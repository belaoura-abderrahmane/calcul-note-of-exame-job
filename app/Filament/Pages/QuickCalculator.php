<?php

namespace App\Filament\Pages;

use App\Filament\Forms\ScoringFields;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class QuickCalculator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'الأدوات';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'الحاسبة السريعة';

    protected static ?string $title = 'حاسبة نقاط التوظيف على أساس الشهادة';

    protected ?string $subheading = 'احسب مجموع نقاطك من 30 مباشرة دون حفظ البيانات';

    protected string $view = 'filament.pages.quick-calculator';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $ref = fn (Get $get) => $get('reference_date');

        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])->schema([
                    Group::make()
                        ->columnSpan(['default' => 1, 'xl' => 2])
                        ->schema([
                            Section::make('التاريخ المرجعي')
                                ->description('تاريخ اختتام التسجيلات أو تاريخ المسابقة - يُستعمل لحساب أقدمية الشهادة')
                                ->icon(Heroicon::OutlinedCalendar)
                                ->compact()
                                ->schema([
                                    DatePicker::make('reference_date')->hiddenLabel()->default(fn () => now()->toDateString())->native(false)->displayFormat('d/m/Y')->live(),
                                ]),
                            ...ScoringFields::make($ref, withDetails: false),
                        ]),
                    Group::make()
                        ->extraAttributes(['class' => 'score-sticky'])
                        ->schema([ScoringFields::summary($ref)]),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('إعادة تعيين')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(fn () => $this->form->fill()),
        ];
    }
}
