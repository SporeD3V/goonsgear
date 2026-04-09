<div class="bg-black px-6 py-16 lg:py-20">
    <div class="mx-auto max-w-6xl">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
            {{-- Left: headline + copy --}}
            <div class="text-center lg:text-left">
                <h2 class="text-3xl font-black uppercase tracking-tight text-white md:text-4xl lg:text-5xl">Don't Miss<br class="hidden lg:inline"> Exclusive Drops</h2>
                <p class="mt-4 text-base leading-relaxed text-white/60 lg:text-lg">
                    Sign up for our newsletter for special offers and limited releases. Unsubscribe anytime with one click.
                </p>
                <p class="mt-4 text-xs tracking-wide text-white/45">Newsletter sent once a week maximum. We don't spam.</p>
            </div>

            {{-- Right: form --}}
            <div>
                @if ($subscribed)
                    <div
                        x-data="{ show: false }"
                        x-init="$nextTick(() => show = true)"
                        x-show="show"
                        x-transition:enter="transition ease-out duration-500"
                        x-transition:enter-start="opacity-0 translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="rounded-xl border border-white/10 bg-white/5 p-8 text-center backdrop-blur-sm"
                    >
                        <svg class="mx-auto mb-4 h-10 w-10 text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                        <p class="text-lg font-bold text-white">You're in!</p>
                        <p class="mt-1 text-sm text-white/60">Watch your inbox for exclusive drops.</p>
                    </div>
                @else
                    <form wire:submit="subscribe" class="space-y-4">
                        <div>
                            <input
                                type="text"
                                wire:model="name"
                                placeholder="Your Name"
                                class="w-full rounded-xl border-2 border-white/10 bg-white/10 px-5 py-4 text-base font-medium text-white backdrop-blur-sm placeholder-white/45 transition-all duration-200 focus:border-white/40 focus:bg-white/15 focus:outline-none focus:ring-0"
                            >
                            @error('name')
                                <p class="mt-1.5 text-left text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <input
                                type="email"
                                wire:model="email"
                                placeholder="Your Email Address"
                                class="w-full rounded-xl border-2 border-white/10 bg-white/10 px-5 py-4 text-base font-medium text-white backdrop-blur-sm placeholder-white/45 transition-all duration-200 focus:border-white/40 focus:bg-white/15 focus:outline-none focus:ring-0"
                            >
                            @error('email')
                                <p class="mt-1.5 text-left text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($errorMessage)
                            <p class="text-sm text-red-400">{{ $errorMessage }}</p>
                        @endif

                        <button
                            type="submit"
                            class="w-full rounded-xl border-2 border-white bg-white px-6 py-4 text-sm font-bold uppercase tracking-widest text-black transition-all duration-200 hover:bg-transparent hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                            wire:loading.attr="disabled"
                        >
                            <span wire:loading.remove wire:target="subscribe">Subscribe</span>
                            <span wire:loading wire:target="subscribe" class="flex items-center justify-center gap-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Subscribing…
                            </span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
