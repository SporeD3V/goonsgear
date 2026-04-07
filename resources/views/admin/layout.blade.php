<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Admin' }} | GoonsGear</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="bg-slate-100 text-slate-900">
        <div class="flex min-h-screen">
            {{-- Sidebar --}}
            <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-60 -translate-x-full flex-col border-r border-slate-200 bg-slate-900 text-slate-300 transition-transform lg:translate-x-0">
                {{-- Logo --}}
                <div class="flex items-center gap-2 border-b border-slate-700 px-4 py-4">
                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    <span class="text-sm font-bold text-white">GoonsGear Admin</span>
                </div>

                {{-- Navigation --}}
                <nav class="flex-1 overflow-y-auto px-3 py-4 text-sm">
                    @php $currentRoute = Route::currentRouteName() ?? ''; @endphp

                    {{-- Orders --}}
                    <a href="{{ route('admin.orders.index') }}"
                       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2 transition {{ str_starts_with($currentRoute, 'admin.orders') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800 hover:text-white' }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/></svg>
                        Orders
                    </a>

                    {{-- Products --}}
                    <a href="{{ route('admin.products.index') }}"
                       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2 transition {{ str_starts_with($currentRoute, 'admin.products') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800 hover:text-white' }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25"/></svg>
                        Products
                    </a>

                    {{-- Categories --}}
                    <a href="{{ route('admin.categories.index') }}"
                       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2 transition {{ str_starts_with($currentRoute, 'admin.categories') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800 hover:text-white' }}">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z"/></svg>
                        Categories
                    </a>

                    {{-- Tags (collapsible) --}}
                    <div x-data="{ open: {{ str_starts_with($currentRoute, 'admin.tags') ? 'true' : 'false' }} }">
                        <button @click="open = !open" type="button"
                                class="mb-1 flex w-full items-center gap-3 rounded-md px-3 py-2 transition {{ str_starts_with($currentRoute, 'admin.tags') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800 hover:text-white' }}">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z"/></svg>
                            <span class="flex-1 text-left">Tags</span>
                            <svg class="h-3 w-3 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="ml-7 space-y-1 border-l border-slate-700 pl-3">
                            <a href="{{ route('admin.tags.index', ['type' => 'artist']) }}"
                               class="block rounded-md px-3 py-1.5 transition {{ $currentRoute === 'admin.tags.index' && request('type') === 'artist' ? 'text-white' : 'hover:text-white' }}">Artists</a>
                            <a href="{{ route('admin.tags.index', ['type' => 'brand']) }}"
                               class="block rounded-md px-3 py-1.5 transition {{ $currentRoute === 'admin.tags.index' && request('type') === 'brand' ? 'text-white' : 'hover:text-white' }}">Brands</a>
                            <a href="{{ route('admin.tags.index', ['type' => 'custom']) }}"
                               class="block rounded-md px-3 py-1.5 transition {{ $currentRoute === 'admin.tags.index' && request('type') === 'custom' ? 'text-white' : 'hover:text-white' }}">Custom Tags</a>
                        </div>
                    </div>

                    {{-- Divider --}}
                    <hr class="my-3 border-slate-700">

                    {{-- Discounts (collapsible) --}}
                    <div x-data="{ open: {{ str_starts_with($currentRoute, 'admin.coupons') || str_starts_with($currentRoute, 'admin.bundle-discounts') || str_starts_with($currentRoute, 'admin.regional-discounts') ? 'true' : 'false' }} }">
                        <button @click="open = !open" type="button"
                                class="mb-1 flex w-full items-center gap-3 rounded-md px-3 py-2 transition {{ str_starts_with($currentRoute, 'admin.coupons') || str_starts_with($currentRoute, 'admin.bundle-discounts') || str_starts_with($currentRoute, 'admin.regional-discounts') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800 hover:text-white' }}">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"/></svg>
                            <span class="flex-1 text-left">Discounts</span>
                            <svg class="h-3 w-3 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="ml-7 space-y-1 border-l border-slate-700 pl-3">
                            <a href="{{ route('admin.coupons.index') }}"
                               class="block rounded-md px-3 py-1.5 transition {{ str_starts_with($currentRoute, 'admin.coupons') ? 'text-white' : 'hover:text-white' }}">Coupons</a>
                            <a href="{{ route('admin.bundle-discounts.index') }}"
                               class="block rounded-md px-3 py-1.5 transition {{ str_starts_with($currentRoute, 'admin.bundle-discounts') ? 'text-white' : 'hover:text-white' }}">Bundle Discounts</a>
                            <a href="{{ route('admin.regional-discounts.index') }}"
                               class="block rounded-md px-3 py-1.5 transition {{ str_starts_with($currentRoute, 'admin.regional-discounts') ? 'text-white' : 'hover:text-white' }}">Regional Discounts</a>
                        </div>
                    </div>

                    {{-- Settings (collapsible) --}}
                    <div x-data="{ open: {{ str_starts_with($currentRoute, 'admin.maintenance.abandoned-cart') || str_starts_with($currentRoute, 'admin.maintenance.integrations') || str_starts_with($currentRoute, 'admin.url-redirects') ? 'true' : 'false' }} }">
                        <button @click="open = !open" type="button"
                                class="mb-1 flex w-full items-center gap-3 rounded-md px-3 py-2 transition {{ str_starts_with($currentRoute, 'admin.maintenance.abandoned-cart') || str_starts_with($currentRoute, 'admin.maintenance.integrations') || str_starts_with($currentRoute, 'admin.url-redirects') ? 'bg-slate-700 text-white' : 'hover:bg-slate-800 hover:text-white' }}">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                            <span class="flex-1 text-left">Settings</span>
                            <svg class="h-3 w-3 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="ml-7 space-y-1 border-l border-slate-700 pl-3">
                            <a href="{{ route('admin.maintenance.abandoned-cart.edit') }}"
                               class="block rounded-md px-3 py-1.5 transition {{ str_starts_with($currentRoute, 'admin.maintenance.abandoned-cart') ? 'text-white' : 'hover:text-white' }}">Cart Reminders</a>
                            <a href="{{ route('admin.maintenance.integrations.edit') }}"
                               class="block rounded-md px-3 py-1.5 transition {{ str_starts_with($currentRoute, 'admin.maintenance.integrations') ? 'text-white' : 'hover:text-white' }}">Integrations</a>
                            <a href="{{ route('admin.url-redirects.index') }}"
                               class="block rounded-md px-3 py-1.5 transition {{ str_starts_with($currentRoute, 'admin.url-redirects') ? 'text-white' : 'hover:text-white' }}">URL Redirects</a>
                        </div>
                    </div>

                    {{-- Utility (collapsible) --}}
                    <div x-data="{ open: {{ str_starts_with($currentRoute, 'admin.maintenance.fallback-media') || str_starts_with($currentRoute, 'admin.activity-log') ? 'true' : 'false' }} }">
                        <button @click="open = !open" type="button"
                                class="mb-1 flex w-full items-center gap-3 rounded-md px-3 py-2 transition hover:bg-slate-800 hover:text-white">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.049.58.025 1.193-.14 1.743"/></svg>
                            <span class="flex-1 text-left">Utility</span>
                            <svg class="h-3 w-3 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="ml-7 space-y-1 border-l border-slate-700 pl-3">
                            <form method="POST" action="{{ route('admin.maintenance.clear-caches') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-md px-3 py-1.5 text-left transition hover:text-white">Clear Caches</button>
                            </form>
                            <form method="POST" action="{{ route('admin.maintenance.clear-logs') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-md px-3 py-1.5 text-left transition hover:text-white" onclick="return confirm('Clear all application log files?')">Clear Logs</button>
                            </form>
                            <a href="{{ route('admin.maintenance.fallback-media.index') }}"
                               class="block rounded-md px-3 py-1.5 transition {{ str_starts_with($currentRoute, 'admin.maintenance.fallback-media') ? 'text-white' : 'hover:text-white' }}">Fallback Media</a>
                            <a href="{{ route('admin.activity-log.index') }}"
                               class="block rounded-md px-3 py-1.5 transition {{ str_starts_with($currentRoute, 'admin.activity-log') ? 'text-white' : 'hover:text-white' }}">Sync Log</a>
                        </div>
                    </div>
                </nav>

                {{-- Sidebar footer --}}
                <div class="border-t border-slate-700 px-3 py-3 text-sm">
                    <a href="{{ url('/') }}" target="_blank"
                       class="mb-1 flex items-center gap-3 rounded-md px-3 py-2 transition hover:bg-slate-800 hover:text-white">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        View Store
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex w-full items-center gap-3 rounded-md px-3 py-2 transition hover:bg-slate-800 hover:text-white">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
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
                <header class="sticky top-0 z-20 flex items-center gap-4 border-b border-slate-200 bg-white px-6 py-3 shadow-sm">
                    <button type="button" class="rounded p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open menu"
                            onclick="document.getElementById('admin-sidebar').classList.remove('-translate-x-full'); document.getElementById('admin-sidebar-overlay').classList.remove('hidden');">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                    </button>
                    <h1 class="text-lg font-semibold text-slate-900">{{ $title ?? 'Admin' }}</h1>
                </header>

                {{-- Page content --}}
                <main class="flex-1 p-6">
                    @if (session('status'))
                        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            <ul class="list-disc pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="rounded-lg bg-white p-6 shadow-sm">
                        @yield('content')
                    </div>
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
