{{-- Discount Margin Impact --}}
<div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Discount Margin Impact</h3>
    <div class="flex items-baseline gap-3">
        <p class="text-3xl font-bold text-slate-900">{{ $discountImpact['discount_pct'] }}%</p>
        <p class="text-sm text-slate-500">of gross revenue goes to discounts</p>
    </div>
    <p class="mt-2 text-sm text-slate-400">
        &euro;{{ number_format($discountImpact['total_discounts'], 2) }} discounted out of &euro;{{ number_format($discountImpact['total_gross'], 2) }} gross
    </p>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    {{-- Coupon Leaderboard --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Coupon Leaderboard</h3>
        @if (empty($couponLeaderboard))
            <p class="text-sm text-slate-500">No coupon usage data.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-slate-600">Code</th>
                            <th class="px-4 py-2 text-right font-medium text-slate-600">Uses</th>
                            <th class="px-4 py-2 text-right font-medium text-slate-600">Total Discounted</th>
                            <th class="px-4 py-2 text-right font-medium text-slate-600">Avg Discount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($couponLeaderboard as $coupon)
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-2 font-mono text-xs font-medium text-slate-700">{{ $coupon['code'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-slate-700">{{ $coupon['times_used'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-slate-700">&euro;{{ number_format($coupon['total_discounted'], 2) }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-slate-500">&euro;{{ number_format($coupon['avg_discount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Cart Recovery Funnel --}}
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700">Cart Recovery</h3>
        <div class="space-y-4">
            @php
                $funnel = [
                    ['label' => 'Abandoned', 'value' => $cartRecovery['abandoned'], 'color' => 'bg-red-100 text-red-700 border-red-200'],
                    ['label' => 'Reminded', 'value' => $cartRecovery['reminded'], 'color' => 'bg-amber-100 text-amber-700 border-amber-200'],
                    ['label' => 'Recovered', 'value' => $cartRecovery['recovered'], 'color' => 'bg-emerald-100 text-emerald-700 border-emerald-200'],
                ];
            @endphp
            @foreach ($funnel as $step)
                <div class="flex items-center justify-between rounded-lg border {{ $step['color'] }} p-3">
                    <span class="text-sm font-medium">{{ $step['label'] }}</span>
                    <span class="text-lg font-bold">{{ $step['value'] }}</span>
                </div>
            @endforeach
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-center">
                <p class="text-sm font-medium text-blue-600">Recovery Rate</p>
                <p class="text-2xl font-bold text-blue-700">{{ $cartRecovery['recovery_pct'] }}%</p>
            </div>
        </div>
    </div>
</div>
