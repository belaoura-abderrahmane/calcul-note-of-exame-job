<?php

namespace App\Models;

use App\Services\ScoreCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competition extends Model
{
    protected $fillable = [
        'scoring_profile_id', 'title', 'institution', 'grade', 'required_diploma', 'required_specialty',
        'positions', 'reference_date', 'exam_date', 'status', 'notes',
    ];

    protected $casts = [
        'reference_date' => 'date',
        'exam_date' => 'date',
        'positions' => 'integer',
    ];

    public const STATUSES = [
        'open' => 'مفتوحة',
        'closed' => 'مغلقة',
    ];

    protected static function booted(): void
    {
        static::creating(function (Competition $competition): void {
            $competition->scoring_profile_id ??= ScoringProfile::default()?->getKey();
        });
    }

    public function scoringProfile(): BelongsTo
    {
        return $this->belongsTo(ScoringProfile::class);
    }

    /** @return array<string, mixed> قواعد سلم التنقيط الخاص بهذه المسابقة */
    public function scoringRules(): array
    {
        return $this->scoringProfile?->fullRules() ?? ScoringProfile::defaultRules();
    }

    public function calculator(): ScoreCalculator
    {
        return ScoreCalculator::make($this->scoringRules());
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /** @var array<int, int|null>|null */
    protected ?array $rankPositionsCache = null;

    /**
     * المترشحون مرتبون: النقاط تنازلياً، ثم معايير الفصل بين المتساوين حسب سلم التنقيط،
     * والمتغيبون عن المقابلة (المقصون) في آخر القائمة.
     *
     * @return Collection<int, Candidate>
     */
    public function rankedCandidates(): Collection
    {
        $tieBreakers = $this->calculator()->tieBreakers();
        $family = array_flip(array_keys(ScoreCalculator::familyStatusOptions()));

        return $this->candidates()->get()->each->setRelation('competition', $this)->sort(function (Candidate $a, Candidate $b) use ($tieBreakers, $family): int {
            $comparisons = [
                (int) $a->isExcluded() <=> (int) $b->isExcluded(),
                (float) $b->total <=> (float) $a->total,
            ];

            foreach ($tieBreakers as $key) {
                $comparisons[] = match ($key) {
                    // الأكبر سناً أولاً، وبدون تاريخ في الأخير
                    'age' => ($a->birth_date?->format('Y-m-d') ?? '9999-12-31') <=> ($b->birth_date?->format('Y-m-d') ?? '9999-12-31'),
                    'family' => ($family[$a->family_status] ?? 99) <=> ($family[$b->family_status] ?? 99),
                    'disabled' => (int) $b->is_disabled <=> (int) $a->is_disabled,
                    'rights' => (int) $b->is_martyr_rights <=> (int) $a->is_martyr_rights,
                    'path' => [(float) $b->score_path, (float) $b->average] <=> [(float) $a->score_path, (float) $a->average],
                    'interview' => (float) $b->score_interview <=> (float) $a->score_interview,
                    'experience' => (float) $b->score_experience <=> (float) $a->score_experience,
                    'diploma_date' => ($a->diploma_date?->format('Y-m-d') ?? '9999-12-31') <=> ($b->diploma_date?->format('Y-m-d') ?? '9999-12-31'),
                    default => 0,
                };
            }

            $comparisons[] = $a->id <=> $b->id;

            foreach ($comparisons as $result) {
                if ($result !== 0) {
                    return $result;
                }
            }

            return 0;
        })->values();
    }

    /**
     * رتبة كل مترشح (المقصى بسبب الغياب = null).
     *
     * @return array<int, int|null>
     */
    public function rankPositions(): array
    {
        if ($this->rankPositionsCache !== null) {
            return $this->rankPositionsCache;
        }

        $position = 0;

        return $this->rankPositionsCache = $this->rankedCandidates()
            ->mapWithKeys(function (Candidate $candidate) use (&$position): array {
                return [$candidate->id => $candidate->isExcluded() ? null : ++$position];
            })
            ->all();
    }

    /** إعادة حساب نقاط كل المترشحين (بعد تغيير الإعدادات أو التاريخ المرجعي) */
    public function recalculate(): int
    {
        $count = 0;
        $this->candidates()->each(function (Candidate $c) use (&$count) {
            $c->computeScores();
            $c->saveQuietly();
            $count++;
        });

        return $count;
    }
}
