<div class="relative overflow-hidden bg-black px-6 py-16 lg:py-20 shadow-[0_30px_80px_-10px_rgba(0,0,0,0.5)]">
    {{-- Dripping paint animation for "Drops" — nod to the SnowGoons dripping snowflake --}}
    <style>
        .drip-word {
            position: relative;
            display: inline-block;
            padding-bottom: 0.15em;
        }

        /* Top bulge — paint pooling at the letter before running down */
        .drip-word .paint-strand::before {
            content: '';
            position: absolute;
            top: -2px;
            left: 50%;
            transform: translateX(-50%);
            width: var(--bulge-top, 6px);
            height: calc(var(--bulge-top, 6px) * 0.65);
            background: radial-gradient(ellipse at center, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.7) 60%, transparent 100%);
            border-radius: 50% 50% 35% 35%;
            opacity: 0;
            animation: bulge-top var(--strand-dur, 10s) var(--strand-del, 0s) ease-in-out infinite;
        }

        /* The thin strand that runs between the two bulges */
        .drip-word .paint-strand {
            position: absolute;
            top: 88%;
            width: var(--strand-w, 2px);
            height: 0;
            background: linear-gradient(to bottom,
                rgba(255,255,255,0.85) 0%,
                rgba(255,255,255,0.65) 40%,
                rgba(255,255,255,0.45) 80%,
                rgba(255,255,255,0.3) 100%);
            border-radius: 1px;
            pointer-events: none;
            animation: strand-pour var(--strand-dur, 10s) var(--strand-del, 0s) ease-in-out infinite;
        }

        /* Bottom droplet — teardrop that swells then detaches and falls */
        .drip-word .paint-strand::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 50%;
            transform: translateX(-50%) scaleY(1);
            width: var(--bulge-bot, 5px);
            height: calc(var(--bulge-bot, 5px) * 1.3);
            background: radial-gradient(ellipse at 50% 40%, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.7) 50%, rgba(255,255,255,0.4) 100%);
            border-radius: 40% 40% 50% 50% / 30% 30% 60% 60%;
            opacity: 0;
            animation: droplet-swell-fall var(--strand-dur, 10s) var(--strand-del, 0s) ease-in infinite;
        }

        /* Top bulge fades in, holds while strand pours, then fades — visible ~2-18% */
        @keyframes bulge-top {
            0%        { opacity: 0; }
            2%        { opacity: 0.85; }
            12%       { opacity: 0.7; }
            17%       { opacity: 0.3; }
            20%       { opacity: 0; }
            100%      { opacity: 0; }
        }

        /* Strand grows slowly downward, holds, then fades — visible ~2-22% */
        @keyframes strand-pour {
            0%        { height: 0;                     opacity: 0; }
            2%        { height: 0;                     opacity: 0.85; }
            12%       { height: var(--strand-h, 30px); opacity: 0.8; }
            18%       { height: var(--strand-h, 30px); opacity: 0.5; }
            22%       { height: var(--strand-h, 30px); opacity: 0; }
            100%      { height: var(--strand-h, 30px); opacity: 0; }
        }

        /* Droplet swells at strand tip, then detaches and falls — visible ~8-25% */
        @keyframes droplet-swell-fall {
            0%, 8%    { bottom: -1px;  opacity: 0;    transform: translateX(-50%) scaleY(1) scaleX(0.8); }
            10%       { bottom: -1px;  opacity: 0.3;  transform: translateX(-50%) scaleY(1) scaleX(0.9); }
            14%       { bottom: -1px;  opacity: 0.9;  transform: translateX(-50%) scaleY(1) scaleX(1); }
            17%       { bottom: -2px;  opacity: 0.95; transform: translateX(-50%) scaleY(1.15) scaleX(1); }
            /* detach */
            19%       { bottom: -4px;  opacity: 0.9;  transform: translateX(-50%) scaleY(1.3) scaleX(0.9); }
            23%       { bottom: -35px; opacity: 0.6;  transform: translateX(-50%) scaleY(1.4) scaleX(0.85); }
            28%       { bottom: -70px; opacity: 0;    transform: translateX(-50%) scaleY(1.5) scaleX(0.8); }
            100%      { bottom: -70px; opacity: 0;    transform: translateX(-50%) scaleY(1.5) scaleX(0.8); }
        }
    </style>
    <div class="pointer-events-none absolute inset-x-0 top-0 z-0 h-32 bg-gradient-to-b from-neutral-700/40 to-transparent"></div>
    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-0 h-32 bg-gradient-to-t from-neutral-700/40 to-transparent"></div>
    <div class="relative z-[1] mx-auto max-w-6xl">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
            {{-- Left: headline + copy --}}
            <div class="text-center lg:text-left">
                <h2 class="text-3xl font-black uppercase tracking-wide text-white md:text-4xl lg:text-5xl">Don't Miss<br class="hidden lg:inline"> Exclusive <span class="drip-word">Drops{{--
                    Each drip has a unique prime-ish duration so they never sync phases.
                    D drips
                    --}}<span class="paint-strand" style="left:4%;  --strand-w:2px; --strand-h:28px; --bulge-top:7px; --bulge-bot:6px; --strand-dur:11s;  --strand-del:0s"></span><span class="paint-strand" style="left:13%; --strand-w:2px; --strand-h:18px; --bulge-top:5px; --bulge-bot:4px; --strand-dur:17s;  --strand-del:4s"></span>{{--
                    R drip
                    --}}<span class="paint-strand" style="left:27%; --strand-w:2px; --strand-h:34px; --bulge-top:8px; --bulge-bot:7px; --strand-dur:13s;  --strand-del:2s"></span>{{--
                    O drips
                    --}}<span class="paint-strand" style="left:43%; --strand-w:2px; --strand-h:16px; --bulge-top:5px; --bulge-bot:4px; --strand-dur:19s;  --strand-del:7s"></span><span class="paint-strand" style="left:53%; --strand-w:2px; --strand-h:26px; --bulge-top:6px; --bulge-bot:5px; --strand-dur:14s;  --strand-del:1s"></span>{{--
                    P drip
                    --}}<span class="paint-strand" style="left:67%; --strand-w:2px; --strand-h:30px; --bulge-top:7px; --bulge-bot:6px; --strand-dur:16s;  --strand-del:5s"></span>{{--
                    S drips
                    --}}<span class="paint-strand" style="left:83%; --strand-w:2px; --strand-h:20px; --bulge-top:5px; --bulge-bot:5px; --strand-dur:23s;  --strand-del:9s"></span><span class="paint-strand" style="left:94%; --strand-w:2px; --strand-h:36px; --bulge-top:9px; --bulge-bot:8px; --strand-dur:12s;  --strand-del:3s"></span></span></h2>
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
                        <svg class="mx-auto mb-4 h-10 w-10 text-white/60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
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
                                <p class="mt-1.5 text-left text-xs text-white/60">{{ $message }}</p>
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
                                <p class="mt-1.5 text-left text-xs text-white/60">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($errorMessage)
                            <p class="text-sm text-white/60">{{ $errorMessage }}</p>
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
</div>
