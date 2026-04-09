<div class="relative overflow-hidden bg-black px-6 py-16 lg:py-20 shadow-[0_30px_80px_-10px_rgba(0,0,0,0.5)]">
    {{-- Dripping paint animation for "Drops" — nod to the SnowGoons dripping snowflake --}}
    <style>
        .drip-word {
            position: relative;
            display: inline-block;
        }

        /*
         * Each .paint-drip is a single element with:
         *   ::before = accumulation bulge at the letter base (swells then shrinks)
         *   itself   = the drip body (rounded head via border-radius + tapered tail via clip-path)
         *   The drip extends downward with ease-out (decelerates like drying paint)
         *   It does NOT disappear — it freezes at final length, then fades very slowly.
         */
        .drip-word .paint-drip {
            position: absolute;
            top: 85%;
            pointer-events: none;
            /* Pill shape that's wider at top (head) and narrower at bottom (tail) */
            width: var(--drip-head, 5px);
            height: 0;
            background: linear-gradient(to bottom,
                rgba(255,255,255,0.92) 0%,
                rgba(255,255,255,0.8) 15%,
                rgba(255,255,255,0.55) 50%,
                rgba(255,255,255,0.3) 85%,
                rgba(255,255,255,0.1) 100%);
            border-radius: 40% 40% 50% 50% / 10% 10% 45% 45%;
            clip-path: polygon(
                15% 0%, 85% 0%,          /* wide head */
                70% 50%,                  /* narrows */
                60% 85%,                  /* thin tail */
                50% 100%,                 /* tip */
                40% 85%,
                30% 50%
            );
            /* ease-out = fast start, decelerates = paint running then drying */
            animation: drip-pour var(--drip-dur) var(--drip-del) cubic-bezier(0.16, 1, 0.3, 1) infinite;
        }

        /* Accumulation bulge — swells at the letter's base before drip starts */
        .drip-word .paint-drip::before {
            content: '';
            position: absolute;
            top: -3px;
            left: 50%;
            transform: translateX(-50%) scale(0);
            width: calc(var(--drip-head, 5px) + 3px);
            height: calc(var(--drip-head, 5px) * 0.55 + 2px);
            background: radial-gradient(ellipse at 50% 60%,
                rgba(255,255,255,0.95) 0%,
                rgba(255,255,255,0.7) 50%,
                rgba(255,255,255,0.3) 80%,
                transparent 100%);
            border-radius: 50% 50% 45% 45%;
            animation: bulge-swell var(--drip-dur) var(--drip-del) ease-in-out infinite;
        }

        /* Phase 1: Accumulation — bulge swells, holds, then shrinks as paint drains into drip */
        @keyframes bulge-swell {
            0%        { transform: translateX(-50%) scale(0);    opacity: 0; }
            /* swell up */
            4%        { transform: translateX(-50%) scale(0.6);  opacity: 0.7; }
            8%        { transform: translateX(-50%) scale(1);    opacity: 0.9; }
            /* hold — paint accumulates */
            14%       { transform: translateX(-50%) scale(1.1);  opacity: 0.85; }
            /* shrink as paint flows into the drip strand */
            22%       { transform: translateX(-50%) scale(0.5);  opacity: 0.5; }
            28%       { transform: translateX(-50%) scale(0);    opacity: 0; }
            100%      { transform: translateX(-50%) scale(0);    opacity: 0; }
        }

        /*
         * Phase 2 & 3: Pour + Freeze
         * Drip extends with deceleration (ease-out via cubic-bezier on anim).
         * Reaches final length, holds (frozen paint), then slowly fades.
         */
        @keyframes drip-pour {
            0%        { height: 0;                     opacity: 0; }
            /* starts just as bulge begins shrinking */
            10%       { height: 0;                     opacity: 0; }
            12%       { height: 3px;                   opacity: 0.9; }
            /* fast pour with deceleration baked into the curve */
            30%       { height: calc(var(--drip-len, 30px) * 0.7); opacity: 0.85; }
            /* slows down... almost frozen */
            42%       { height: calc(var(--drip-len, 30px) * 0.92); opacity: 0.8; }
            /* fully frozen at final length */
            50%       { height: var(--drip-len, 30px); opacity: 0.75; }
            /* paint sits there drying */
            70%       { height: var(--drip-len, 30px); opacity: 0.5; }
            /* slowly evaporates */
            85%       { height: var(--drip-len, 30px); opacity: 0.15; }
            90%       { height: var(--drip-len, 30px); opacity: 0; }
            100%      { height: var(--drip-len, 30px); opacity: 0; }
        }
    </style>
    <div class="pointer-events-none absolute inset-x-0 top-0 z-0 h-32 bg-gradient-to-b from-neutral-700/40 to-transparent"></div>
    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-0 h-32 bg-gradient-to-t from-neutral-700/40 to-transparent"></div>
    <div class="relative z-[1] mx-auto max-w-6xl">
        <div class="grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
            {{-- Left: headline + copy --}}
            <div class="text-center lg:text-left">
                <h2 class="text-3xl font-black uppercase tracking-wide text-white md:text-4xl lg:text-5xl">Don't Miss<br class="hidden lg:inline"> Exclusive <span class="drip-word">Drops{{--
                    Per-drip: head width, final length, cycle duration, start delay.
                    Coprime durations + spread delays = never same phase.
                    D
                    --}}<span class="paint-drip" style="left:5%;  --drip-head:5px; --drip-len:32px; --drip-dur:11s; --drip-del:0s"></span><span class="paint-drip" style="left:14%; --drip-head:4px; --drip-len:18px; --drip-dur:17s; --drip-del:5s"></span>{{--
                    R
                    --}}<span class="paint-drip" style="left:28%; --drip-head:6px; --drip-len:40px; --drip-dur:13s; --drip-del:2s"></span>{{--
                    O
                    --}}<span class="paint-drip" style="left:44%; --drip-head:4px; --drip-len:14px; --drip-dur:19s; --drip-del:8s"></span><span class="paint-drip" style="left:54%; --drip-head:5px; --drip-len:28px; --drip-dur:15s; --drip-del:11s"></span>{{--
                    P
                    --}}<span class="paint-drip" style="left:68%; --drip-head:6px; --drip-len:36px; --drip-dur:12s; --drip-del:4s"></span>{{--
                    S
                    --}}<span class="paint-drip" style="left:84%; --drip-head:4px; --drip-len:20px; --drip-dur:23s; --drip-del:14s"></span><span class="paint-drip" style="left:91%; --drip-head:7px; --drip-len:44px; --drip-dur:14s; --drip-del:7s"></span></span></h2>
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
