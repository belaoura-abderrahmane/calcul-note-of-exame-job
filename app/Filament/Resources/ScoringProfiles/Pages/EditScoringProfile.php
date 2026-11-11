<?php

namespace App\Filament\Resources\ScoringProfiles\Pages;

use App\Filament\Resources\ScoringProfiles\ScoringProfileResource;
use App\Models\ScoringProfile;
use App\Support\ScoringPresets;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property ScoringProfile $record
 */
class EditScoringProfile extends EditRecord
{
    protected static string $resource = ScoringProfileResource::class;

    protected ?string $subheading = 'عدّل القيم وفق النص التنظيمي وقرار فتح المسابقة. عند الحفظ يُعاد حساب نقاط كل المترشحين في المسابقات التي تستعمل هذا السلم.';

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['rules'] = ScoringProfile::completeRules($data['rules'] ?? []);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (['path.bands', 'path.mentions', 'experience.types', 'specialty.levels'] as $path) {
            data_set($data['rules'], $path, array_values(data_get($data['rules'], $path) ?? []));
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $count = $this->record->recalculate();

        Notification::make()
            ->title('تم حفظ السلم')
            ->body("تمت إعادة حساب نقاط {$count} مترشح.")
            ->success()
            ->send();
    }

    protected function getSavedNotification(): ?Notification
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        $preset = collect(ScoringPresets::all())->firstWhere('slug', $this->record->slug);

        return [
            Action::make('restore')
                ->label('استرجاع القيم الأصلية')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible($preset !== null)
                ->requiresConfirmation()
                ->modalDescription('ستُستبدل القيم والشروحات الحالية بالقيم الأصلية لهذا السلم كما هي مدمجة في البرنامج.')
                ->action(function () use ($preset): void {
                    $this->form->fill([
                        ...$this->form->getRawState(),
                        'legal_reference' => $preset['legal_reference'],
                        'description' => $preset['description'],
                        'rules' => ScoringProfile::completeRules($preset['rules']),
                    ]);
                }),
            DeleteAction::make()->hidden(fn (): bool => $this->record->is_default),
        ];
    }
}
