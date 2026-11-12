<?php

namespace App\Filament\Forms;

use App\Services\ScoreCalculator;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;

/**
 * حقول معايير التنقيط السبعة (مشتركة بين ملف المترشح والحاسبة السريعة).
 * كل الحدود والشروحات تُقرأ من سلم التنقيط المختار، فتتغير الحقول مباشرة عند تغيير نوع المسابقة.
 */
class ScoringFields
{
    /**
     * @param  Closure(Get, string): ScoreCalculator  $calculator  يرجع حاسبة سلم التنقيط المختار (الوسيط الثاني: بادئة المسار داخل Repeater)
     * @param  Closure(Get): mixed  $referenceDate  التاريخ المرجعي لحساب أقدمية الشهادة
     * @return array<int, Component>
     */
    public static function make(Closure $calculator, Closure $referenceDate, bool $withDetails = true): array
    {
        $calc = fn (Get $get): ScoreCalculator => $calculator($get, '');
        $max = fn (Get $get, string $key): float => $calc($get)->maxima()[$key];
        $fmt = fn (float $v): string => ScoreCalculator::format($v);
        $hiddenIfZero = fn (string $key): Closure => fn (Get $get): bool => $max($get, $key) <= 0;
        $title = fn (int $n, string $key): Closure => fn (Get $get): string => $n.'. '.$calc($get)->criterionLabels()[$key];

        return [
            Section::make($title(1, 'score_specialty'))
                ->description(fn (Get $get) => 'الحد الأقصى '.$fmt($max($get, 'score_specialty')).' نقاط')
                ->icon(Heroicon::OutlinedAcademicCap)
                ->hidden($hiddenIfZero('score_specialty'))
                ->schema([
                    static::help($calc, 'specialty'),
                    Grid::make(['default' => 1, 'md' => 3])->schema([
                        Select::make('specialty_level')
                            ->label('اختيار سريع حسب ترتيب التخصص في قرار فتح المسابقة')
                            ->options(fn (Get $get): array => $calc($get)->specialtyLevelOptions())
                            ->visible(fn (Get $get): bool => $calc($get)->specialtyLevelOptions() !== [])
                            ->placeholder('اختر مستوى الملاءمة')
                            ->native(false)
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set) => filled($state) ? $set('specialty_match', (float) $state) : null)
                            ->columnSpan(['md' => 2]),
                        TextInput::make('specialty_match')
                            ->label('درجة مطابقة التخصص')
                            ->numeric()->minValue(0)->step(0.25)->default(0)->required()
                            ->maxValue(fn (Get $get): float => $max($get, 'score_specialty'))
                            ->suffix(fn (Get $get): string => '/ '.$fmt($max($get, 'score_specialty')))
                            ->live(debounce: 400),
                    ]),
                ]),

            Section::make($title(2, 'score_path'))
                ->description(fn (Get $get) => 'الحد الأقصى '.$fmt($max($get, 'score_path')).' نقاط مهما كانت العلاوات')
                ->icon(Heroicon::OutlinedChartBar)
                ->hidden($hiddenIfZero('score_path'))
                ->columns(2)
                ->schema([
                    static::help($calc, 'path')->columnSpanFull(),
                    TextInput::make('average')
                        ->label('المعدل العام للشهادة')
                        ->hidden(fn (Get $get): bool => ($calc($get)->rules()['path']['bands'] ?? []) === [])
                        ->numeric()->minValue(0)->maxValue(20)->step(0.01)
                        ->suffix('/ 20')
                        ->live(debounce: 400)
                        ->helperText(fn (Get $get) => 'نقاط المعدل حسب السلم: '.$fmt($calc($get)->averagePoints($get('average')))),
                    Select::make('master_mention')
                        ->label('تقدير شهادة الماستر / المهندس (إن وجد)')
                        ->options(fn (Get $get): array => $calc($get)->mentionOptions())
                        ->placeholder('بدون')
                        ->live()
                        ->helperText(fn (Get $get) => 'العلاوة: '.$fmt($calc($get)->mentionBonus($get('master_mention')))),
                    Toggle::make('is_grande_ecole')
                        ->label('خريج مدرسة عليا')
                        ->helperText(fn (Get $get) => 'علاوة: +'.$fmt((float) $calc($get)->rules()['path']['grande_ecole_bonus']))
                        ->hidden(fn (Get $get): bool => (float) $calc($get)->rules()['path']['grande_ecole_bonus'] <= 0)
                        ->inline(false)
                        ->live(),
                    Toggle::make('is_major')
                        ->label('الأول على الدفعة')
                        ->helperText(fn (Get $get) => 'علاوة: +'.$fmt((float) $calc($get)->rules()['path']['major_bonus']))
                        ->hidden(fn (Get $get): bool => (float) $calc($get)->rules()['path']['major_bonus'] <= 0)
                        ->inline(false)
                        ->live(),
                ]),

