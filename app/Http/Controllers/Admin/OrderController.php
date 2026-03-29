<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $paymentStatus = (string) $request->query('payment_status', '');
        $search = (string) $request->query('q', '');

        $orders = Order::query()
            ->withCount('items')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($paymentStatus !== '', fn ($query) => $query->where('payment_status', $paymentStatus))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('order_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statusOptions' => $this->statusOptions(),
            'paymentStatusOptions' => $this->paymentStatusOptions(),
            'filters' => [
                'status' => $status,
                'payment_status' => $paymentStatus,
                'q' => $search,
            ],
        ]);
    }

    public function show(Order $order): View
    {
        $order->load([
            'items' => fn ($query) => $query->orderBy('id'),
            'items.product.media' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderBy('position')
                ->orderBy('id'),
        ]);

        return view('admin.orders.show', [
            'order' => $order,
            'statusOptions' => $this->statusOptions(),
            'paymentStatusOptions' => $this->paymentStatusOptions(),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in($this->statusOptions())],
            'payment_status' => ['required', 'string', Rule::in($this->paymentStatusOptions())],
        ]);

        $order->update($validated);

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('status', 'Order updated successfully.');
    }

    /**
     * @return list<string>
     */
    private function statusOptions(): array
    {
        return ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
    }

    /**
     * @return list<string>
     */
    private function paymentStatusOptions(): array
    {
        return ['pending', 'paid', 'failed', 'refunded'];
    }
}
