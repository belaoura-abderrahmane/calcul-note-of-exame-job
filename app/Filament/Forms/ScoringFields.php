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
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;

/**
 * حقول معايير التنقيط السبعة (مشتركة بين ملف المترشح والحاسبة السريعة).
 */
class ScoringFields
{
    /**
     * @param  Closure(Get): mixed  $referenceDate  يرجع التاريخ المرجعي لحساب أقدمية الشهادة
     */
    public static function make(Closure $referenceDate, bool $withDetails = true): array
    {
        $calc = ScoreCalculator::make();
        $max = $calc->maxima();

        return [
            Section::make('1. مطابقة التخصص')
                ->description("الحد الأقصى {$max['score_specialty']} نقاط - تقدرها اللجنة حسب ملاءمة التخصص مع متطلبات الرتبة")
                ->icon(Heroicon::OutlinedAcademicCap)
                ->schema([
                    TextInput::make('specialty_match')
                        ->label('درجة مطابقة التخصص')
                        ->numeric()->minValue(0)->maxValue($max['score_specialty'])->step(0.5)
                        ->default(0)->required()
                        ->suffix('/ '.$max['score_specialty'])
                        ->live(debounce: 400),
                ]),

            Section::make('2. المسار الدراسي')
                ->description("الحد الأقصى {$max['score_path']} نقاط مهما كانت العلاوات")
                ->icon(Heroicon::OutlinedChartBar)
                ->columns(2)
                ->schema([
                    TextInput::make('average')
                        ->label('المعدل العام للشهادة')
                        ->numeric()->minValue(0)->maxValue(20)->step(0.01)
                        ->suffix('/ 20')
                        ->live(debounce: 400)
                        ->helperText(fn (Get $get) => 'نقاط المعدل: '.$calc->averagePoints($get('average'))),
                    Select::make('master_mention')
                        ->label('تقدير شهادة الماستر (إن وجد)')
                        ->options($calc->mentionOptions())
                        ->placeholder('بدون')
                        ->live(),
                    Toggle::make('is_grande_ecole')
                        ->label('خريج مدرسة عليا')
                        ->inline(false)
                        ->live(),
                    Toggle::make('is_major')
                        ->label('الأول على الدفعة')
                        ->inline(false)
                        ->live(),
                ]),

            Grid::make(2)->schema([
                Section::make('3. التكوين التكميلي')
                    ->description("الحد الأقصى {$max['score_training']} - ".$calc->rules()['training']['per_item'].' لكل تكوين')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->schema(array_filter([
                        TextInput::make('trainings_count')
                            ->label('عدد التكوينات المكملة للشهادة')
                            ->integer()->minValue(0)->default(0)->required()
                            ->live(debounce: 400),
                        $withDetails ? Textarea::make('trainings_details')->label('تفاصيل التكوينات')->rows(2) : null,
                    ])),

                Section::make('4. البحث العلمي')
                    ->description("الحد الأقصى {$max['score_research']} - ".$calc->rules()['research']['per_item'].' لكل عمل')
                    ->icon(Heroicon::OutlinedBeaker)
                    ->schema(array_filter([
                        TextInput::make('research_count')
                            ->label('عدد الأشغال والدراسات المنجزة')
                            ->integer()->minValue(0)->default(0)->required()
                            ->live(debounce: 400),
                        $withDetails ? Textarea::make('research_details')->label('تفاصيل الأعمال')->rows(2) : null,
                    ])),
            ]),

            Section::make('5. الخبرة المهنية')
                ->description("الحد الأقصى {$max['score_experience']} نقاط - الخبرات لا تُجمع، يُعتمد النوع الذي يمنح أكبر عدد من النقاط")
                ->icon(Heroicon::OutlinedBriefcase)
                ->schema([
                    Repeater::make('experiences')
                        ->hiddenLabel()
                        ->schema([
                            Select::make('type')
                                ->label('نوع الخبرة')
                                ->options($calc->experienceTypeOptions())
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
                                ->helperText(fn (Get $get) => 'النقاط: '.$calc->experienceLine($get('type'), $get('years'), $get('months'))),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->addActionLabel('إضافة خبرة مهنية')
                        ->reorderable(false)
                        ->live(),
                ]),

            Grid::make(2)->schema([
                Section::make('6. أقدمية الشهادة')
                    ->description("الحد الأقصى {$max['score_seniority']} - ".$calc->rules()['seniority']['per_year'].' عن كل سنة كاملة')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->schema([
                        DatePicker::make('diploma_date')
                            ->label('تاريخ الحصول على الشهادة')
                            ->native(false)->displayFormat('d/m/Y')
                            ->live()
                            ->helperText(fn (Get $get) => 'عدد السنوات الكاملة: '.$calc->seniorityYears($get('diploma_date'), $referenceDate($get))),
                        TextInput::make('seniority_years_override')
                            ->label('أو أدخل عدد سنوات الأقدمية يدوياً')
                            ->numeric()->minValue(0)->step(1)
                            ->placeholder('اتركه فارغاً للحساب التلقائي')
                            ->live(debounce: 400),
                    ]),

                Section::make('7. المقابلة الشفهية')
                    ->description("الحد الأقصى {$max['score_interview']} نقاط")
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->schema([
                        TextInput::make('interview')
                            ->label('علامة المقابلة')
                            ->numeric()->minValue(0)->maxValue($max['score_interview'])->step(0.25)
                            ->default(0)->required()
                            ->suffix('/ '.$max['score_interview'])
                            ->live(debounce: 400),
                    ]),
            ]),
        ];
    }

    /** لوحة النتيجة المباشرة */
    public static function summary(Closure $referenceDate): View
    {
        return View::make('filament.score-summary')
            ->viewData(function (Get $get) use ($referenceDate) {
                $calc = ScoreCalculator::make();

                return [
                    'result' => $calc->calculate([
                        'specialty_match' => $get('specialty_match'),
                        'average' => $get('average'),
                        'master_mention' => $get('master_mention'),
                        'is_grande_ecole' => $get('is_grande_ecole'),
                        'is_major' => $get('is_major'),
                        'trainings_count' => $get('trainings_count'),
                        'research_count' => $get('research_count'),
                        'experiences' => array_values($get('experiences') ?? []),
                        'diploma_date' => $get('diploma_date'),
                        'reference_date' => $referenceDate($get),
                        'seniority_years_override' => $get('seniority_years_override'),
                        'interview' => $get('interview'),
                    ]),
                    'maxima' => $calc->maxima(),
                    'labels' => ScoreCalculator::labels(),
                ];
            });
    }
}