            Grid::make(2)->schema([
                Section::make($title(3, 'score_training'))
                    ->description(fn (Get $get) => 'الحد الأقصى '.$fmt($max($get, 'score_training'))
                        .($calc($get)->isManual('training') ? ' - تُدخل النقاط مباشرة' : ' - '.$fmt((float) $calc($get)->rules()['training']['per_item']).' لكل '.$calc($get)->unitLabel('training', 'تكوين')))
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->hidden($hiddenIfZero('score_training'))
                    ->schema(array_values(array_filter([
                        static::help($calc, 'training'),
                        TextInput::make('trainings_count')
                            ->label(fn (Get $get) => 'العدد (بوحدة: '.$calc($get)->unitLabel('training', 'تكوين').')')
                            ->integer()->minValue(0)->default(0)
                            ->hidden(fn (Get $get): bool => $calc($get)->isManual('training'))
                            ->live(debounce: 400),
                        TextInput::make('training_points')
                            ->label('النقاط (تقدرها اللجنة حسب الشرح أعلاه)')
                            ->numeric()->minValue(0)->step(0.25)
                            ->maxValue(fn (Get $get): float => $max($get, 'score_training'))
                            ->suffix(fn (Get $get): string => '/ '.$fmt($max($get, 'score_training')))
                            ->visible(fn (Get $get): bool => $calc($get)->isManual('training'))
                            ->live(debounce: 400),
                        $withDetails ? Textarea::make('trainings_details')->label('تفاصيل التكوينات')->rows(2) : null,
                    ]))),

                Section::make($title(4, 'score_research'))
                    ->description(fn (Get $get) => 'الحد الأقصى '.$fmt($max($get, 'score_research'))
                        .($calc($get)->isManual('research') ? ' - تُدخل النقاط مباشرة' : ' - '.$fmt((float) $calc($get)->rules()['research']['per_item']).' لكل '.$calc($get)->unitLabel('research', 'عمل')))
                    ->icon(Heroicon::OutlinedBeaker)
                    ->hidden($hiddenIfZero('score_research'))
                    ->schema(array_values(array_filter([
                        static::help($calc, 'research'),
                        TextInput::make('research_count')
                            ->label(fn (Get $get) => 'العدد (بوحدة: '.$calc($get)->unitLabel('research', 'عمل').')')
                            ->integer()->minValue(0)->default(0)
                            ->hidden(fn (Get $get): bool => $calc($get)->isManual('research'))
                            ->live(debounce: 400),
                        TextInput::make('research_points')
                            ->label('النقاط (تقدرها اللجنة حسب الشرح أعلاه)')
                            ->numeric()->minValue(0)->step(0.25)
                            ->maxValue(fn (Get $get): float => $max($get, 'score_research'))
                            ->suffix(fn (Get $get): string => '/ '.$fmt($max($get, 'score_research')))
                            ->visible(fn (Get $get): bool => $calc($get)->isManual('research'))
                            ->live(debounce: 400),
                        $withDetails ? Textarea::make('research_details')->label('تفاصيل الأعمال')->rows(2) : null,
                    ]))),
            ]),

