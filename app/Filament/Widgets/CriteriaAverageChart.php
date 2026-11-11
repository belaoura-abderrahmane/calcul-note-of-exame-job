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
        $labels = ScoreCalculator::labels();
        $calculators = [];
        $sums = array_fill_keys(array_keys($labels), 0.0);
        $counts = array_fill_keys(array_keys($labels), 0);

        // كل مترشح يُقارن بالحد الأقصى في سلم مسابقته
        Candidate::query()->with('competition.scoringProfile')->each(function (Candidate $candidate) use (&$calculators, &$sums, &$counts): void {
            $maxima = ($calculators[$candidate->competition_id] ??= $candidate->competition?->calculator() ?? ScoreCalculator::make())->maxima();
            foreach ($maxima as $col => $max) {
                if ($max > 0) {
                    $sums[$col] += $candidate->{$col} / $max * 100;
                    $counts[$col]++;
                }
            }
        });

        $values = collect($sums)->map(fn (float $sum, string $col): float => $counts[$col] ? round($sum / $counts[$col], 1) : 0);

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
