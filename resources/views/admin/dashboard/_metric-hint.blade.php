{{-- Inline metric explanation: shows abbreviation meaning + formula --}}
@props(['abbr' => null, 'meaning' => null, 'formula' => null])

<div class="mt-1.5 space-y-0.5 text-[12px] leading-relaxed text-stone-400">
    @if ($abbr && $meaning)
        <p><span class="font-semibold text-stone-500">{{ $abbr }}</span> = {{ $meaning }}</p>
    @endif
    @if ($formula)
        <p class="font-mono text-[11px] text-stone-400/80">{{ $formula }}</p>
    @endif
</div>