            Section::make($title(5, 'score_experience'))
                ->description(fn (Get $get) => 'الحد الأقصى '.$fmt($max($get, 'score_experience')).' نقاط - '
                    .(($calc($get)->rules()['experience']['combine'] ?? 'best') === 'sum'
                        ? 'تُجمع نقاط كل الأنواع في حدود الحد الأقصى'
                        : 'الأنواع لا تُجمع: يُعتمد النوع الذي يمنح أكبر عدد من النقاط'))
                ->icon(Heroicon::OutlinedBriefcase)
                ->hidden($hiddenIfZero('score_experience'))
                ->schema([
                    static::help($calc, 'experience'),
                    Repeater::make('experiences')
                        ->hiddenLabel()
                        ->schema([
                            Select::make('type')
                                ->label('نوع الخبرة')
                                ->options(fn (Get $get): array => static::experienceOptions($calculator($get, '../../')))
                                ->required()
                                ->live()
                                ->columnSpan(2),
                            TextInput::make('years')
                                ->label('السنوات')
                                ->integer()->minValue(0)->default(0)->required()
                                ->live(debounce: 400),
                            TextInput::make('months')
                                ->label('الأشهر')
                                ->integer()->minValue(0)->maxValue(11)->default(0)
                                ->live(debounce: 400)
                                ->helperText(fn (Get $get) => 'النقاط: '.$fmt($calculator($get, '../../')->experienceLine($get('type'), $get('years'), $get('months')))),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->addActionLabel('إضافة خبرة مهنية')
                        ->reorderable(false)
                        ->live(),
                ]),

            Grid::make(2)->schema([
                Section::make($title(6, 'score_seniority'))
                    ->description(fn (Get $get) => 'الحد الأقصى '.$fmt($max($get, 'score_seniority')).' - '
                        .$fmt((float) $calc($get)->rules()['seniority']['per_year']).' عن كل سنة'
                        .((float) ($calc($get)->rules()['seniority']['per_month'] ?? 0) > 0
                            ? ' و'.$fmt((float) $calc($get)->rules()['seniority']['per_month']).' عن كل شهر'
                            : ' كاملة'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->hidden($hiddenIfZero('score_seniority'))
                    ->schema([
                        static::help($calc, 'seniority'),
                        DatePicker::make('diploma_date')
                            ->label('تاريخ الحصول على الشهادة')
                            ->native(false)->displayFormat('d/m/Y')
                            ->live()
                            ->helperText(fn (Get $get) => $calc($get)->seniorityDescription($get('diploma_date'), $referenceDate($get))),
                        TextInput::make('seniority_years_override')
                            ->label('أو أدخل عدد سنوات الأقدمية يدوياً (يلغي الحساب التلقائي)')
                            ->numeric()->minValue(0)->step(0.01)
                            ->placeholder('اتركه فارغاً (أو 0) للحساب التلقائي من التاريخ')
                            ->live(debounce: 400),
                    ]),

                Section::make($title(7, 'score_interview'))
                    ->description(fn (Get $get) => 'الحد الأقصى '.$fmt($max($get, 'score_interview')).' نقاط')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->hidden($hiddenIfZero('score_interview'))
                    ->schema([
                        static::help($calc, 'interview'),
                        Grid::make(1)
                            ->visible(fn (Get $get): bool => $calc($get)->interviewItems() !== [])
                            ->schema(fn (Get $get): array => collect($calc($get)->interviewItems())
                                ->map(fn (array $item): TextInput => TextInput::make('interview_details.'.$item['key'])
                                    ->label($item['label'])
                                    ->numeric()->minValue(0)->step(0.25)
                                    ->maxValue((float) $item['max'])
                                    ->placeholder('0')
                                    ->suffix('/ '.$fmt((float) $item['max']))
                                    ->live(debounce: 400))
                                ->all()),
                        TextInput::make('interview')
                            ->hidden(fn (Get $get): bool => $calc($get)->interviewItems() !== [])
                            ->label('علامة المقابلة')
                            ->numeric()->minValue(0)->step(0.25)
                            ->maxValue(fn (Get $get): float => $max($get, 'score_interview'))
                            ->default(0)->required()
                            ->suffix(fn (Get $get): string => '/ '.$fmt($max($get, 'score_interview')))
                            ->live(debounce: 400),
                    ]),
            ]),
        ];
    }

    /**
     * شرح المعيار حسب السلم المختار (نص قانوني قابل للتعديل من صفحة سلالم التنقيط).
     *
     * @param  Closure(Get): ScoreCalculator  $calc
     */
    protected static function help(Closure $calc, string $criterion): View
    {
        return View::make('filament.criterion-help')
            ->viewData(fn (Get $get): array => ['text' => $calc($get)->help($criterion)]);
    }

    /** @return array<string, string> */
    protected static function experienceOptions(ScoreCalculator $calculator): array
    {
        $rules = $calculator->rules()['experience'];

        return collect($rules['types'])->mapWithKeys(function (array $type) use ($rules): array {
            $cap = (isset($type['max']) && $type['max'] !== '' && $type['max'] !== null) ? (float) $type['max'] : (float) $rules['max'];

            return [$type['key'] => $type['label'].' — '.ScoreCalculator::format((float) $type['per_year']).'/سنة، حد '.ScoreCalculator::format($cap)];
        })->all();
    }

    /**
     * لوحة النتيجة المباشرة.
     *
     * @param  Closure(Get, string): ScoreCalculator  $calculator
     */
    public static function summary(Closure $calculator, Closure $referenceDate): View
    {
        return View::make('filament.score-summary')
            ->viewData(function (Get $get) use ($calculator, $referenceDate): array {
                $calc = $calculator($get, '');

                return [
                    'result' => $calc->calculate([
                        'specialty_match' => $get('specialty_match'),
                        'average' => $get('average'),
                        'master_mention' => $get('master_mention'),
                        'is_grande_ecole' => $get('is_grande_ecole'),
                        'is_major' => $get('is_major'),
                        'trainings_count' => $get('trainings_count'),
                        'research_count' => $get('research_count'),
                        'training_points' => $get('training_points'),
                        'research_points' => $get('research_points'),
                        'experiences' => array_values($get('experiences') ?? []),
                        'diploma_date' => $get('diploma_date'),
                        'reference_date' => $referenceDate($get),
                        'seniority_years_override' => $get('seniority_years_override'),
                        'interview' => $get('interview'),
                        'interview_details' => $get('interview_details'),
                    ]),
                    'maxima' => $calc->maxima(),
                    'labels' => $calc->criterionLabels(),
                ];
            });
    }
}
