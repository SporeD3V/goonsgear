{{--
    SEO field with character counter + visual progress bar.

    Required variables:
        $name  – form field name (e.g. 'meta_title')
        $label – display label (e.g. 'Meta Title')
        $min   – minimum recommended characters
        $max   – maximum recommended characters
        $hint  – SEO recommendation text

    Optional variables:
        $value    – current value (default '')
        $type     – 'input' or 'textarea' (default 'input')
        $rows     – textarea rows (default 3)
        $wireModel – wire:model attribute for Livewire fields (default null)
--}}

@php
    $value     = $value ?? '';
    $type      = $type ?? 'input';
    $rows      = $rows ?? 3;
    $wireModel = $wireModel ?? null;
@endphp

<div x-data="{
    count: {{ strlen($value) }},
    min: {{ $min }},
    max: {{ $max }},
    update(e) { this.count = e.target.value.length },
    get pct() { return Math.min(100, Math.round(this.count / this.max * 100)) },
    get color() {
        if (this.count === 0) return 'bg-slate-200';
        if (this.count < this.min) return 'bg-amber-400';
        if (this.count <= this.max) return 'bg-emerald-500';
        return 'bg-red-500';
    },
    get textColor() {
        if (this.count === 0) return 'text-slate-400';
        if (this.count < this.min) return 'text-amber-600';
        if (this.count <= this.max) return 'text-emerald-600';
        return 'text-red-600';
    }
}">
    <div class="mb-1 flex items-baseline justify-between">
        <label class="block text-sm font-medium">{{ $label }}</label>
        <span class="text-xs" :class="textColor">
            <span x-text="count">{{ strlen($value) }}</span>/<span>{{ $max }}</span>
        </span>
    </div>

    @if ($type === 'textarea')
        <textarea
            name="{{ $name }}"
            rows="{{ $rows }}"
            class="w-full rounded border border-slate-300 px-3 py-2"
            x-on:input="update($event)"
            @if ($wireModel) wire:model="{{ $wireModel }}" @endif
        >{{ $value }}</textarea>
    @else
        <input
            type="text"
            name="{{ $name }}"
            value="{{ $value }}"
            class="w-full rounded border border-slate-300 px-3 py-2"
            x-on:input="update($event)"
            @if ($wireModel) wire:model="{{ $wireModel }}" @endif
        >
    @endif

    {{-- Progress bar --}}
    <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full transition-all duration-200" :class="color" :style="'width:' + pct + '%'"></div>
    </div>

    {{-- Hint --}}
    <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
</div>
