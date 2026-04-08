<div class="bg-black px-6 py-16 lg:py-20">
    <div class="mx-auto max-w-xl text-center">
        {{-- Envelope icon --}}
        <div class="mb-6 flex justify-center">
            <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
            </svg>
        </div>

        <h2 class="text-3xl font-black uppercase tracking-tight text-white md:text-4xl">Don't Miss Exclusive Drops</h2>
        <p class="mt-3 text-base leading-relaxed text-slate-400">
            Sign up for our newsletter for special offers and limited releases. Unsubscribe anytime with one click.
        </p>

        @if ($subscribed)
            <div
                x-data="{ show: false }"
                x-init="$nextTick(() => show = true)"
                x-show="show"
                x-transition:enter="transition ease-out duration-500"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="mt-8 rounded-lg border border-green-500/30 bg-green-500/10 px-6 py-4"
            >
                <p class="font-semibold text-green-400">You're in! Watch your inbox for exclusive drops.</p>
            </div>
        @else
            <form wire:submit="subscribe" class="mt-8 space-y-3">
                <div>
                    <input
                        type="text"
                        wire:model="name"
                        placeholder="Your Name"
                        class="w-full rounded-lg border border-[#242424] bg-[#242424] px-4 py-3 text-sm text-white placeholder-slate-500 transition-colors duration-200 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                    >
                    @error('name')
                        <p class="mt-1 text-left text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <input
                        type="email"
                        wire:model="email"
                        placeholder="Your Email Address"
                        class="w-full rounded-lg border border-[#242424] bg-[#242424] px-4 py-3 text-sm text-white placeholder-slate-500 transition-colors duration-200 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
                    >
                    @error('email')
                        <p class="mt-1 text-left text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                @if ($errorMessage)
                    <p class="text-sm text-red-400">{{ $errorMessage }}</p>
                @endif

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-lg border-2 border-white bg-white px-8 py-3 text-sm font-bold uppercase tracking-widest text-black transition-all duration-200 hover:bg-transparent hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="subscribe">Subscribe</span>
                    <span wire:loading wire:target="subscribe" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Subscribing…
                    </span>
                </button>
            </form>
        @endif

        <p class="mt-6 text-xs italic text-slate-500">Newsletter sent once a week maximum. We don't spam.</p>
    </div>
</div>
