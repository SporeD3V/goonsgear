<?php

use App\Models\AdminNote;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $newNote = '';
    public string $newColor = 'warm';
    public ?int $editingId = null;
    public string $editingContent = '';
    public bool $showForm = false;

    /** Optional context key to scope notes to a specific dashboard section */
    public ?string $context = null;
    public ?string $contextLabel = null;

    /** @var list<string> */
    public array $colorOptions = ['warm', 'sky', 'sage', 'rose', 'lavender'];

    public function mount(?string $context = null, ?string $contextLabel = null): void
    {
        $this->context = $context;
        $this->contextLabel = $contextLabel;
    }

    public function addNote(): void
    {
        $this->validate(['newNote' => 'required|string|max:1000']);

        AdminNote::create([
            'user_id' => auth()->id(),
            'content' => $this->newNote,
            'color' => $this->newColor,
            'context' => $this->context,
            'context_label' => $this->contextLabel,
        ]);

        $this->reset('newNote', 'newColor', 'showForm');
        unset($this->notes);
    }

    public function startEditing(int $id): void
    {
        $note = AdminNote::where('user_id', auth()->id())->findOrFail($id);
        $this->editingId = $id;
        $this->editingContent = $note->content;
    }

    public function saveEdit(): void
    {
        $this->validate(['editingContent' => 'required|string|max:1000']);

        AdminNote::where('user_id', auth()->id())
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
        $note = AdminNote::where('user_id', auth()->id())->findOrFail($id);
        $note->update(['is_pinned' => ! $note->is_pinned]);
        unset($this->notes);
    }

    public function deleteNote(int $id): void
    {
        AdminNote::where('user_id', auth()->id())->where('id', $id)->delete();
        unset($this->notes);
    }

    /**
     * @return Collection<int, AdminNote>
     */
    #[Computed]
    public function notes(): Collection
    {
        return AdminNote::where('user_id', auth()->id())
            ->when($this->context, fn ($q) => $q->where('context', $this->context))
            ->when(! $this->context, fn ($q) => $q->whereNull('context'))
            ->orderByDesc('is_pinned')
            ->latest()
            ->take(20)
            ->get();
    }
}; ?>

<div>
    <div class="flex items-center justify-between">
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
            <textarea wire:model="newNote"
                      rows="3"
                      placeholder="Write a note…"
                      class="w-full rounded-lg border border-stone-200 bg-stone-50 px-3 py-2.5 text-base text-stone-800 placeholder-stone-400 transition focus:border-amber-400 focus:bg-white focus:ring-2 focus:ring-amber-200 focus:outline-none"></textarea>
            @error('newNote') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="flex items-center gap-4">
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
                        <div class="flex gap-2">
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
                        <p class="flex-1 text-base leading-relaxed text-stone-700 whitespace-pre-line">{{ $note->content }}</p>
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-xs text-stone-400">{{ $note->created_at->diffForHumans() }}</span>
                        <div class="flex gap-1 opacity-0 transition-opacity duration-200 group-hover:opacity-100">
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
