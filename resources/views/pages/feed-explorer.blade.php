<x-filament-panels::page>
    <form wire:submit="analyze">
        {{ $this->form }}

        <div class="flex justify-end gap-2 mt-4">
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">
                {{ __('feedmanager::feedmanager.explorer.actions.analyze') }}
            </x-filament::button>
        </div>
    </form>

    @if ($error)
        <div class="mt-6 rounded-xl bg-danger-50 dark:bg-danger-950/20 p-4 ring-1 ring-danger-200 dark:ring-danger-800">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-x-circle" class="w-6 h-6 text-danger-500 shrink-0" />
                <div>
                    <h3 class="text-sm font-semibold text-danger-700 dark:text-danger-300">
                        {{ __('feedmanager::feedmanager.explorer.error_heading') }}
                    </h3>
                    <p class="mt-1 text-sm text-danger-700 dark:text-danger-200">{{ $error }}</p>
                </div>
            </div>
        </div>
    @endif

    @if ($result)
        @php
            $r = $result;
        @endphp

        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="rounded-xl bg-gray-50 dark:bg-gray-900 p-4 ring-1 ring-gray-200 dark:ring-gray-800">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('feedmanager::feedmanager.explorer.stats.products') }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format((int) $r['total_products'], 0, ',', ' ') }}</div>
            </div>
            <div class="rounded-xl bg-gray-50 dark:bg-gray-900 p-4 ring-1 ring-gray-200 dark:ring-gray-800">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('feedmanager::feedmanager.explorer.stats.size') }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($r['payload_bytes'] / 1024, 1, ',', ' ') }} kB</div>
            </div>
            <div class="rounded-xl bg-gray-50 dark:bg-gray-900 p-4 ring-1 ring-gray-200 dark:ring-gray-800">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('feedmanager::feedmanager.explorer.stats.download') }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ $r['download_ms'] !== null ? number_format($r['download_ms']) . ' ms' : '—' }}</div>
            </div>
            <div class="rounded-xl bg-gray-50 dark:bg-gray-900 p-4 ring-1 ring-gray-200 dark:ring-gray-800">
                <div class="text-xs uppercase tracking-wide text-gray-500">{{ __('feedmanager::feedmanager.explorer.stats.format') }}</div>
                <div class="mt-1 text-2xl font-semibold">{{ __('feedmanager::feedmanager.formats.' . $r['format']) }}</div>
            </div>
        </div>

        @if (! empty($r['issues']))
            <x-filament::section class="mt-6">
                <x-slot name="heading">{{ __('feedmanager::feedmanager.explorer.issues.heading') }}</x-slot>
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="pb-2">{{ __('feedmanager::feedmanager.explorer.issues.severity') }}</th>
                            <th class="pb-2">{{ __('feedmanager::feedmanager.explorer.issues.type') }}</th>
                            <th class="pb-2 text-right">{{ __('feedmanager::feedmanager.explorer.issues.count') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($r['issues'] as $issue)
                            <tr>
                                <td class="py-2">
                                    @if ($issue['severity'] === 'error')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-danger-100 dark:bg-danger-950 px-2 py-0.5 text-xs text-danger-700 dark:text-danger-300">{{ __('feedmanager::feedmanager.explorer.issues.error') }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-warning-100 dark:bg-warning-950 px-2 py-0.5 text-xs text-warning-700 dark:text-warning-300">{{ __('feedmanager::feedmanager.explorer.issues.warning') }}</span>
                                    @endif
                                </td>
                                <td class="py-2 font-mono text-xs">{{ __('feedmanager::feedmanager.explorer.issue_keys.' . $issue['key']) }}</td>
                                <td class="py-2 text-right">{{ number_format((int) $issue['count'], 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endif

        <x-filament::section class="mt-6">
            <x-slot name="heading">{{ __('feedmanager::feedmanager.explorer.fields.heading') }}</x-slot>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="pb-2">{{ __('feedmanager::feedmanager.explorer.fields.field') }}</th>
                        <th class="pb-2 text-right">{{ __('feedmanager::feedmanager.explorer.fields.filled') }}</th>
                        <th class="pb-2 text-right">{{ __('feedmanager::feedmanager.explorer.fields.percent') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($r['fields'] as $field)
                        <tr>
                            <td class="py-2 font-mono">{{ $field['name'] }}</td>
                            <td class="py-2 text-right">{{ number_format((int) $field['filled'], 0, ',', ' ') }}</td>
                            <td class="py-2 text-right">{{ number_format((float) $field['percent'], 1, ',', ' ') }} %</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>

        @if (! empty($r['samples']))
            <x-filament::section class="mt-6">
                <x-slot name="heading">{{ __('feedmanager::feedmanager.explorer.samples.heading') }}</x-slot>
                <div class="space-y-3">
                    @foreach ($r['samples'] as $sample)
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-900 p-3 text-xs ring-1 ring-gray-200 dark:ring-gray-800">
                            <div class="font-mono">{{ $sample['code'] ?? '—' }}</div>
                            <div class="mt-1 font-semibold">{{ $sample['name'] ?? '—' }}</div>
                            <div class="mt-1 text-gray-500">
                                {{ $sample['price_vat'] ? number_format((float) $sample['price_vat'], 2, ',', ' ') . ' ' . ($sample['currency'] ?? '') : '—' }}
                                @if (! empty($sample['stock_quantity'])) · {{ $sample['stock_quantity'] }} {{ __('feedmanager::feedmanager.explorer.samples.stock_unit') }} @endif
                                @if (! empty($sample['availability'])) · {{ $sample['availability'] }} @endif
                            </div>
                            @if (! empty($sample['category']))
                                <div class="mt-1 text-gray-400 text-[11px]">{{ $sample['category'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if (! empty($r['category_counts']))
            <x-filament::section class="mt-6">
                <x-slot name="heading">{{ __('feedmanager::feedmanager.explorer.categories.heading') }}</x-slot>
                <ul class="text-sm space-y-1">
                    @foreach ($r['category_counts'] as $cat)
                        <li class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-200">{{ $cat['path'] }}</span>
                            <span class="text-gray-400">{{ number_format((int) $cat['count'], 0, ',', ' ') }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
