<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Candidate;
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
            ->query(fn () => Candidate::query()->with('competition')->orderByDesc('total')->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('last_name')->label('اللقب والاسم')->formatStateUsing(fn (Candidate $r) => $r->full_name)->weight('bold'),
                TextColumn::make('competition.title')->label('المسابقة')->limit(40),
                TextColumn::make('specialty')->label('التخصص'),
                TextColumn::make('total')->label('المجموع / 30')->badge()->color('success')->formatStateUsing(fn ($state) => number_format($state, 2)),
            ])
            ->recordUrl(fn (Candidate $r) => CandidateResource::getUrl('edit', ['record' => $r]));
    }
}
