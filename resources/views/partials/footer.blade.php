@php
    $light = $light ?? false;
@endphp

<footer class="{{ $light ? 'border-t border-slate-200 bg-white text-slate-500' : 'border-t border-slate-800 bg-black text-slate-400' }}">
    <div class="mx-auto max-w-6xl px-6 pb-10 pt-16">
        {{-- Top: Logo + Social --}}
        <div class="flex flex-col items-center gap-8 sm:flex-row sm:items-start sm:justify-between">
            {{-- Logo --}}
            <a href="{{ url('/') }}" class="shrink-0 transition-opacity duration-200 hover:opacity-70">
                <picture>
                    <source srcset="{{ asset('images/goonsgear-shop-by-snowgoons-logo.avif') }}" type="image/avif">
                    <img
                        src="{{ asset('images/goonsgear-shop-by-snowgoons-logo.png') }}"
                        alt="GoonsGear"
                        class="h-12 w-auto {{ $light ? '' : 'brightness-0 invert' }}"
                        width="168"
                        height="140"
                        loading="lazy"
                    >
                </picture>
            </a>

            {{-- Social Icons --}}
            <div class="flex items-center gap-4">
                <a href="https://x.com/goonsgeardotcom" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)" class="rounded-lg p-2.5 {{ $light ? 'text-slate-400 transition-all duration-200 hover:bg-slate-100 hover:text-black' : 'text-slate-500 transition-all duration-200 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                    </svg>
                </a>
                <a href="https://www.facebook.com/GoonsGear" target="_blank" rel="noopener noreferrer" aria-label="Facebook" class="rounded-lg p-2.5 {{ $light ? 'text-slate-400 transition-all duration-200 hover:bg-slate-100 hover:text-black' : 'text-slate-500 transition-all duration-200 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/>
                    </svg>
                </a>
                <a href="https://www.instagram.com/goonsgear" target="_blank" rel="noopener noreferrer" aria-label="Instagram" class="rounded-lg p-2.5 {{ $light ? 'text-slate-400 transition-all duration-200 hover:bg-slate-100 hover:text-black' : 'text-slate-500 transition-all duration-200 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd"/>
                    </svg>
                </a>
                <a href="https://www.youtube.com/user/Snowgoons" target="_blank" rel="noopener noreferrer" aria-label="YouTube" class="rounded-lg p-2.5 {{ $light ? 'text-slate-400 transition-all duration-200 hover:bg-slate-100 hover:text-black' : 'text-slate-500 transition-all duration-200 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill-rule="evenodd" d="M19.812 5.418c.861.23 1.538.907 1.768 1.768C21.998 8.746 22 12 22 12s0 3.255-.418 4.814a2.504 2.504 0 01-1.768 1.768C18.254 19 12 19 12 19s-6.254 0-7.814-.418a2.505 2.505 0 01-1.768-1.768C2 15.255 2 12 2 12s0-3.255.418-4.814a2.507 2.507 0 011.768-1.768C5.746 5 12 5 12 5s6.254 0 7.812.418zM15.194 12L10 15V9l5.194 3z" clip-rule="evenodd"/>
                    </svg>
                </a>
                <a href="#" aria-label="Pinterest" class="rounded-lg p-2.5 {{ $light ? 'text-slate-400 transition-all duration-200 hover:bg-slate-100 hover:text-black' : 'text-slate-500 transition-all duration-200 hover:bg-white/10 hover:text-white' }}">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.462-6.233 7.462-1.214 0-2.354-.63-2.748-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/>
                    </svg>
                </a>
            </div>
        </div>

        {{-- Divider --}}
        <div class="my-8 border-t {{ $light ? 'border-slate-200' : 'border-slate-800' }}"></div>

        {{-- Link columns + Instagram --}}
        <div class="grid gap-8 text-center sm:grid-cols-2 sm:text-left lg:grid-cols-4">
            <div>
                <h3 class="mb-4 text-xs font-bold uppercase tracking-widest {{ $light ? 'text-black' : 'text-white' }}">Info &amp; Legal</h3>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ url('/about') }}" class="transition-colors duration-200 {{ $light ? 'hover:text-black' : 'hover:text-white' }}">About Us</a></li>
                    <li><a href="{{ url('/privacy-policy') }}" class="transition-colors duration-200 {{ $light ? 'hover:text-black' : 'hover:text-white' }}">Privacy Policy</a></li>
                    <li><a href="{{ url('/terms-of-service') }}" class="transition-colors duration-200 {{ $light ? 'hover:text-black' : 'hover:text-white' }}">Terms of Service</a></li>
                </ul>
            </div>

            <div>
                <h3 class="mb-4 text-xs font-bold uppercase tracking-widest {{ $light ? 'text-black' : 'text-white' }}">Client Service</h3>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ url('/returns') }}" class="transition-colors duration-200 {{ $light ? 'hover:text-black' : 'hover:text-white' }}">Returns &amp; Exchanges</a></li>
                    <li><a href="{{ url('/shipping') }}" class="transition-colors duration-200 {{ $light ? 'hover:text-black' : 'hover:text-white' }}">Shipping Info</a></li>
                    <li><a href="{{ url('/contact') }}" class="transition-colors duration-200 {{ $light ? 'hover:text-black' : 'hover:text-white' }}">Contact Us</a></li>
                </ul>
            </div>

            <div>
                <h3 class="mb-4 text-xs font-bold uppercase tracking-widest {{ $light ? 'text-black' : 'text-white' }}">Sitemap</h3>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ url('/sitemap.xml') }}" class="transition-colors duration-200 {{ $light ? 'hover:text-black' : 'hover:text-white' }}">Sitemap</a></li>
                </ul>
            </div>

            {{-- Instagram Feed --}}
            <div>
                <h3 class="mb-4 text-xs font-bold uppercase tracking-widest {{ $light ? 'text-black' : 'text-white' }}">
                    <a href="https://www.instagram.com/goonsgear" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 transition-opacity duration-200 hover:opacity-70">
                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd"/></svg>
                        @goonsgear
                    </a>
                </h3>
                <div class="grid grid-cols-3 gap-1.5">
                    @for ($i = 0; $i < 6; $i++)
                        <a href="https://www.instagram.com/goonsgear" target="_blank" rel="noopener noreferrer" class="group relative aspect-square overflow-hidden rounded-lg {{ $light ? 'bg-slate-100' : 'bg-white/5' }}">
                            <div class="flex h-full w-full items-center justify-center">
                                <svg class="h-5 w-5 {{ $light ? 'text-slate-300' : 'text-white/10' }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z" clip-rule="evenodd"/></svg>
                            </div>
                            <div class="absolute inset-0 flex items-center justify-center bg-black/0 transition-all duration-200 group-hover:bg-black/50">
                                <svg class="h-5 w-5 text-white opacity-0 transition-all duration-200 group-hover:opacity-100" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                            </div>
                        </a>
                    @endfor
                </div>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="mt-10 border-t {{ $light ? 'border-slate-200' : 'border-slate-800' }} pt-6 text-center text-xs">
            <p>&copy; {{ date('Y') }} GoonsGear. All rights reserved.</p>
            <p class="mt-1">Crafted by Hip-Hop heads for Hip-Hop heads by Mean Mugga CRU | <a href="https://macaw.studio" target="_blank" rel="noopener noreferrer" class="transition-colors duration-200 {{ $light ? 'hover:text-black' : 'hover:text-white' }}">Macaw Studio</a>.</p>
        </div>
</footer>
