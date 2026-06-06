<?php

use App\Models\AdminNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $newNote = '';
    public string $newColor = 'warm';
    public ?int $editingId = null;
    public string $editingContent = '';
    public bool $showForm = false;

    /** @var array<int, array{key:string,label:string,value:?string,meta?:array<string,mixed>}> */
    public array $anchorOptions = [];
    public string $selectedAnchorKey = '';

    /** Optional context key to scope notes to a specific dashboard section */
    public ?string $context = null;
    public ?string $contextLabel = null;

    /** @var list<string> */
    public array $colorOptions = ['warm', 'sky', 'sage', 'rose', 'lavender'];

    public function mount(?string $context = null, ?string $contextLabel = null, array $anchorOptions = []): void
    {
        $this->context = $context;
        $this->contextLabel = $contextLabel;
        $this->anchorOptions = collect($anchorOptions)
            ->filter(fn ($option) => isset($option['key'], $option['label']))
            ->values()
            ->all();
    }

    private function userId(): int
    {
        return (int) Auth::id();
    }

    public function addNote(): void
    {
        $this->validate(['newNote' => 'required|string|max:1000']);

        $selectedAnchor = $this->selectedAnchor();

        AdminNote::create([
            'user_id' => $this->userId(),
            'content' => $this->newNote,
            'color' => $this->newColor,
            'context' => $this->context,
            'context_label' => $this->contextLabel,
            'anchor_key' => $selectedAnchor['key'] ?? null,
            'anchor_label' => $selectedAnchor['label'] ?? null,
            'anchor_value' => $selectedAnchor['value'] ?? null,
            'anchor_meta' => $selectedAnchor['meta'] ?? null,
        ]);

        $this->reset('newNote', 'newColor', 'showForm', 'selectedAnchorKey');
        unset($this->notes);
    }

    public function removeAnchor(int $id): void
    {
        AdminNote::where('user_id', $this->userId())
            ->where('id', $id)
            ->update([
                'anchor_key' => null,
                'anchor_label' => null,
                'anchor_value' => null,
                'anchor_meta' => null,
            ]);

        unset($this->notes);
    }

    /**
     * @return array{key:string,label:string,value:?string,meta?:array<string,mixed>}|null
     */
    private function selectedAnchor(): ?array
    {
        if ($this->selectedAnchorKey === '') {
            return null;
        }

        return collect($this->anchorOptions)
            ->first(fn ($option) => ($option['key'] ?? null) === $this->selectedAnchorKey);
    }

    #[On('dashboard-note-anchor-selected')]
    public function selectAnchorFromChart(string $context, string $anchorKey): void
    {
        if ($this->context !== $context || $anchorKey === '') {
            return;
        }

        $anchorExists = collect($this->anchorOptions)
            ->contains(fn ($option) => ($option['key'] ?? null) === $anchorKey);

        if (! $anchorExists) {
            return;
        }

        $this->selectedAnchorKey = $anchorKey;
        $this->showForm = true;
    }

    public function startEditing(int $id): void
    {
        $note = AdminNote::where('user_id', $this->userId())->findOrFail($id);
        $this->editingId = $id;
        $this->editingContent = $note->content;
    }

    public function saveEdit(): void
    {
        $this->validate(['editingContent' => 'required|string|max:1000']);

        AdminNote::where('user_id', $this->userId())
            ->where('id', $this->editingId)
            ->update(['content' => $this->editingContent]);

        $this->reset('editingId', 'editingContent');
        unset($this->notes);
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editingContent');
    }

    public function togglePin(int $id): void
    {
        $note = AdminNote::where('user_id', $this->userId())->findOrFail($id);
        $note->update(['is_pinned' => ! $note->is_pinned]);
        unset($this->notes);
    }

    public function deleteNote(int $id): void
    {
        AdminNote::where('user_id', $this->userId())->where('id', $id)->delete();
        unset($this->notes);
    }

    /**
     * @return Collection<int, AdminNote>
     */
    #[Computed]
    public function notes(): Collection
    {
        return AdminNote::where('user_id', $this->userId())
            ->when($this->context, fn ($q) => $q->where('context', $this->context))
            ->when(! $this->context, fn ($q) => $q->whereNull('context'))
            ->orderByDesc('is_pinned')
            ->latest()
            ->take(20)
            ->get();
    }
}; ?>

