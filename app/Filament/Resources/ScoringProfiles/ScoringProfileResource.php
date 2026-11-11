<?php

namespace App\Filament\Resources\ScoringProfiles;

use App\Filament\Resources\ScoringProfiles\Pages\CreateScoringProfile;
use App\Filament\Resources\ScoringProfiles\Pages\EditScoringProfile;
use App\Filament\Resources\ScoringProfiles\Pages\ListScoringProfiles;
use App\Models\ScoringProfile;
use App\Services\ScoreCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ScoringProfileResource extends Resource
{
    protected static ?string $model = ScoringProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'الأدوات';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'سلم تنقيط';

    protected static ?string $pluralModelLabel = 'سلالم التنقيط';

    protected static ?string $navigationLabel = 'سلالم التنقيط (أنواع المسابقات)';

    protected static ?string $recordTitleAttribute = 'name';

    /** المعايير وحدودها القصوى داخل حقل rules */
    private const MAX_FIELDS = [
        'specialty' => 'مطابقة التخصص',
        'path' => 'المسار الدراسي',
        'training' => 'التكوين المكمل',
        'research' => 'الأشغال المنجزة',
        'experience' => 'الخبرة المهنية',
        'seniority' => 'أقدمية الشهادة',
        'interview' => 'المقابلة',
    ];

    public static function form(Schema $schema): Schema
    {
        $num = fn (string $name, string $label): TextInput => TextInput::make($name)->label($label)->numeric()->minValue(0)->step(0.25)->required();
        $help = fn (string $criterion): Textarea => Textarea::make("rules.{$criterion}.help")
            ->label('الشرح القانوني (يظهر للمستخدم عند إدخال هذا المعيار)')
            ->rows(3)
            ->columnSpanFull();
        $key = fn (): Hidden => Hidden::make('key')->default(fn (): string => 'k'.Str::lower(Str::random(6)));
        $rename = fn (string $criterion): TextInput => TextInput::make("rules.{$criterion}.label")
            ->label('اسم المعيار في هذا السلم (اختياري)')
            ->placeholder(self::MAX_FIELDS[$criterion]);
        $mode = fn (string $criterion): Select => Select::make("rules.{$criterion}.mode")
            ->label('طريقة الحساب')
            ->options(['count' => 'بالعدد × النقاط لكل وحدة', 'manual' => 'نقاط تُدخلها اللجنة مباشرة (مثل المنشورات حسب صنف المجلة)'])
            ->default('count')->native(false)->required();

        return $schema->components([
            Section::make('نوع المسابقة والمرجع القانوني')
                ->icon(Heroicon::OutlinedScale)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->label('اسم السلم / نوع المسابقة')
                        ->placeholder('مثال: قطاع الصحة - شبه الطبي على أساس الشهادة')
                        ->required()->maxLength(255),
                    TextInput::make('sector')
                        ->label('القطاع')
                        ->placeholder('الوظيفة العمومية، التربية الوطنية، الصحة...')
                        ->datalist(['الوظيفة العمومية', 'التربية الوطنية', 'التعليم العالي', 'الصحة', 'التكوين المهني', 'الجماعات المحلية', 'أخرى']),
                    Textarea::make('legal_reference')
                        ->label('المرجع القانوني (المرسوم، القرار، المنشور...)')
                        ->rows(3)->columnSpanFull(),
                    Textarea::make('description')
                        ->label('شرح وملاحظات حول هذا السلم')
                        ->rows(3)->columnSpanFull(),
                    Toggle::make('is_default')
                        ->label('السلم الافتراضي للمسابقات الجديدة'),
                ]),

            Section::make('الحد الأقصى لكل معيار')
                ->description(fn (Get $get): string => 'المجموع الأقصى للسلم: '.ScoreCalculator::format(
                    collect(array_keys(self::MAX_FIELDS))->sum(fn (string $c): float => (float) $get("rules.{$c}.max"))
                ).' نقطة. ضع 0 لإلغاء معيار غير مستعمل في هذه المسابقة (يُخفى من الاستمارة).')
                ->icon(Heroicon::OutlinedCalculator)
                ->columns(['default' => 2, 'lg' => 7])
                ->columnSpanFull()
                ->schema(collect(self::MAX_FIELDS)->map(
                    fn (string $label, string $criterion): TextInput => $num("rules.{$criterion}.max", $label)->live(debounce: 500)
                )->values()->all()),

            Tabs::make('criteria')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('1. مطابقة التخصص')->icon(Heroicon::OutlinedAcademicCap)->schema([
                        $rename('specialty'),
                        $help('specialty'),
                        Repeater::make('rules.specialty.levels')
                            ->label('مستويات الملاءمة حسب ترتيب الأولوية في قرار فتح المسابقة (اتركها فارغة لإدخال النقطة يدوياً)')
                            ->schema([
                                TextInput::make('label')->label('المستوى')->required()->columnSpan(3),
                                $num('points', 'النقاط'),
                            ])
                            ->columns(4)
                            ->defaultItems(0)
                            ->addActionLabel('إضافة مستوى'),
                    ]),

                    Tab::make('2. المسار الدراسي')->icon(Heroicon::OutlinedChartBar)->schema([
                        $rename('path'),
                        $help('path'),
                        Grid::make(['default' => 1, 'lg' => 2])->schema([
                            Repeater::make('rules.path.bands')
                                ->label('سلم المعدل (تُمنح نقاط أعلى شريحة يبلغها المعدل، وما دون أصغر شريحة = 0)')
                                ->schema([$num('min', 'المعدل الأدنى'), $num('points', 'النقاط')])
                                ->columns(2)
                                ->addActionLabel('إضافة شريحة'),
                            Repeater::make('rules.path.mentions')
                                ->label('علاوة تقدير الماستر / المهندس')
                                ->schema([$key(), TextInput::make('label')->label('التقدير')->required(), $num('bonus', 'العلاوة')])
                                ->columns(2)
                                ->addActionLabel('إضافة تقدير'),
                        ]),
                        Grid::make(3)->schema([
                            $num('rules.path.grande_ecole_bonus', 'علاوة خريجي المدارس العليا'),
                            $num('rules.path.major_bonus', 'علاوة الأول على الدفعة'),
                            Toggle::make('rules.path.bonus_outside_cap')
                                ->label('تُضاف هاتان العلاوتان خارج الحد الأقصى للمعيار (مثل سلم الأساتذة 33 نقطة)')
                                ->inline(false),
                        ]),
                    ]),

                    Tab::make('3. التكوين المكمل')->icon(Heroicon::OutlinedBookOpen)->schema([
                        $rename('training'),
                        $help('training'),
                        Grid::make(3)->schema([
                            $mode('training'),
                            $num('rules.training.per_item', 'النقاط لكل وحدة'),
                            TextInput::make('rules.training.unit_label')->label('الوحدة')->placeholder('سداسي / تكوين / شهادة')->required(),
                        ]),
                    ]),

                    Tab::make('4. الأشغال المنجزة')->icon(Heroicon::OutlinedBeaker)->schema([
                        $rename('research'),
                        $help('research'),
                        Grid::make(3)->schema([
                            $mode('research'),
                            $num('rules.research.per_item', 'النقاط لكل وحدة'),
                            TextInput::make('rules.research.unit_label')->label('الوحدة')->placeholder('عمل منشور / بحث / مقال')->required(),
                        ]),
                    ]),

                    Tab::make('5. الخبرة المهنية')->icon(Heroicon::OutlinedBriefcase)->schema([
                        $rename('experience'),
                        $help('experience'),
                        Grid::make(2)->schema([
                            Select::make('rules.experience.combine')
                                ->label('طريقة جمع أنواع الخبرة')
                                ->options([
                                    'best' => 'لا تُجمع: يُعتمد النوع الأكثر نقاطاً (القاعدة العامة)',
                                    'sum' => 'تُجمع كل الأنواع في حدود الحد الأقصى',
                                ])
                                ->native(false)->required(),
                            Toggle::make('rules.experience.count_months')->label('احتساب الأشهر كجزء من السنة')->inline(false),
                        ]),
                        Repeater::make('rules.experience.types')
                            ->label('أنواع الخبرة (لكل نوع نقاطه وسقفه الخاص)')
                            ->schema([
                                $key(),
                                TextInput::make('label')->label('نوع الخبرة')->required()->columnSpan(2),
                                $num('per_year', 'النقاط لكل سنة'),
                                TextInput::make('max')->label('سقف هذا النوع')->numeric()->minValue(0)->step(0.25)->placeholder('= الحد الأقصى'),
                            ])
                            ->columns(4)
                            ->addActionLabel('إضافة نوع خبرة'),
                    ]),

                    Tab::make('6. أقدمية الشهادة')->icon(Heroicon::OutlinedCalendarDays)->schema([
                        $rename('seniority'),
                        $help('seniority'),
                        $num('rules.seniority.per_year', 'النقاط لكل سنة كاملة'),
                    ]),

                    Tab::make('7. المقابلة')->icon(Heroicon::OutlinedChatBubbleLeftRight)->schema([
                        $rename('interview'),
                        $help('interview'),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('السلم / نوع المسابقة')->searchable()->weight('bold')->wrap()
                    ->description(fn (ScoringProfile $record): ?string => Str::limit((string) $record->legal_reference, 110)),
                TextColumn::make('sector')->label('القطاع')->badge()->color('gray'),
                TextColumn::make('max_total')->label('المجموع')->alignCenter()->badge()->color('info')
                    ->state(fn (ScoringProfile $record): string => ScoreCalculator::format($record->maxTotal()).' نقطة'),
                TextColumn::make('competitions_count')->counts('competitions')->label('المسابقات')->alignCenter(),
                IconColumn::make('is_default')->label('افتراضي')->boolean()->alignCenter(),
            ])
            ->defaultSort('sort')
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('duplicate')
                        ->label('نسخ (لإنشاء سلم جديد مشابه)')
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->action(function (ScoringProfile $record) {
                            $copy = $record->replicate(['slug', 'is_default', 'is_builtin']);
                            $copy->name = $record->name.' (نسخة)';
                            $copy->is_default = false;
                            $copy->is_builtin = false;
                            $copy->sort = (int) ScoringProfile::max('sort') + 1;
                            $copy->save();

                            Notification::make()->title('تم إنشاء نسخة يمكنك تعديلها')->success()->send();

                            return redirect(static::getUrl('edit', ['record' => $copy]));
                        }),
                    Action::make('makeDefault')
                        ->label('تعيين كسلم افتراضي')
                        ->icon(Heroicon::OutlinedStar)
                        ->hidden(fn (ScoringProfile $record): bool => $record->is_default)
                        ->action(fn (ScoringProfile $record) => $record->update(['is_default' => true])),
                    DeleteAction::make()
                        ->hidden(fn (ScoringProfile $record): bool => $record->is_default)
                        ->modalDescription('المسابقات المرتبطة بهذا السلم ستستعمل السلم الافتراضي.'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScoringProfiles::route('/'),
            'create' => CreateScoringProfile::route('/create'),
            'edit' => EditScoringProfile::route('/{record}/edit'),
        ];
    }
}
