<?php

namespace App\Filament\Resources\ScoringProfiles\Pages;

use App\Filament\Resources\ScoringProfiles\ScoringProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScoringProfiles extends ListRecords
{
    protected static string $resource = ScoringProfileResource::class;

    protected ?string $subheading = 'لكل نوع مسابقة سلم تنقيط خاص به. اختر السلم المناسب عند إنشاء المسابقة، أو انسخ سلماً موجوداً وعدّله لإنشاء نوع جديد.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('إضافة سلم جديد')];
    }
}
