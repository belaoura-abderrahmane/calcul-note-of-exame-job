<?php

namespace App\Filament\Resources\ScoringProfiles\Pages;

use App\Filament\Resources\ScoringProfiles\ScoringProfileResource;
use App\Models\ScoringProfile;
use App\Support\ScoringPresets;
use Filament\Resources\Pages\CreateRecord;

class CreateScoringProfile extends CreateRecord
{
    protected static string $resource = ScoringProfileResource::class;

    /** السلم الجديد يبدأ من قيم السلم العام لتسهيل التعديل */
    protected function fillForm(): void
    {
        $this->form->fill([
            'sector' => 'أخرى',
            'rules' => ScoringPresets::baseRules(),
            'is_default' => false,
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['rules'] = ScoringProfile::completeRules($data['rules'] ?? []);
        $data['sort'] = (int) ScoringProfile::max('sort') + 1;

        return $data;
    }
}
