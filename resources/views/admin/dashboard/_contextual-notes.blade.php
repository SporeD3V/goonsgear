{{-- Contextual Notes Toggle --}}
@props(['context', 'label'])

<div x-data="{ open: false }" class="mt-3 border-t border-stone-100 pt-2">
    <button @click="open = !open"
            class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-stone-400 transition hover:bg-amber-50 hover:text-amber-600"
            :class="open && 'bg-amber-50 text-amber-600'">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
        <span x-text="open ? 'Hide Notes' : 'Notes'"></span>
    </button>
    <div x-show="open" x-collapse class="mt-2">
        <livewire:admin.dashboard-notes :context="$context" :context-label="$label" :key="'notes-' . $context" />
    </div>
</div>
