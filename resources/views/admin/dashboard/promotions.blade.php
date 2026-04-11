{{-- Discount Margin Impact --}}
<div class="admin-card admin-card-hover rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="1">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Discount Margin Impact ({{ $periodLabel }})</h3>
    <div class="flex items-baseline gap-3">
        <p class="text-3xl font-bold text-stone-800">{{ $discountImpact['discount_pct'] }}%</p>
        @if (isset($deltas['discount_pct']) && $deltas['discount_pct'] !== null)
            <span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $deltas['discount_pct'] > 0 ? 'bg-[#ff6384]/15 text-[#ff6384]' : ($deltas['discount_pct'] < 0 ? 'bg-[#4bc0c0]/15 text-[#4bc0c0]' : 'bg-stone-100 text-stone-500') }}">
                {{ $deltas['discount_pct'] > 0 ? '+' : '' }}{{ $deltas['discount_pct'] }}%
            </span>
        @endif
        <p class="text-[15px] text-stone-500">of gross revenue goes to discounts</p>
    </div>
    <p class="mt-2 text-sm text-stone-400">
        &euro;{{ number_format($discountImpact['total_discounts'], 2) }} discounted out of &euro;{{ number_format($discountImpact['total_gross'], 2) }} gross
    </p>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Coupon Leaderboard --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="2">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Coupon Leaderboard</h3>
        @if (empty($couponLeaderboard))
            <p class="text-[15px] text-stone-500">No coupon usage data.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200 text-[15px]">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-medium text-stone-600">Code</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Uses</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Total Discounted</th>
                            <th class="px-4 py-2.5 text-right font-medium text-stone-600">Avg Discount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($couponLeaderboard as $coupon)
                            <tr class="transition hover:bg-stone-50">
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs font-medium text-stone-700">{{ $coupon['code'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">{{ $coupon['times_used'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-700">&euro;{{ number_format($coupon['total_discounted'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-stone-500">&euro;{{ number_format($coupon['avg_discount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Cart Recovery Funnel --}}
    <div class="admin-card rounded-xl border border-stone-200 bg-white p-5 shadow-sm" data-delay="3">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-stone-600">Cart Recovery</h3>
        <div class="space-y-3">
            @php
                $funnel = [
                    ['label' => 'Abandoned', 'value' => $cartRecovery['abandoned'], 'color' => 'bg-red-50 text-red-700 border-red-200'],
                    ['label' => 'Reminded', 'value' => $cartRecovery['reminded'], 'color' => 'bg-amber-50 text-amber-700 border-amber-200'],
                    ['label' => 'Recovered', 'value' => $cartRecovery['recovered'], 'color' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                ];
            @endphp
            @foreach ($funnel as $step)
                <div class="flex items-center justify-between rounded-xl border {{ $step['color'] }} p-4 transition hover:shadow-sm">
                    <span class="text-[15px] font-medium">{{ $step['label'] }}</span>
                    <span class="text-xl font-bold">{{ $step['value'] }}</span>
                </div>
            @endforeach
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-center">
                <p class="text-sm font-medium text-blue-600">Recovery Rate</p>
                <div class="flex items-center justify-center gap-2">
                    <p class="text-3xl font-bold text-blue-700">{{ $cartRecovery['recovery_pct'] }}%</p>
                    @if (isset($deltas['recovery_pct']) && $deltas['recovery_pct'] !== null)
                        <span class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $deltas['recovery_pct'] > 0 ? 'bg-[#4bc0c0]/15 text-[#4bc0c0]' : ($deltas['recovery_pct'] < 0 ? 'bg-[#ff6384]/15 text-[#ff6384]' : 'bg-stone-100 text-stone-500') }}">
                            {{ $deltas['recovery_pct'] > 0 ? '+' : '' }}{{ $deltas['recovery_pct'] }}%
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
