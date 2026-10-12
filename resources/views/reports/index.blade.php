<x-layouts.app title="Reports">
    @php

        [$label, $icon, $about, $dated] = \App\Http\Controllers\ReportController::REPORTS[$type];
        $query = fn (array $extra = []) => array_filter(array_merge(['type' => $type, 'period' => $period, 'from' => $period === 'custom' ? $from->format('Y-m-d') : null, 'to' => $period === 'custom' ? $to->format('Y-m-d') : null], $extra));
        $cell = function ($value, string $format) {
            return match ($format) {
                'money' => $value ? money($value, false) : '—',
                'signed' => ($value < 0 ? '−' : '').money(abs($value), false),
                'int' => number_format((float) $value),
                'pct' => $value.'%',
                default => $value,
            };
        };
        $toneText = ['red' => 'text-red-fg', 'amber' => 'text-amber-fg', 'green' => 'text-green-fg', 'blue' => 'text-blue-fg'];
    @endphp

    <x-page-header title="Reports" :subtitle="$dated ? $from->format('j M Y').' – '.$to->format('j M Y') : 'As of '.today()->format('j M Y')">
        <x-slot:actions>
            <button type="button" class="btn btn-secondary" onclick="window.print()"><x-icon name="printer" /> Print</button>
            <a href="{{ route('reports.export', $query()) }}" class="btn btn-primary"><x-icon name="download" /> Export CSV</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-[230px_minmax(0,1fr)] gap-6">
        <nav class="no-print space-y-0.5 self-start">
            @foreach (\App\Http\Controllers\ReportController::REPORTS as $key => [$l, $i])
                <a href="{{ route('reports.index', array_filter(['type' => $key, 'period' => request('period'), 'from' => request('from'), 'to' => request('to')])) }}" @class(['nav-link', 'nav-active' => $type === $key])><x-icon :name="$i" />{{ $l }}</a>
            @endforeach
        </nav>

        <div class="min-w-0 space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-[17px] font-semibold">{{ $label }}</h2>
                    <p class="text-[13px] text-ink-2">{{ $about }}</p>
                </div>
                @if ($dated)
                    <form class="no-print flex flex-wrap items-center gap-2" x-data="{ period: '{{ $period }}' }">
                        <input type="hidden" name="type" value="{{ $type }}">
                        <select name="period" x-model="period" class="input !h-9 !w-auto" @change="period !== 'custom' && $el.form.submit()" aria-label="Period">
                            @foreach (\App\Http\Controllers\ReportController::PERIODS as $k => $l)
                                <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                        <template x-if="period === 'custom'">
                            <div class="flex items-center gap-2">
                                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="input !h-9 !w-auto" aria-label="From">
                                <span class="text-muted">to</span>
                                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="input !h-9 !w-auto" aria-label="To">
                                <button class="btn btn-secondary btn-sm">Apply</button>
                            </div>
                        </template>
                    </form>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                @foreach ($report['stats'] as [$statLabel, $value, $hint, $tone])
                    <div class="card px-[18px] pt-4 pb-[18px]">
                        <div class="text-[13px] font-medium text-ink-2">{{ $statLabel }}</div>
                        <div class="kpi-value mt-3 truncate">{{ $value }}</div>
                        <div @class(['mt-2 text-[12.5px] font-medium truncate', $toneText[$tone] ?? 'text-muted'])>{{ $hint }}</div>
                    </div>
                @endforeach
            </div>

            @if ($report['chart'] && count($report['rows']))
                <div class="card px-[18px] py-4">
                    <div class="h-64"><canvas data-chart="{{ json_encode($report['chart']) }}"></canvas></div>
                </div>
            @endif

            <div class="card overflow-x-auto">
                @if (count($report['rows']))
                    <table class="table">
                        <thead>
                            <tr>
                                @foreach ($report['columns'] as [$heading, $format])
                                    <th @class(['text-right' => ! in_array($format, ['text', 'mono'])])>{{ $heading }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['rows'] as $row)
                                <tr>
                                    @foreach ($report['columns'] as $key => [$heading, $format])
                                        @php $v = $row[$key] ?? null; @endphp
                                        @if ($format === 'meter')
                                            <td class="text-right">
                                                @if ($v === null)
                                                    <span class="text-muted">—</span>
                                                @else
                                                    <div class="inline-flex items-center gap-2">
                                                        <div class="bar w-20"><span @class(['bg-red-fg' => $v > 100, 'bg-amber-fg' => $v > 85 && $v <= 100, 'bg-accent' => $v <= 85]) style="width: {{ min(100, $v) }}%"></span></div>
                                                        <span @class(['tabular-nums w-12', 'text-red-fg font-medium' => $v > 100])>{{ $v }}%</span>
                                                    </div>
                                                @endif
                                            </td>
                                        @else
                                            <td @class([
                                                'text-right tabular-nums' => ! in_array($format, ['text', 'mono']),
                                                'font-mono text-[12.5px] text-ink-2' => $format === 'mono',
                                                'font-medium' => $loop->first && $format === 'text',
                                                'text-red-fg' => $format === 'signed' && $v < 0,
                                                'text-muted' => in_array($format, ['money', 'int']) && ! $v,
                                            ])>{{ $cell($v, $format) }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($report['totals'])
                            <tfoot>
                                <tr class="font-semibold bg-surface-2">
                                    @foreach ($report['columns'] as $key => [$heading, $format])
                                        @php $v = $report['totals'][$key] ?? ''; @endphp
                                        <td @class(['text-right tabular-nums' => ! in_array($format, ['text', 'mono']), 'text-red-fg' => $format === 'signed' && $v < 0])>
                                            {{ $v === '' || $v === null ? '' : ($format === 'meter' ? $v.'%' : $cell($v, $format)) }}
                                        </td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                @else
                    <x-empty :icon="$icon" title="Nothing to show" text="{{ $dated ? 'No records fall in this period. Try a wider date range.' : 'There is no data for this report yet.' }}" />
                @endif
            </div>
            <p class="text-[12px] text-muted">Amounts in {{ \App\Models\Setting::get('finance.currency') }}.@if (in_array($type, ['pnl', 'expenses', 'projects'])) Only approved expenses are counted.@endif</p>
        </div>
    </div>
</x-layouts.app>
