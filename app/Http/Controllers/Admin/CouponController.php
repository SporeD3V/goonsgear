<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::query()->latest('id')->paginate((int) config('pagination.admin_per_page', 20));

        return view('admin.coupons.index', [
            'coupons' => $coupons,
        ]);
    }

    public function create(Request $request): View
    {
        $userSearch = trim($request->string('user_search')->toString());

        return view('admin.coupons.create', [
            'coupon' => new Coupon,
            'types' => Coupon::supportedTypes(),
            'scopeTypes' => Coupon::supportedScopes(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(200)->get(['id', 'name']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']),
            'assignableUsers' => $this->resolveAssignableUsers($userSearch),
            'assignedUsers' => [],
            'userSearch' => $userSearch,
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated(), $request->boolean('is_active'));

        $coupon = Coupon::query()->create($validated);
        $this->syncAssignedUsers($coupon, $request->validated());

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon created successfully.');
    }

    public function edit(Request $request, Coupon $coupon): View
    {
        $userSearch = trim($request->string('user_search')->toString());
        if (Coupon::assignmentTableExists()) {
            $coupon->load(['users' => fn ($query) => $query->select(['users.id', 'users.email'])->orderBy('users.email')]);
        } else {
            $coupon->setRelation('users', collect());
        }

        $assignedUserIds = $coupon->users->pluck('id')->all();
        $assignedUserOptions = $coupon->users;

        $assignableUsers = $this->resolveAssignableUsers($userSearch)
            ->merge($assignedUserOptions)
            ->unique('id')
            ->sortBy('email')
            ->values();

        return view('admin.coupons.edit', [
            'coupon' => $coupon,
            'types' => Coupon::supportedTypes(),
            'scopeTypes' => Coupon::supportedScopes(),
            'products' => Product::query()->where('status', 'active')->orderBy('name')->limit(200)->get(['id', 'name']),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'tags' => Tag::query()->where('is_active', true)->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']),
            'assignableUsers' => $assignableUsers,
            'assignedUsers' => $assignedUserIds,
            'userSearch' => $userSearch,
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $validated = $this->normalizePayload($request->validated(), $request->boolean('is_active'));

        $coupon->update($validated);
        $this->syncAssignedUsers($coupon, $request->validated());

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon updated successfully.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('status', 'Coupon deleted successfully.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizePayload(array $validated, bool $isActive): array
    {
        $scopeType = $validated['scope_type'] ?? Coupon::SCOPE_ALL;
        $scopeId = match ($scopeType) {
            Coupon::SCOPE_PRODUCT => isset($validated['scope_product_id']) ? (int) $validated['scope_product_id'] : null,
            Coupon::SCOPE_CATEGORY => isset($validated['scope_category_id']) ? (int) $validated['scope_category_id'] : null,
            Coupon::SCOPE_TAG => isset($validated['scope_tag_id']) ? (int) $validated['scope_tag_id'] : null,
            default => null,
        };

        $validated['code'] = Str::upper(trim((string) $validated['code']));
        $validated['is_active'] = $isActive;
        $validated['is_stackable'] = (bool) ($validated['is_stackable'] ?? false);
        $validated['stack_group'] = isset($validated['stack_group']) ? trim((string) $validated['stack_group']) : null;
        $validated['scope_type'] = $scopeType;
        $validated['scope_id'] = $scopeId;
        $validated['is_personal'] = (bool) ($validated['is_personal'] ?? false);

        if ($validated['stack_group'] === '') {
            $validated['stack_group'] = null;
        }

        if ($validated['scope_type'] === Coupon::SCOPE_ALL) {
            $validated['scope_id'] = null;
        }

        unset($validated['scope_product_id'], $validated['scope_category_id'], $validated['scope_tag_id'], $validated['assigned_user_ids'], $validated['user_usage_limit']);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncAssignedUsers(Coupon $coupon, array $validated): void
    {
        if (! Coupon::assignmentTableExists()) {
            if (! empty($validated['assigned_user_ids'])) {
                throw ValidationException::withMessages([
                    'assigned_user_ids' => 'Coupon assignments are unavailable until the coupon migrations are applied.',
                ]);
            }

            return;
        }

        $userIds = collect($validated['assigned_user_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($userIds === []) {
            $coupon->users()->detach();

            return;
        }

        $usageLimit = isset($validated['user_usage_limit']) && $validated['user_usage_limit'] !== null && $validated['user_usage_limit'] !== ''
            ? (int) $validated['user_usage_limit']
            : null;

        $existingUsageCounts = $coupon->users()
            ->whereIn('users.id', $userIds)
            ->pluck('coupon_user.used_count', 'users.id');

        $syncPayload = [];

        foreach ($userIds as $userId) {
            $syncPayload[$userId] = [
                'usage_limit' => $usageLimit,
                'used_count' => (int) ($existingUsageCounts[$userId] ?? 0),
                'is_active' => true,
            ];
        }

        $coupon->users()->sync($syncPayload);
    }

    private function resolveAssignableUsers(string $userSearch): Collection
    {
        if ($userSearch === '') {
            return collect();
        }

        return User::query()
            ->where('email', 'like', '%'.$userSearch.'%')
            ->orderBy('email')
            ->limit(100)
            ->get(['id', 'email']);
    }
}
