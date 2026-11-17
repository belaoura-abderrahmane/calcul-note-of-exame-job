<?php

namespace App\Models;

use App\Services\ScoreCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Candidate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'birth_date' => 'date',
        'diploma_date' => 'date',
        'is_grande_ecole' => 'boolean',
        'is_major' => 'boolean',
        'is_disabled' => 'boolean',
        'is_martyr_rights' => 'boolean',
        'interview_absent' => 'boolean',
        'experiences' => 'array',
        'interview_details' => 'array',
        'specialty_match' => 'float',
        'average' => 'float',
        'interview' => 'float',
        'training_points' => 'float',
        'research_points' => 'float',
        'seniority_years_override' => 'float',
        'score_specialty' => 'float',
        'score_path' => 'float',
        'score_training' => 'float',
        'score_research' => 'float',
        'score_experience' => 'float',
        'score_seniority' => 'float',
        'score_interview' => 'float',
        'total' => 'float',
        'max_total' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(fn (Candidate $c) => $c->computeScores());
    }

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->last_name} {$this->first_name}");
    }

    public function getPercentageAttribute(): float
    {
        return $this->max_total > 0 ? round(($this->total / $this->max_total) * 100, 2) : 0;
    }

    public function computeScores(): void
    {
        $calculator = $this->competition?->calculator() ?? ScoreCalculator::make();

        $result = $calculator->calculate([
            ...$this->only([
                'specialty_match', 'average', 'master_mention', 'is_grande_ecole', 'is_major',
                'trainings_count', 'research_count', 'training_points', 'research_points', 'experiences', 'seniority_years_override', 'interview', 'interview_details', 'interview_absent',
            ]),
            'diploma_date' => $this->diploma_date,
            'reference_date' => $this->competition?->reference_date ?? $this->competition?->exam_date,
        ]);

        unset($result['percentage']);
        $this->forceFill($result);

        // العلامة الإجمالية للمقابلة تبقى مطابقة لمجموع عناصرها
        $this->interview = $result['score_interview'];
    }

    /** السن الأدنى للالتحاق بوظيفة عمومية (المادة 75 من الأمر رقم 06-03) */
    public const MINIMUM_AGE = 18;

    /**
     * سبب إقصاء المترشح من الترتيب حسب القانون، أو null إذا كان مقبولاً.
     * - الغياب عن المقابلة (المادة 6 من القرار المنشور في الجريدة الرسمية عدد 13 لسنة 2020)؛
     * - السن أقل من 18 سنة في تاريخ المسابقة (المادة 75 من الأمر رقم 06-03)؛
     * - الشهادة متحصل عليها بعد تاريخ فتح المسابقة.
     */
    public function exclusionReason(): ?string
    {
        if ($this->interview_absent) {
            return 'غائب عن المقابلة';
        }

        $referenceDate = $this->competition?->reference_date ?? $this->competition?->exam_date ?? now();

        if ($this->birth_date && $this->birth_date->diffInYears($referenceDate) < self::MINIMUM_AGE) {
            return 'السن أقل من '.self::MINIMUM_AGE.' سنة في تاريخ المسابقة';
        }

        if ($this->diploma_date && $this->diploma_date->greaterThan($referenceDate)) {
            return 'الشهادة بعد تاريخ فتح المسابقة';
        }

        return null;
    }

    public function isExcluded(): bool
    {
        return $this->exclusionReason() !== null;
    }

    /**
     * النتيجة حسب الرتبة: ناجح في حدود عدد المناصب، ثم القائمة الاحتياطية بنفس العدد، والباقي غير ناجح.
     */
    public function resultLabel(): string
    {
        $positions = (int) ($this->competition?->positions ?? 0);

        return match (true) {
            $this->isExcluded() => 'مقصى: '.$this->exclusionReason(),
            $this->rank !== null && $this->rank <= $positions => 'ناجح',
            $this->rank !== null && $this->rank <= 2 * $positions => 'قائمة الانتظار',
            default => 'غير ناجح',
        };
    }

    /** الترتيب داخل المسابقة (null للمقصى) */
    public function getRankAttribute(): ?int
    {
        return $this->competition?->rankPositions()[$this->id] ?? null;
    }
}
