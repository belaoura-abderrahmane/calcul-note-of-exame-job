<?php

namespace App\Filament\Pages;

use App\Filament\Forms\ScoringFields;
use App\Models\ScoringProfile;
use App\Services\ScoreCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
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

    protected ?string $subheading = 'اختر نوع المسابقة ثم أدخل معطياتك لحساب مجموع نقاطك مباشرة دون حفظ البيانات';

    protected string $view = 'filament.pages.quick-calculator';

    public ?array $data = [];

    /** @var array<int, ScoringProfile|null> */
    private array $profiles = [];

    protected function profile(mixed $id): ?ScoringProfile
    {
        return $id ? ($this->profiles[(int) $id] ??= ScoringProfile::find($id)) : null;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $ref = fn (Get $get) => $get('reference_date');
        $calc = fn (Get $get, string $up = ''): ScoreCalculator => $this->profile($get($up.'scoring_profile_id'))?->calculator() ?? ScoreCalculator::make();

        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])->schema([
                    Group::make()
                        ->columnSpan(['default' => 1, 'xl' => 2])
                        ->schema([
                            Section::make('نوع المسابقة')
                                ->description('اختر سلم التنقيط المطابق للمسابقة: تتغير المعايير والحدود والشروحات تلقائياً.')
                                ->icon(Heroicon::OutlinedScale)
                                ->columns(['default' => 1, 'md' => 3])
                                ->schema([
                                    Select::make('scoring_profile_id')
                                        ->label('سلم التنقيط')
                                        ->options(fn (): array => ScoringProfile::query()->orderBy('sort')->pluck('name', 'id')->all())
                                        ->default(fn (): ?int => ScoringProfile::default()?->getKey())
                                        ->native(false)->required()->live()
                                        ->columnSpan(['md' => 2]),
                                    DatePicker::make('reference_date')
                                        ->label('تاريخ فتح المسابقة')
                                        ->helperText('لحساب أقدمية الشهادة')
                                        ->native(false)->displayFormat('d/m/Y')->default(fn () => now()->toDateString())->live(),
                                    View::make('filament.profile-card')
                                        ->viewData(fn (Get $get): array => ['profile' => $this->profile($get('scoring_profile_id'))])
                                        ->columnSpanFull(),
                                ]),
                            ...ScoringFields::make($calc, $ref, withDetails: false),
                        ]),
                    Group::make()
                        ->extraAttributes(['class' => 'score-sticky'])
                        ->schema([ScoringFields::summary($calc, $ref)]),
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
