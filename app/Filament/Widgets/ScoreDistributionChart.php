<?php

namespace App\Filament\Widgets;

use App\Models\Candidate;
use Filament\Widgets\ChartWidget;

class ScoreDistributionChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'توزيع المترشحين حسب المجموع';

    protected function getData(): array
    {
        $ranges = ['0-5' => [0, 5], '5-10' => [5, 10], '10-15' => [10, 15], '15-20' => [15, 20], '20-25' => [20, 25], '25-30' => [25, 30.01]];

        $data = collect($ranges)->map(fn ($r) => Candidate::where('total', '>=', $r[0])->where('total', '<', $r[1])->count());

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