<div>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-base font-semibold text-stone-700">{{ $context ? 'Notes' : 'My Notes' }}</h3>
            @if ($contextLabel)
                <p class="text-xs text-stone-400">Attached to: {{ $contextLabel }}</p>
            @endif
        </div>
        <button wire:click="$toggle('showForm')"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-amber-700 transition-all duration-200 hover:bg-amber-50 active:scale-95">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Add Note
        </button>
    </div>

    {{-- Add Note Form --}}
    @if ($showForm)
        <form wire:submit="addNote" class="mt-3 space-y-3 rounded-xl border border-stone-200 bg-white p-4 shadow-sm animate-fade-in">
            @if (!empty($anchorOptions))
                <div class="space-y-2 rounded-lg border border-stone-200 bg-stone-50 px-3 py-3">
                    <label for="note-anchor-{{ $context ?: 'general' }}" class="block text-sm font-medium text-stone-700">Attach to point</label>
                    <select id="note-anchor-{{ $context ?: 'general' }}"
                            wire:model="selectedAnchorKey"
                            class="w-full rounded-lg border border-stone-200 bg-white px-3 py-2 text-sm text-stone-700 focus:border-amber-400 focus:ring-2 focus:ring-amber-200 focus:outline-none">
                        <option value="">Section only</option>
                        @foreach ($anchorOptions as $option)
                            <option value="{{ $option['key'] }}">{{ $option['label'] }}@if(!empty($option['value'])) · {{ $option['value'] }}@endif</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-stone-500">Pick the exact point, item, or row this note refers to.</p>
                </div>
            @endif

            <textarea wire:model="newNote"
                      rows="3"
                      placeholder="Write a note…"
                      class="w-full rounded-lg border border-stone-200 bg-stone-50 px-3 py-2.5 text-base text-stone-800 placeholder-stone-400 transition focus:border-amber-400 focus:bg-white focus:ring-2 focus:ring-amber-200 focus:outline-none"></textarea>
            @error('newNote') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex flex-wrap items-center gap-4">
                <span class="text-sm text-stone-500">Color:</span>
                <div class="flex gap-2">
                    @foreach ($colorOptions as $color)
                        <button type="button"
                                wire:click="$set('newColor', '{{ $color }}')"
                                class="h-7 w-7 rounded-full border-2 transition-all duration-150 {{ $newColor === $color ? 'scale-110 border-stone-600 ring-2 ring-stone-300' : 'border-transparent hover:scale-105' }} {{ match($color) { 'warm' => 'bg-amber-100', 'sky' => 'bg-sky-100', 'sage' => 'bg-emerald-100', 'rose' => 'bg-rose-100', 'lavender' => 'bg-violet-100' } }}">
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-all duration-200 hover:bg-amber-700 active:scale-95">
                    Save Note
                </button>
                <button type="button"
                        wire:click="$set('showForm', false)"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-stone-500 transition hover:text-stone-700">
                    Cancel
                </button>
            </div>
        </form>
    @endif

    {{-- Notes List --}}
    <div class="mt-3 space-y-2">
        @forelse ($this->notes as $note)
            <div wire:key="note-{{ $note->id }}"
                 class="group relative rounded-xl border p-4 transition-all duration-200 hover:shadow-md animate-fade-in
                    {{ match($note->color) {
                        'sky' => 'border-sky-200 bg-sky-50',
                        'sage' => 'border-emerald-200 bg-emerald-50',
                        'rose' => 'border-rose-200 bg-rose-50',
                        'lavender' => 'border-violet-200 bg-violet-50',
                        default => 'border-amber-200 bg-amber-50',
                    } }}">

                @if ($editingId === $note->id)
                    {{-- Editing --}}
                    <form wire:submit="saveEdit" class="space-y-2">
                        <textarea wire:model="editingContent"
                                  rows="3"
                                  class="w-full rounded-lg border border-stone-200 bg-white px-3 py-2 text-base text-stone-800 focus:border-amber-400 focus:ring-2 focus:ring-amber-200 focus:outline-none"></textarea>
                        @error('editingContent') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-amber-700 active:scale-95">Save</button>
                            <button type="button" wire:click="cancelEdit" class="rounded-lg px-3 py-1.5 text-sm font-medium text-stone-500 transition hover:text-stone-700">Cancel</button>
                        </div>
                    </form>
                @else
                    {{-- Display --}}
                    <div class="flex items-start gap-3">
                        @if ($note->is_pinned)
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                        @endif
                        <div class="flex-1">
                            @if ($note->anchor_label)
                                <div class="mb-2 rounded-lg border border-white/60 bg-white/60 px-3 py-2 text-xs text-stone-600">
                                    <div class="font-medium text-stone-700">Linked to peak</div>
                                    <div class="mt-0.5 text-stone-500">{{ $note->anchor_label }}@if ($note->anchor_value) · {{ $note->anchor_value }}@endif</div>
                                </div>
                            @endif
                            <p class="text-base leading-relaxed text-stone-700 whitespace-pre-line">{{ $note->content }}</p>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-xs text-stone-400">{{ $note->created_at->diffForHumans() }}</span>
                        <div class="flex gap-1 opacity-100 transition-opacity duration-200 sm:opacity-0 sm:group-hover:opacity-100">
                            <button wire:click="togglePin({{ $note->id }})"
                                    title="{{ $note->is_pinned ? 'Unpin' : 'Pin' }}"
                                    class="rounded-lg p-2 text-stone-400 transition hover:bg-white/60 hover:text-amber-600">
                                <svg class="h-4 w-4" fill="{{ $note->is_pinned ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 12V4h1V2H7v2h1v8l-2 2v2h5.2v6h1.6v-6H18v-2l-2-2z"/></svg>
                            </button>
                            <button wire:click="startEditing({{ $note->id }})"
                                    title="Edit"
                                    class="rounded-lg p-2 text-stone-400 transition hover:bg-white/60 hover:text-stone-600">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                            </button>
                            @if ($note->anchor_key)
                                <button wire:click="removeAnchor({{ $note->id }})"
                                        wire:confirm="Remove this note link?"
                                        title="Remove link"
                                        class="rounded-lg p-2 text-stone-400 transition hover:bg-white/60 hover:text-amber-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 12H6m12 0a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z"/></svg>
                                </button>
                            @endif
                            <button wire:click="deleteNote({{ $note->id }})"
                                    wire:confirm="Delete this note?"
                                    title="Delete"
                                    class="rounded-lg p-2 text-stone-400 transition hover:bg-white/60 hover:text-red-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="py-6 text-center text-sm text-stone-400">No notes yet. Jot down reminders, todos, or ideas.</p>
        @endforelse
    </div>
</div>
