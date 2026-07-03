<?php

use App\Models\AdminActivityLog;
use App\Models\EditHistory;
use App\Models\Tag;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';
    public string $filterType = '';

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $slug = '';
    public string $type = 'artist';
    public bool $is_active = true;
    public string $description = '';
    public string $meta_title = '';
    public string $meta_description = '';
    public bool $show_on_homepage = false;
    public bool $remove_logo = false;

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    public ?string $currentLogoPath = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $tag = Tag::findOrFail($id);
        $this->editingId = $tag->id;
        $this->name = $tag->name;
        $this->slug = $tag->slug;
        $this->type = $tag->type;
        $this->is_active = $tag->is_active;
        $this->description = $tag->description ?? '';
        $this->meta_title = $tag->meta_title ?? '';
        $this->meta_description = $tag->meta_description ?? '';
        $this->show_on_homepage = $tag->show_on_homepage;
        $this->currentLogoPath = $tag->logo_path;
        $this->remove_logo = false;
        $this->logo = null;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $nameUnique = Rule::unique('tags', 'name')->where('type', $this->type);
        $slugUnique = Rule::unique('tags', 'slug')->where('type', $this->type);

        if ($this->editingId) {
            $nameUnique = $nameUnique->ignore($this->editingId);
            $slugUnique = $slugUnique->ignore($this->editingId);
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', $nameUnique],
            'slug' => ['required', 'string', 'max:255', $slugUnique],
            'type' => ['required', 'string', Rule::in(['artist', 'brand', 'custom'])],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'show_on_homepage' => ['boolean'],
            'logo' => ['nullable', 'image', 'max:5120'],
        ]);

        unset($validated['logo']);

        if ($this->editingId) {
            $tag = Tag::findOrFail($this->editingId);

            foreach (['name', 'slug', 'type', 'is_active', 'show_on_homepage', 'description', 'meta_title', 'meta_description'] as $field) {
                $oldValue = (string) $tag->getAttribute($field);
                $newValue = (string) ($validated[$field] ?? '');
                if ($oldValue !== $newValue) {
                    EditHistory::recordChange($tag, $field, $oldValue, $newValue);
                }
            }

            $tag->update($validated);

            // Handle logo removal
            if ($this->remove_logo && $tag->logo_path !== null) {
                $this->deleteTagLogo($tag->logo_path);
                $tag->update(['logo_path' => null, 'show_on_homepage' => false]);
            }

            // Handle new logo upload
            if ($this->logo !== null && in_array($tag->type, ['artist', 'brand'], true)) {
                if ($tag->logo_path !== null) {
                    $this->deleteTagLogo($tag->logo_path);
                }

                $logoPath = $this->storeTagLogo($this->logo, $tag->slug);
                if ($logoPath !== null) {
                    $tag->update(['logo_path' => $logoPath]);
                }
            }

            // Ensure show_on_homepage requires a logo
            if ($tag->fresh()->show_on_homepage && $tag->fresh()->logo_path === null) {
                $tag->update(['show_on_homepage' => false]);
            }

            AdminActivityLog::log(AdminActivityLog::ACTION_UPDATED, $tag, "Updated tag \"{$tag->name}\"");
            session()->flash('status', 'Tag updated.');
        } else {
            $tag = Tag::create($validated);
            AdminActivityLog::log(AdminActivityLog::ACTION_CREATED, $tag, "Created tag \"{$tag->name}\"");

            if ($this->logo !== null && in_array($tag->type, ['artist', 'brand'], true)) {
                $logoPath = $this->storeTagLogo($this->logo, $tag->slug);
                if ($logoPath !== null) {
                    $tag->update(['logo_path' => $logoPath]);
                }
            }

            if ($tag->show_on_homepage && $tag->logo_path === null) {
                $tag->update(['show_on_homepage' => false]);
            }

            session()->flash('status', 'Tag created.');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $tag = Tag::findOrFail($id);
        $oldValue = (string) $tag->is_active;
        $tag->update(['is_active' => ! $tag->is_active]);
        EditHistory::recordChange($tag, 'is_active', $oldValue, (string) $tag->is_active);
        AdminActivityLog::log(
            AdminActivityLog::ACTION_UPDATED,
            $tag,
            ($tag->is_active ? 'Activated' : 'Deactivated') . " tag \"{$tag->name}\""
        );
    }

    public function delete(int $id): void
    {
        $tag = Tag::findOrFail($id);
        AdminActivityLog::log(AdminActivityLog::ACTION_DELETED, $tag, "Deleted tag \"{$tag->name}\"");

        if ($tag->logo_path !== null) {
            $this->deleteTagLogo($tag->logo_path);
        }

        $tag->delete();
        session()->flash('status', 'Tag deleted.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->slug = '';
        $this->type = 'artist';
        $this->is_active = true;
        $this->description = '';
        $this->meta_title = '';
        $this->meta_description = '';
        $this->show_on_homepage = false;
        $this->remove_logo = false;
        $this->logo = null;
        $this->currentLogoPath = null;
        $this->resetValidation();
    }

    private function storeTagLogo(TemporaryUploadedFile $file, string $slug): ?string
    {
        try {
            $directory = 'tags/' . $slug . '/logo';
            $baseFilename = $slug . '-logo';

            $fallbackDir = 'tags/' . $slug . '/fallback';
            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            $fallbackPath = $file->storeAs($fallbackDir, $baseFilename . '.' . $extension, 'public');

            if ($fallbackPath === false) {
                return null;
            }

            $absoluteFallbackPath = storage_path('app/public/' . $fallbackPath);

            if (! is_dir(storage_path('app/public/' . $directory))) {
                mkdir(storage_path('app/public/' . $directory), 0755, true);
            }

            $avifRelativePath = $directory . '/' . $baseFilename . '.avif';
            $webpRelativePath = $directory . '/' . $baseFilename . '.webp';

            $avifCreated = $this->convertTagLogoTo($absoluteFallbackPath, $avifRelativePath, 'avif', 400, 400);
            if ($avifCreated) {
                return $avifRelativePath;
            }

            $webpCreated = $this->convertTagLogoTo($absoluteFallbackPath, $webpRelativePath, 'webp', 400, 400);
            if ($webpCreated) {
                return $webpRelativePath;
            }

            $originalPath = $directory . '/' . $baseFilename . '.' . $extension;
            Storage::disk('public')->copy($fallbackPath, $originalPath);

            return $originalPath;
        } catch (\Throwable $exception) {
            Log::warning('Tag logo upload failed.', [
                'slug' => $slug,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function convertTagLogoTo(string $absoluteSourcePath, string $relativeTargetPath, string $format, int $maxWidth, int $maxHeight): bool
    {
        try {
            $absoluteTargetPath = storage_path('app/public/' . $relativeTargetPath);
            $targetDir = dirname($absoluteTargetPath);

            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            if (class_exists('Imagick')) {
                $imagick = new \Imagick($absoluteSourcePath);
                $imagick->thumbnailImage($maxWidth, $maxHeight, true);
                $imagick->setImageFormat($format);
                $imagick->setImageCompressionQuality($format === 'avif' ? 62 : 82);
                $imagick->stripImage();
                $saved = $imagick->writeImage($absoluteTargetPath);
                $imagick->clear();
                $imagick->destroy();

                return $saved && is_file($absoluteTargetPath);
            }

            if (! function_exists('imagecreatetruecolor')) {
                return false;
            }

            if ($format === 'avif' && ! function_exists('imageavif')) {
                return false;
            }

            if ($format === 'webp' && ! function_exists('imagewebp')) {
                return false;
            }

            $imageInfo = @getimagesize($absoluteSourcePath);
            if ($imageInfo === false) {
                return false;
            }

            $mime = strtolower((string) $imageInfo['mime']);
            $srcImage = match ($mime) {
                'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($absoluteSourcePath) : false,
                'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($absoluteSourcePath) : false,
                'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absoluteSourcePath) : false,
                'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($absoluteSourcePath) : false,
                default => false,
            };

            if ($srcImage === false) {
                return false;
            }

            $srcW = $imageInfo[0];
            $srcH = $imageInfo[1];
            $ratio = min($maxWidth / $srcW, $maxHeight / $srcH);
            $newW = (int) round($srcW * $ratio);
            $newH = (int) round($srcH * $ratio);

            $destImage = @imagecreatetruecolor($newW, $newH);
            if ($destImage === false) {
                return false;
            }

            @imagealphablending($destImage, false);
            @imagesavealpha($destImage, true);
            $transparent = @imagecolorallocatealpha($destImage, 0, 0, 0, 127);
            @imagefill($destImage, 0, 0, $transparent);
            @imagealphablending($destImage, true);
            @imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

            $saved = match ($format) {
                'avif' => @imageavif($destImage, $absoluteTargetPath, 62),
                'webp' => @imagewebp($destImage, $absoluteTargetPath, 82),
                default => false,
            };

            return $saved && is_file($absoluteTargetPath);
        } catch (\Throwable $exception) {
            Log::warning('Tag logo conversion failed.', [
                'target' => $relativeTargetPath,
                'format' => $format,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function deleteTagLogo(string $logoPath): void
    {
        try {
            Storage::disk('public')->delete($logoPath);
        } catch (\Throwable $exception) {
            Log::warning('Failed to delete tag logo file.', [
                'path' => $logoPath,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    #[Computed]
    public function tags()
    {
        return Tag::query()
            ->withCount([
                'followers as followers_count',
                'products as active_products_count' => fn ($q) => $q->where('status', 'active'),
            ])
            ->when($this->filterType !== '', fn ($q) => $q->where('type', $this->filterType))
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%' . $this->search . '%')
                ->orWhere('slug', 'like', '%' . $this->search . '%'))
            ->orderBy('type')
            ->orderBy('name')
            ->paginate((int) config('pagination.admin_tag_per_page', 30));
    }
}; ?>

<div class="space-y-6">
    {{-- Flash message --}}
    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    {{-- Header row --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-lg font-semibold">Tags{{ $filterType ? ': ' . ucfirst($filterType) . 's' : '' }}</h2>
        <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Search tags…"
                class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm sm:w-64"
            >
            <button wire:click="openCreate" class="shrink-0 rounded-lg bg-[#36a2eb] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2b8ac9]">
                New Tag
            </button>
        </div>
    </div>

    {{-- Filters & Table --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
        {{-- Type filter tabs --}}
        <div class="mb-4 flex flex-wrap gap-2 text-sm">
            @foreach (['' => 'All', 'artist' => 'Artists', 'brand' => 'Brands', 'custom' => 'Custom'] as $value => $label)
                <button
                    wire:click="$set('filterType', '{{ $value }}')"
                    class="rounded px-3 py-1 {{ $filterType === $value ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}"
                >{{ $label }}</button>
            @endforeach
        </div>

        {{-- Loading indicator --}}
        <div wire:loading.delay class="mb-2 text-xs text-stone-500">Loading…</div>

        {{-- Tag list --}}
        <ul class="divide-y divide-stone-100">
            @forelse ($this->tags as $tag)
                <li wire:key="tag-{{ $tag->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3 transition hover:bg-stone-50/60">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-stone-800">{{ $tag->name }}</p>
                        <p class="mt-0.5 truncate font-mono text-xs text-stone-400">{{ $tag->slug }}</p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                            <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ ucfirst($tag->type) }}</span>
                            <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $tag->followers_count }} {{ \Illuminate\Support\Str::plural('follower', $tag->followers_count) }}</span>
                            <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $tag->active_products_count }} active {{ \Illuminate\Support\Str::plural('product', $tag->active_products_count) }}</span>
                        </div>
                    </div>

                    <button wire:click="toggleActive({{ $tag->id }})" title="{{ $tag->is_active ? 'Click to deactivate' : 'Click to activate' }}"
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold transition hover:ring-1 hover:ring-stone-300 {{ $tag->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-100 text-stone-500' }}">
                        {{ $tag->is_active ? 'Active' : 'Inactive' }}
                    </button>

                    <div class="flex items-center gap-1">
                        <button wire:click="openEdit({{ $tag->id }})" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-[#36a2eb]/10 hover:text-[#36a2eb]" title="Edit tag">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                        </button>
                        <button wire:click="delete({{ $tag->id }})" wire:confirm="Delete this tag?" class="flex h-9 w-9 items-center justify-center rounded-lg text-stone-500 transition hover:bg-red-50 hover:text-red-600" title="Delete tag">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </div>
                </li>
            @empty
                <li class="px-6 py-10 text-center text-sm text-stone-500">
                    {{ $search || $filterType ? 'No tags match your filters.' : 'No tags found.' }}
                </li>
            @endforelse
        </ul>

    {{-- Pagination --}}
        <div class="mt-4">{{ $this->tags->links() }}</div>
    </div>

    {{-- Modal --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeModal()">
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-black/50" wire:click="closeModal"></div>

            {{-- Dialog --}}
            <div class="relative z-10 w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                <h3 class="mb-4 text-lg font-semibold">{{ $editingId ? 'Edit Tag' : 'New Tag' }}</h3>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Name</label>
                            <input type="text" wire:model="name" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm" maxlength="255">
                            @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Slug</label>
                            <input type="text" wire:model="slug" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm" maxlength="255">
                            @error('slug') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Type</label>
                            <select wire:model.live="type" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                                <option value="artist">Artist</option>
                                <option value="brand">Brand</option>
                                <option value="custom">Custom</option>
                            </select>
                            @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-center gap-2 pt-7">
                            <input type="checkbox" wire:model="is_active" id="modal-is-active" class="h-4 w-4 rounded border-stone-300">
                            <label for="modal-is-active" class="text-sm font-medium">Active</label>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Description</label>
                        <textarea wire:model="description" rows="3" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm"></textarea>
                        @error('description') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- SEO fields with character counters --}}
                    <div class="space-y-4 rounded-lg border border-stone-200 p-4">
                        <h4 class="text-sm font-semibold">SEO</h4>

                        @include('admin.partials.seo-field', [
                            'name'      => 'meta_title',
                            'label'     => 'Meta Title',
                            'value'     => $meta_title,
                            'min'       => 50,
                            'max'       => 60,
                            'hint'      => 'Recommended 50–60 characters. This appears as the clickable headline in search results.',
                            'wireModel' => 'meta_title',
                        ])

                        @include('admin.partials.seo-field', [
                            'name'      => 'meta_description',
                            'label'     => 'Meta Description',
                            'value'     => $meta_description,
                            'type'      => 'textarea',
                            'rows'      => 3,
                            'min'       => 120,
                            'max'       => 160,
                            'hint'      => 'Recommended 120–160 characters. This appears below the title in search results.',
                            'wireModel' => 'meta_description',
                        ])
                    </div>

                    {{-- Logo section: artist & brand only --}}
                    @if (in_array($type, ['artist', 'brand']))
                        <div class="space-y-3 rounded-lg border border-stone-200 p-4">
                            <h4 class="text-sm font-semibold">Logo Image (200×200)</h4>

                            @if ($currentLogoPath && ! $remove_logo)
                                <div class="flex items-center gap-4">
                                    <img
                                        src="{{ route('media.show', ['path' => $currentLogoPath]) }}"
                                        alt="{{ $name }} logo"
                                        class="h-16 w-16 rounded-lg border border-stone-200 object-cover"
                                    >
                                    <label class="inline-flex items-center gap-2 text-sm text-rose-600">
                                        <input type="checkbox" wire:model="remove_logo" class="h-4 w-4 rounded border-stone-300">
                                        Remove current logo
                                    </label>
                                </div>
                            @endif

                            <div>
                                <label class="mb-1 block text-sm font-medium">{{ $currentLogoPath ? 'Upload new logo' : 'Upload logo' }}</label>
                                <input type="file" wire:model="logo" accept="image/*" class="w-full rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-3 py-2 text-sm">
                                <p class="mt-1 text-xs text-stone-500">JPG, PNG or WebP. Will be converted to AVIF 200×200. Max 5 MB.</p>
                                @error('logo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                <div wire:loading wire:target="logo" class="mt-1 text-xs text-blue-600">Uploading…</div>
                            </div>

                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="show_on_homepage" class="h-4 w-4 rounded border-stone-300">
                                Display in "Shop by Artist" carousel on homepage
                                @if (! $currentLogoPath && $logo === null)
                                    <span class="text-xs text-stone-400">(requires a logo)</span>
                                @endif
                            </label>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" wire:click="closeModal" class="rounded-lg border border-stone-200 focus:border-[#36a2eb] focus:outline-none px-4 py-2 text-sm text-stone-700 hover:bg-stone-50">Cancel</button>
                        <button type="submit" class="rounded bg-stone-800 px-4 py-2 text-sm font-medium text-white hover:bg-stone-900">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Save Changes' : 'Create Tag' }}</span>
                            <span wire:loading wire:target="save">Saving…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

