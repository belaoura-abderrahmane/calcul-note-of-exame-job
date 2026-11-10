<?php

namespace App\Filament\Resources\Candidates\Pages;

use App\Filament\Resources\Candidates\CandidateResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCandidates extends ListRecords
{
    protected static string $resource = CandidateResource::class;

    protected function getHeaderActions(): array
    {
        $competition = $this->tableFilters['competition_id']['value'] ?? null;

        return [
            Action::make('print')
                ->label('طباعة قائمة الترتيب')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->visible(filled($competition))
                ->url(fn () => route('competitions.print', $competition), shouldOpenInNewTab: true),
            CreateAction::make()
                ->url(fn () => CandidateResource::getUrl('create', array_filter(['competition' => $competition]))),
        ];
    }
}
