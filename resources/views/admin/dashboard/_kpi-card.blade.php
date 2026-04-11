@props(['label', 'value', 'delta' => null, 'subtitle' => null, 'href' => null, 'delay' => 1])

@php
    $tag = $href ? 'a' : 'div';
    $extraAttrs = $href ? "href=\"{$href}\"" : '';
@endphp

<{{ $tag }} {!! $extraAttrs !!} class="admin-card admin-card-hover {{ $href ? 'group' : '' }} rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="{{ $delay }}">
    <p class="text-sm font-semibold uppercase tracking-wide text-stone-500 {{ $href ? 'group-hover:text-[#36a2eb]' : '' }}">{{ $label }}</p>
    <div class="mt-1 flex items-baseline gap-2">
        <p class="text-3xl font-bold text-stone-800">{!! $value !!}</p>
        @if ($delta !== null)
            <span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $delta > 0 ? 'bg-[#4bc0c0]/15 text-[#4bc0c0]' : ($delta < 0 ? 'bg-[#ff6384]/15 text-[#ff6384]' : 'bg-stone-100 text-stone-500') }}">
                @if ($delta > 0)
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                @elseif ($delta < 0)
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                @endif
                {{ $delta > 0 ? '+' : '' }}{{ $delta }}%
            </span>
        @endif
    </div>
    @if ($subtitle)
        <p class="mt-1 text-sm text-stone-400">{!! $subtitle !!}</p>
    @endif
</{{ $tag }}>
