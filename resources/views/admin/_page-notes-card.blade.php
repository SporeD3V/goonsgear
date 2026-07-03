@props([
    'context',
    'label',
    'anchorOptions' => [],
    'cardClass' => 'admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm',
])

<div class="{{ $cardClass }}">
    <livewire:admin.dashboard-notes
        :context="$context"
        :context-label="$label"
        :anchor-options="$anchorOptions"
        :key="'page-notes-' . $context" />
</div>