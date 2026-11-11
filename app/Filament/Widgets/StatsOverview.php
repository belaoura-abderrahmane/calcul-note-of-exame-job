<?php

namespace App\Filament\Widgets;

use App\Models\Candidate;
use App\Models\Competition;
use App\Services\ScoreCalculator;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // المسابقات قد تختلف في المجموع الأقصى (30، 33...) لذا نقارن بالنسب المئوية
        $percentages = Candidate::query()->where('max_total', '>', 0)->selectRaw('total * 100.0 / max_total as pct')->pluck('pct');
        $avg = (float) $percentages->avg();
        $max = (float) $percentages->max();
        $best = Candidate::query()->where('max_total', '>', 0)->orderByRaw('total * 1.0 / max_total desc')->first();

        return [
            Stat::make('المسابقات', Competition::count())
                ->description(Competition::where('status', 'open')->count().' مسابقة مفتوحة')
                ->descriptionIcon(Heroicon::OutlinedTrophy)
                ->color('primary'),
            Stat::make('المترشحون', Candidate::count())
                ->description('إجمالي الملفات المسجلة')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('info'),
            Stat::make('متوسط النتائج', number_format($avg, 1).'%')
                ->description('نسبة المجموع إلى المجموع الأقصى')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color('warning'),
            Stat::make('أعلى نتيجة', number_format($max, 1).'%')
                ->description($best ? $best->full_name.' — '.number_format($best->total, 2).' / '.ScoreCalculator::format($best->max_total) : '—')
                ->descriptionIcon(Heroicon::OutlinedStar)
                ->color('success'),
        ];
    }
}
