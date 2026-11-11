<?php

namespace App\Filament\Resources\Competitions;

use App\Filament\Resources\Candidates\CandidateResource;
use App\Models\Competition;
use App\Models\ScoringProfile;
use App\Services\ScoreCalculator;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CompetitionResource extends Resource
{
    protected static ?string $model = Competition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'إدارة المسابقات';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'مسابقة';

    protected static ?string $pluralModelLabel = 'المسابقات';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('نوع المسابقة وسلم التنقيط')
                ->description('يحدد السلم المختار المعايير والحدود القصوى وطريقة الحساب لكل المترشحين في هذه المسابقة.')
                ->icon(Heroicon::OutlinedScale)
                ->columnSpanFull()
                ->schema([
                    Select::make('scoring_profile_id')
                        ->label('سلم التنقيط (نوع المسابقة)')
                        ->relationship('scoringProfile', 'name', fn ($query) => $query->orderBy('sort'))
                        ->getOptionLabelFromRecordUsing(fn (ScoringProfile $record): string => $record->name)
                        ->default(fn (): ?int => ScoringProfile::default()?->getKey())
                        ->searchable()->preload()->required()->native(false)
                        ->live()
                        ->helperText('لإضافة نوع مسابقة جديد أو تعديل القيم: الأدوات ← سلالم التنقيط.'),
                    View::make('filament.profile-card')
                        ->viewData(fn (Get $get): array => ['profile' => ScoringProfile::find($get('scoring_profile_id'))]),
                ]),

            Section::make('معلومات المسابقة')
                ->icon(Heroicon::OutlinedTrophy)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')
                        ->label('عنوان المسابقة')
                        ->placeholder('مسابقة على أساس الشهادة للالتحاق برتبة ...')
                        ->required()->maxLength(255)->columnSpanFull(),
                    TextInput::make('institution')->label('المؤسسة / الإدارة المعنية')->maxLength(255),
                    TextInput::make('grade')->label('الرتبة')->required()->maxLength(255),
                    TextInput::make('required_diploma')->label('الشهادة المطلوبة')->placeholder('ليسانس، ماستر، ...'),
                    TextInput::make('required_specialty')->label('التخصصات المطلوبة'),
                    TextInput::make('positions')->label('عدد المناصب المالية')->integer()->minValue(1)->default(1)->required(),
                    Select::make('status')->label('الحالة')->options(Competition::STATUSES)->default('open')->required()->native(false),
                    DatePicker::make('reference_date')
                        ->label('تاريخ فتح المسابقة')
                        ->helperText('تُحسب أقدمية الشهادة إلى غاية هذا التاريخ (حسب معايير الانتقاء). إن تُرك فارغاً يُستعمل تاريخ إجراء المسابقة ثم تاريخ اليوم.')
                        ->native(false)->displayFormat('d/m/Y'),
                    DatePicker::make('exam_date')->label('تاريخ إجراء المسابقة')->native(false)->displayFormat('d/m/Y'),
                    Textarea::make('notes')->label('ملاحظات')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('المسابقة')->searchable()->sortable()->wrap()->weight('bold'),
                TextColumn::make('grade')->label('الرتبة')->searchable()->badge()->color('gray'),
                TextColumn::make('scoringProfile.name')->label('سلم التنقيط')->limit(35)->tooltip(fn ($state) => $state)->toggleable(),
                TextColumn::make('institution')->label('المؤسسة')->searchable()->toggleable(),
                TextColumn::make('positions')->label('المناصب')->alignCenter()->sortable(),
                TextColumn::make('candidates_count')->counts('candidates')->label('المترشحون')->alignCenter()->badge()->color('info')->sortable(),
                TextColumn::make('candidates_max_total')->max('candidates', 'total')->label('أعلى مجموع')->alignCenter()
                    ->formatStateUsing(fn ($state, Competition $record) => $state ? number_format($state, 2).' / '.ScoreCalculator::format($record->calculator()->maxTotal()) : '-'),
                TextColumn::make('exam_date')->label('تاريخ المسابقة')->date('d/m/Y')->sortable(),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->formatStateUsing(fn ($state) => Competition::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'open' ? 'success' : 'danger'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options(Competition::STATUSES),
            ])
            ->recordActions([
                Action::make('ranking')
                    ->label('الترتيب')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('info')
                    ->url(fn (Competition $record) => CandidateResource::getUrl('index', [
                        'filters' => ['competition_id' => ['value' => $record->id]],
                    ])),
                ActionGroup::make([
                    Action::make('addCandidate')
                        ->label('إضافة مترشح')
                        ->icon(Heroicon::OutlinedUserPlus)
                        ->url(fn (Competition $record) => CandidateResource::getUrl('create', ['competition' => $record->id])),
                    Action::make('print')
                        ->label('طباعة قائمة الترتيب')
                        ->icon(Heroicon::OutlinedPrinter)
                        ->url(fn (Competition $record) => route('competitions.print', $record), shouldOpenInNewTab: true),
                    Action::make('recalculate')
                        ->label('إعادة حساب النقاط')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->requiresConfirmation()
                        ->action(function (Competition $record) {
                            $n = $record->recalculate();
                            Notification::make()->title("تمت إعادة حساب نقاط {$n} مترشح")->success()->send();
                        }),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompetitions::route('/'),
            'create' => Pages\CreateCompetition::route('/create'),
            'edit' => Pages\EditCompetition::route('/{record}/edit'),
        ];
    }
}
