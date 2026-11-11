<?php

namespace App\Filament\Widgets;

use App\Models\Candidate;
use Filament\Widgets\ChartWidget;

class ScoreDistributionChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'توزيع المترشحين حسب النسبة المحققة';

    protected function getData(): array
    {
        $ranges = ['0-20%' => [0, 20], '20-40%' => [20, 40], '40-50%' => [40, 50], '50-60%' => [50, 60], '60-75%' => [60, 75], '75-100%' => [75, 100.01]];
        $percentages = Candidate::query()->where('max_total', '>', 0)->selectRaw('total * 100.0 / max_total as pct')->pluck('pct');

        $data = collect($ranges)->map(fn (array $r): int => $percentages->filter(fn ($p): bool => $p >= $r[0] && $p < $r[1])->count());

        return [
            'datasets' => [[
                'label' => 'عدد المترشحين',
                'data' => $data->values()->all(),
                'backgroundColor' => ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#22c55e', '#059669'],
                'borderRadius' => 6,
            ]],
            'labels' => array_keys($ranges),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]], 'scales' => ['y' => ['ticks' => ['precision' => 0]]]];
    }
}
