<?php

namespace App\Filament\Pages;

use App\Models\Competition;
use App\Models\ScoringSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ScoringSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'الأدوات';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'إعدادات التنقيط';

    protected static ?string $title = 'إعدادات سلم التنقيط';

    protected ?string $subheading = 'عدّل القيم وفق النصوص التنظيمية المعمول بها، ثم احفظ لإعادة حساب نقاط جميع المترشحين';

    protected string $view = 'filament.pages.scoring-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(ScoringSetting::rules());
    }

    public function form(Schema $schema): Schema
    {
        $num = fn (string $name, string $label) => TextInput::make($name)->label($label)->numeric()->minValue(0)->required();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('الحد الأقصى لكل معيار')
                    ->icon(Heroicon::OutlinedScale)
                    ->columns(['default' => 2, 'lg' => 4])
                    ->schema([
                        $num('specialty.max', 'مطابقة التخصص'),
                        $num('path.max', 'المسار الدراسي'),
                        $num('training.max', 'التكوين التكميلي'),
                        $num('research.max', 'البحث العلمي'),
                        $num('experience.max', 'الخبرة المهنية'),
                        $num('seniority.max', 'أقدمية الشهادة'),
                        $num('interview.max', 'المقابلة الشفهية'),
                    ]),

                Grid::make(['default' => 1, 'lg' => 2])->schema([
                    Section::make('سلم المعدل العام')
                        ->description('تُمنح النقاط لأعلى شريحة يبلغ المعدل حدها الأدنى، وما دون أصغر شريحة = 0')
                        ->icon(Heroicon::OutlinedChartBar)
                        ->schema([
                            Repeater::make('path.bands')
                                ->hiddenLabel()
                                ->schema([
                                    $num('min', 'المعدل الأدنى'),
                                    $num('points', 'النقاط'),
                                ])
                                ->columns(2)
                                ->addActionLabel('إضافة شريحة'),
                        ]),

                    Section::make('علاوات المسار الدراسي')
                        ->icon(Heroicon::OutlinedStar)
                        ->schema([
                            Repeater::make('path.mentions')
                                ->label('علاوة تقدير الماستر')
                                ->schema([
                                    TextInput::make('key')->label('الرمز')->required()->alphaDash(),
                                    TextInput::make('label')->label('التقدير')->required(),
                                    $num('bonus', 'العلاوة'),
                                ])
                                ->columns(3)
                                ->addActionLabel('إضافة تقدير'),
                            Grid::make(2)->schema([
                                $num('path.grande_ecole_bonus', 'علاوة خريج مدرسة عليا'),
                                $num('path.major_bonus', 'علاوة الأول على الدفعة'),
                            ]),
                        ]),
                ]),

                Grid::make(['default' => 1, 'lg' => 3])->schema([
                    Section::make('التكوين التكميلي')->icon(Heroicon::OutlinedBookOpen)
                        ->schema([$num('training.per_item', 'النقاط لكل تكوين')]),
                    Section::make('البحث العلمي')->icon(Heroicon::OutlinedBeaker)
                        ->schema([$num('research.per_item', 'النقاط لكل عمل منجز')]),
                    Section::make('أقدمية الشهادة')->icon(Heroicon::OutlinedCalendarDays)
                        ->schema([$num('seniority.per_year', 'النقاط لكل سنة كاملة')]),
                ]),

                Section::make('أنواع الخبرة المهنية')
                    ->description('لا تُجمع الخبرات؛ يُعتمد النوع الذي يمنح المترشح أكبر عدد من النقاط')
                    ->icon(Heroicon::OutlinedBriefcase)
                    ->schema([
                        Toggle::make('experience.count_months')->label('احتساب الأشهر كجزء من السنة'),
                        Repeater::make('experience.types')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('key')->label('الرمز')->required()->alphaDash(),
                                TextInput::make('label')->label('نوع الخبرة')->required()->columnSpan(2),
                                $num('per_year', 'النقاط لكل سنة'),
                            ])
                            ->columns(4)
                            ->addActionLabel('إضافة نوع خبرة'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $rules = $this->form->getState();

        $rules['path']['bands'] = array_values($rules['path']['bands'] ?? []);
        $rules['path']['mentions'] = array_values($rules['path']['mentions'] ?? []);
        $rules['experience']['types'] = array_values($rules['experience']['types'] ?? []);

        ScoringSetting::query()->updateOrCreate(['id' => 1], ['rules' => $rules]);

        $count = Competition::all()->sum(fn (Competition $c) => $c->recalculate());

        Notification::make()
            ->title('تم حفظ الإعدادات')
            ->body("تمت إعادة حساب نقاط {$count} مترشح.")
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('defaults')
                ->label('استرجاع القيم الافتراضية')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->requiresConfirmation()
                ->action(fn () => $this->form->fill(config('scoring'))),
        ];
    }
}
