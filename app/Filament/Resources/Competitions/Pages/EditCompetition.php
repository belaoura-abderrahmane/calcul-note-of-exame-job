<?php

namespace App\Filament\Resources\Competitions\Pages;

use App\Filament\Resources\Competitions\CompetitionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCompetition extends EditRecord
{
    protected static string $resource = CompetitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('طباعة قائمة الترتيب')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => route('competitions.print', $this->record), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }

    /** تغيير التاريخ المرجعي يؤثر على أقدمية الشهادة */
    protected function afterSave(): void
    {
        $this->record->recalculate();
    }
}
