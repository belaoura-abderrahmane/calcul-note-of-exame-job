<?php

namespace App\Filament\Widgets;

use App\Models\Candidate;
use App\Services\ScoreCalculator;
use Filament\Widgets\ChartWidget;

class CriteriaAverageChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'متوسط النسبة المحققة في كل معيار';

    protected function getData(): array
    {
        $maxima = ScoreCalculator::make()->maxima();
        $labels = ScoreCalculator::labels();

        $values = collect($maxima)->map(fn ($max, $col) => $max > 0 ? round((float) Candidate::avg($col) / $max * 100, 1) : 0);

        return [
            'datasets' => [[
                'label' => '%',
                'data' => $values->values()->all(),
                'backgroundColor' => 'rgba(16, 185, 129, 0.25)',
                'borderColor' => '#10b981',
                'pointBackgroundColor' => '#059669',
            ]],
            'labels' => array_values($labels),
        ];
    }

    protected function getType(): string
    {
        return 'radar';
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]], 'scales' => ['r' => ['min' => 0, 'max' => 100, 'ticks' => ['display' => false]], 'x' => ['display' => false], 'y' => ['display' => false]]];
    }
}
