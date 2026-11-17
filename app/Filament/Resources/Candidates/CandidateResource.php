<?php

namespace App\Filament\Resources\Candidates;

use App\Filament\Forms\ScoringFields;
use App\Models\Candidate;
use App\Models\Competition;
use App\Services\ScoreCalculator;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CandidateResource extends Resource
{
    protected static ?string $model = Candidate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'إدارة المسابقات';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'مترشح';

    protected static ?string $pluralModelLabel = 'المترشحون';

    protected static ?string $recordTitleAttribute = 'last_name';

    /** @var array<int, Competition|null> */
    private static array $competitions = [];

    protected static function competition(mixed $id): ?Competition
    {
        if (! $id) {
            return null;
        }

        return static::$competitions[(int) $id] ??= Competition::with('scoringProfile')->find($id);
    }

    public static function referenceDate(): \Closure
    {
        return function (Get $get) {
            $competition = static::competition($get('competition_id'));

            return $competition?->reference_date ?? $competition?->exam_date;
        };
    }

    /** حاسبة سلم التنقيط الخاص بالمسابقة المختارة في الاستمارة */
    public static function calculator(): \Closure
    {
        return fn (Get $get, string $up = ''): ScoreCalculator => static::competition($get($up.'competition_id'))?->calculator() ?? ScoreCalculator::make();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'xl' => 3])
                ->columnSpanFull()
                ->schema([
                    Group::make()
                        ->columnSpan(['default' => 1, 'xl' => 2])
                        ->schema([
                            Section::make('المعلومات الشخصية')
                                ->icon(Heroicon::OutlinedIdentification)
                                ->columns(2)
                                ->schema([
                                    Select::make('competition_id')
                                        ->label('المسابقة')
                                        ->relationship('competition', 'title')
                                        ->default(fn () => request()->integer('competition') ?: null)
                                        ->searchable()->preload()->required()
                                        ->live()
                                        ->columnSpanFull(),
                                    TextInput::make('last_name')->label('اللقب')->required()->maxLength(100),
                                    TextInput::make('first_name')->label('الاسم')->required()->maxLength(100),
                                    DatePicker::make('birth_date')->label('تاريخ الميلاد')->native(false)->displayFormat('d/m/Y')
                                        ->maxDate(now()->subYears(Candidate::MINIMUM_AGE))
                                        ->validationMessages(['before_or_equal' => 'يجب أن يكون سن المترشح '.Candidate::MINIMUM_AGE.' سنة على الأقل (المادة 75 من الأمر 06-03).']),
                                    TextInput::make('birth_place')->label('مكان الميلاد'),
                                    Select::make('gender')->label('الجنس')->options(['M' => 'ذكر', 'F' => 'أنثى'])->native(false),
                                    TextInput::make('registration_number')->label('رقم التسجيل'),
                                    TextInput::make('phone')->label('رقم الهاتف')->tel(),
                                    TextInput::make('address')->label('العنوان'),
                                    Select::make('family_status')->label('الوضعية العائلية')
                                        ->options(ScoreCalculator::familyStatusOptions())->native(false)
                                        ->helperText('تُستعمل للفصل بين المتساوين في النقاط.'),
                                    Grid::make(2)->schema([
                                        Toggle::make('is_disabled')->label('شخص معوق قادر على أداء مهام الرتبة')->inline(false),
                                        Toggle::make('is_martyr_rights')->label('من ذوي الحقوق (ابن أو ابنة شهيد)')->inline(false),
                                    ]),
                                ]),

                            Section::make('الشهادة')
                                ->icon(Heroicon::OutlinedDocumentText)
                                ->columns(3)
                                ->schema([
                                    TextInput::make('diploma')->label('الشهادة')->placeholder('ليسانس / ماستر / مهندس دولة ...'),
                                    TextInput::make('specialty')->label('التخصص'),
                                    TextInput::make('university')->label('الجامعة / المؤسسة المانحة'),
                                ]),

                            ...ScoringFields::make(static::calculator(), static::referenceDate()),

                            Section::make('ملاحظات')
                                ->collapsed()
                                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
                        ]),

                    Group::make()
                        ->columnSpan(1)
                        ->extraAttributes(['class' => 'score-sticky'])
                        ->schema([
                            ScoringFields::summary(static::calculator(), static::referenceDate()),
                            View::make('filament.profile-card')
                                ->viewData(fn (Get $get): array => ['profile' => static::competition($get('competition_id'))?->scoringProfile]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $score = fn ($state) => rtrim(rtrim(number_format((float) $state, 2), '0'), '.');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('competition'))
            ->columns([
                TextColumn::make('rank_position')->label('الترتيب')->alignCenter()->badge()
                    ->state(fn (Candidate $record): string => (string) ($record->rank ?? 'مقصى'))
                    ->color(fn ($state) => match ((int) $state) {
                        1 => 'warning', 2, 3 => 'info', default => 'gray'
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('total', $direction === 'asc' ? 'desc' : 'asc')),
                TextColumn::make('last_name')->label('اللقب والاسم')
                    ->formatStateUsing(fn (Candidate $record) => $record->full_name)
                    ->description(fn (Candidate $record) => $record->specialty)
                    ->searchable(['last_name', 'first_name', 'registration_number'])
                    ->weight('bold'),
                TextColumn::make('competition.title')->label('المسابقة')->toggleable(isToggledHiddenByDefault: true)->limit(30)->tooltip(fn ($state) => $state),
                TextColumn::make('birth_date')->label('تاريخ الميلاد')->date('d/m/Y')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('average')->label('المعدل')->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('score_specialty')->label('التخصص')->alignCenter()->formatStateUsing($score)->toggleable(),
                TextColumn::make('score_path')->label('المسار')->alignCenter()->formatStateUsing($score)->toggleable(),
                TextColumn::make('score_training')->label('التكوين')->alignCenter()->formatStateUsing($score)->toggleable(),
                TextColumn::make('score_research')->label('البحث')->alignCenter()->formatStateUsing($score)->toggleable(),
                TextColumn::make('score_experience')->label('الخبرة')->alignCenter()->formatStateUsing($score)->toggleable(),
                TextColumn::make('score_seniority')->label('الأقدمية')->alignCenter()->formatStateUsing($score)->toggleable(),
                TextColumn::make('score_interview')->label('المقابلة')->alignCenter()->formatStateUsing($score)->toggleable(),
                TextColumn::make('total')->label('المجموع')->alignCenter()->sortable()
                    ->badge()->size('lg')->weight('bold')
                    ->formatStateUsing(fn ($state, Candidate $record) => number_format($state, 2).' / '.ScoreCalculator::format($record->max_total))
                    ->color(fn (Candidate $record) => $record->percentage >= 66 ? 'success' : ($record->percentage >= 50 ? 'warning' : 'danger')),
                TextColumn::make('result')->label('النتيجة')->alignCenter()->badge()
                    ->state(fn (Candidate $record): string => $record->resultLabel())
                    ->color(fn ($state) => match (true) {
                        $state === 'ناجح' => 'success', $state === 'قائمة الانتظار' => 'warning', str_starts_with($state, 'مقصى') => 'danger', default => 'gray'
                    }),
            ])
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('total')->orderByRaw("COALESCE(birth_date, '9999-12-31') asc")->orderBy('id'))
            ->filters([
                SelectFilter::make('competition_id')
                    ->label('المسابقة')
                    ->relationship('competition', 'title')
                    ->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make()->iconButton(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCandidates::route('/'),
            'create' => Pages\CreateCandidate::route('/create'),
            'edit' => Pages\EditCandidate::route('/{record}/edit'),
        ];
    }
}
