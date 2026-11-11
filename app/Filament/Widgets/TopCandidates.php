<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
use App\Services\ScoreCalculator;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopCandidates extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'أفضل المترشحين';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Candidate::query()->with('competition')->where('max_total', '>', 0)->orderByRaw('total * 1.0 / max_total desc')->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('last_name')->label('اللقب والاسم')->formatStateUsing(fn (Candidate $r) => $r->full_name)->weight('bold'),
                TextColumn::make('competition.title')->label('المسابقة')->limit(40),
                TextColumn::make('specialty')->label('التخصص'),
                TextColumn::make('total')->label('المجموع')->badge()->color('success')
                    ->formatStateUsing(fn ($state, Candidate $r) => number_format($state, 2).' / '.ScoreCalculator::format($r->max_total).' ('.number_format($r->percentage, 1).'%)'),
            ])
            ->recordUrl(fn (Candidate $r) => CandidateResource::getUrl('edit', ['record' => $r]));
    }
}
