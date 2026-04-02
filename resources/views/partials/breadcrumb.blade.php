@php
    /** @var array<int, array{name: string, url: string|null}> $breadcrumbs */
@endphp

@if (!empty($breadcrumbs))
    <nav aria-label="Breadcrumb" class="mb-4 text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-1">
            @foreach ($breadcrumbs as $i => $crumb)
                <li class="flex items-center gap-1">
                    @if ($i > 0)
                        <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                    @endif
                    @if ($crumb['url'] && $i < count($breadcrumbs) - 1)
                        <a href="{{ $crumb['url'] }}" class="hover:text-slate-800 hover:underline">{{ $crumb['name'] }}</a>
                    @else
                        <span class="text-slate-700 font-medium">{{ $crumb['name'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($breadcrumbs)->map(fn ($crumb, $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $crumb['name'],
            'item' => $crumb['url'] ?? url()->current(),
        ])->values(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
