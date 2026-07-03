@props(['histories'])

@if ($histories->isNotEmpty())
    <div class="mt-8 admin-card rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
        <h3 class="mb-3 text-sm font-semibold text-stone-700">Edit History</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-stone-200 text-xs">
                <thead class="bg-stone-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-stone-600">When</th>
                        <th class="px-3 py-2 text-left font-medium text-stone-600">User</th>
                        <th class="px-3 py-2 text-left font-medium text-stone-600">Field</th>
                        <th class="px-3 py-2 text-left font-medium text-stone-600">Old Value</th>
                        <th class="px-3 py-2 text-left font-medium text-stone-600">New Value</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($histories as $entry)
                        <tr class="hover:bg-stone-50">
                            <td class="whitespace-nowrap px-3 py-2 text-stone-500" title="{{ $entry->created_at->toDateTimeString() }}">
                                {{ $entry->created_at->diffForHumans() }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2">
                                {{ $entry->user?->name ?? $entry->user?->email ?? 'System' }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 font-medium text-stone-700">
                                {{ str_replace('_', ' ', ucfirst($entry->field)) }}
                            </td>
                            <td class="max-w-xs truncate px-3 py-2 text-red-600" title="{{ $entry->old_value }}">
                                {{ Str::limit($entry->old_value ?? '(empty)', 60) }}
                            </td>
                            <td class="max-w-xs truncate px-3 py-2 text-green-600" title="{{ $entry->new_value }}">
                                {{ Str::limit($entry->new_value ?? '(empty)', 60) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
