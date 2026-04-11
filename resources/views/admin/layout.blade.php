<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Admin' }} | GoonsGear</title>
        @include('partials.favicons')
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-stone-50 text-stone-900">
        <div class="flex min-h-screen">
            {{-- Sidebar --}}
            <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-60 -translate-x-full flex-col border-r border-stone-200 bg-stone-900 text-stone-300 transition-transform lg:translate-x-0">
                {{-- Logo --}}
                <div class="flex items-center gap-2.5 border-b border-stone-700 px-4 py-5">
                    <svg class="h-6 w-6 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    <span class="text-base font-bold text-white">GoonsGear Admin</span>
                </div>

                {{-- Navigation --}}
                <nav class="flex-1 overflow-y-auto px-3 py-4">
                    @php
                        $currentRoute = Route::currentRouteName() ?? '';
                        $inStore = str_starts_with($currentRoute, 'admin.orders') || str_starts_with($currentRoute, 'admin.products') || str_starts_with($currentRoute, 'admin.categories') || str_starts_with($currentRoute, 'admin.tags');
                        $inPricing = str_starts_with($currentRoute, 'admin.coupons') || str_starts_with($currentRoute, 'admin.bundle-discounts') || str_starts_with($currentRoute, 'admin.regional-discounts');
                        $inConfig = str_starts_with($currentRoute, 'admin.maintenance.integrations') || str_starts_with($currentRoute, 'admin.maintenance.abandoned-cart') || str_starts_with($currentRoute, 'admin.url-redirects');
                        $inSystem = str_starts_with($currentRoute, 'admin.activity-log') || str_starts_with($currentRoute, 'admin.maintenance.fallback-media') || str_starts_with($currentRoute, 'admin.maintenance.clear');
                    @endphp

                    {{-- Dashboard --}}
                    <a href="{{ route('admin.dashboard') }}"
                       class="mb-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ $currentRoute === 'admin.dashboard' ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z"/></svg>
                        Dashboard
                    </a>

                    {{-- Section: Store Management --}}
                    <div x-data="{ open: {{ $inStore || $currentRoute === 'admin.dashboard' ? 'true' : 'false' }} }" class="mt-5">
                        <button @click="open = !open" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wider text-stone-500 transition hover:text-stone-300">
                            <span>Store</span>
                            <svg class="h-3.5 w-3.5 transition-transform" :class="open && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </button>
                        <div x-show="open" x-transition.origin.top>
                            <a href="{{ route('admin.orders.index') }}"
                               class="mb-0.5 mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.orders') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg>
                                Orders
                            </a>

                            <a href="{{ route('admin.products.index') }}"
                               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.products') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25"/></svg>
                                Products
                            </a>

                            <a href="{{ route('admin.categories.index') }}"
                               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.categories') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/></svg>
                                Categories
                            </a>

                            <a href="{{ route('admin.tags.index') }}"
                               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.tags') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                                Tags
                            </a>
                        </div>
                    </div>

                    {{-- Section: Pricing & Promotions --}}
                    <div x-data="{ open: {{ $inPricing ? 'true' : 'false' }} }" class="mt-4">
                        <button @click="open = !open" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wider text-stone-500 transition hover:text-stone-300">
                            <span>Pricing</span>
                            <svg class="h-3.5 w-3.5 transition-transform" :class="open && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </button>
                        <div x-show="open" x-transition.origin.top>
                            <a href="{{ route('admin.coupons.index') }}"
                               class="mb-0.5 mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.coupons') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z"/></svg>
                                Coupons
                            </a>

                            <a href="{{ route('admin.bundle-discounts.index') }}"
                               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.bundle-discounts') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 1 0 9.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 1 1 14.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                                Bundle Deals
                            </a>

                            <a href="{{ route('admin.regional-discounts.index') }}"
                               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.regional-discounts') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5a17.92 17.92 0 0 1-8.716-2.247m0 0A8.966 8.966 0 0 1 3 12c0-1.264.26-2.467.732-3.559"/></svg>
                                Regional Pricing
                            </a>
                        </div>
                    </div>

                    {{-- Section: Configuration --}}
                    <div x-data="{ open: {{ $inConfig ? 'true' : 'false' }} }" class="mt-4">
                        <button @click="open = !open" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wider text-stone-500 transition hover:text-stone-300">
                            <span>Config</span>
                            <svg class="h-3.5 w-3.5 transition-transform" :class="open && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </button>
                        <div x-show="open" x-transition.origin.top>
                            <a href="{{ route('admin.maintenance.integrations.edit') }}"
                               class="mb-0.5 mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.maintenance.integrations') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244"/></svg>
                                Integrations
                            </a>

                            <a href="{{ route('admin.maintenance.abandoned-cart.edit') }}"
                               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.maintenance.abandoned-cart') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                                Cart Recovery
                            </a>

                            <a href="{{ route('admin.url-redirects.index') }}"
                               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.url-redirects') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                                URL Redirects
                            </a>
                        </div>
                    </div>

                    {{-- Section: System --}}
                    <div x-data="{ open: {{ $inSystem ? 'true' : 'false' }} }" class="mt-4">
                        <button @click="open = !open" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wider text-stone-500 transition hover:text-stone-300">
                            <span>System</span>
                            <svg class="h-3.5 w-3.5 transition-transform" :class="open && 'rotate-90'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </button>
                        <div x-show="open" x-transition.origin.top>
                            <a href="{{ route('admin.activity-log.index') }}"
                               class="mb-0.5 mt-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.activity-log') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                Activity Log
                            </a>

                            <a href="{{ route('admin.maintenance.fallback-media.index') }}"
                               class="mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition {{ str_starts_with($currentRoute, 'admin.maintenance.fallback-media') ? 'bg-amber-600/20 text-amber-300' : 'hover:bg-stone-800 hover:text-white' }}">
                                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                                Media Maintenance
                            </a>

                            <div class="mt-2 flex items-center gap-2 px-3">
                                <form method="POST" action="{{ route('admin.maintenance.clear-caches') }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-stone-700 px-3 py-2 text-xs text-stone-400 transition hover:border-stone-500 hover:text-white">Clear Caches</button>
                                </form>
                                <form method="POST" action="{{ route('admin.maintenance.clear-logs') }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-stone-700 px-3 py-2 text-xs text-stone-400 transition hover:border-stone-500 hover:text-white" onclick="return confirm('Clear all application log files?')">Clear Logs</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </nav>

                {{-- Sidebar footer --}}
                <div class="border-t border-stone-700 px-3 py-3">
                    <a href="{{ url('/') }}" target="_blank"
                       class="mb-1 flex items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition hover:bg-stone-800 hover:text-white">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        View Store
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-[15px] font-medium transition hover:bg-stone-800 hover:text-white">
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                            Log Out
                        </button>
                    </form>
                </div>
            </aside>

            {{-- Sidebar overlay (mobile) --}}
            <div id="admin-sidebar-overlay" class="fixed inset-0 z-30 hidden bg-black/50 lg:hidden" onclick="document.getElementById('admin-sidebar').classList.add('-translate-x-full'); this.classList.add('hidden');"></div>

            {{-- Main content --}}
            <div class="flex flex-1 flex-col lg:ml-60">
                {{-- Top bar (mobile toggle + breadcrumb) --}}
                <header class="sticky top-0 z-20 flex items-center gap-4 border-b border-stone-200 bg-white/80 px-6 py-3.5 shadow-sm backdrop-blur-sm">
                    <button type="button" class="rounded-lg p-2.5 text-stone-600 hover:bg-stone-100 lg:hidden" aria-label="Open menu"
                            onclick="document.getElementById('admin-sidebar').classList.remove('-translate-x-full'); document.getElementById('admin-sidebar-overlay').classList.remove('hidden');">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    </button>
                    <h1 class="text-xl font-semibold text-stone-800">{{ $title ?? 'Admin' }}</h1>
                </header>

                {{-- Page content --}}
                <main class="flex-1 p-6">
                    @if (session('status'))
                        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 animate-fade-in">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 animate-fade-in">
                            <ul class="list-disc pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>

        @livewireScripts
        @stack('scripts')
    </body>
</html>
