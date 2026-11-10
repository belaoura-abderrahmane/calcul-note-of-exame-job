<?php

namespace App\Filament\Widgets;

use App\Models\Candidate;
use App\Models\Competition;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $avg = (float) Candidate::avg('total');
        $max = (float) Candidate::max('total');

        return [
            Stat::make('المسابقات', Competition::count())
                ->description(Competition::where('status', 'open')->count().' مسابقة مفتوحة')
                ->descriptionIcon(Heroicon::OutlinedTrophy)
                ->color('primary'),
            Stat::make('المترشحون', Candidate::count())
                ->description('إجمالي الملفات المسجلة')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('info'),
            Stat::make('متوسط النقاط', number_format($avg, 2).' / 30')
                ->description(number_format($avg / 30 * 100, 1).'%')
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->color('warning'),
            Stat::make('أعلى مجموع', number_format($max, 2).' / 30')
                ->description(number_format($max / 30 * 100, 1).'%')
                ->descriptionIcon(Heroicon::OutlinedStar)
                ->color('success'),
        ];
    }
}
