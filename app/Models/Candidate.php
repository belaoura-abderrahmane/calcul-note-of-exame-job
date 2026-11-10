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
        'experiences' => 'array',
        'specialty_match' => 'float',
        'average' => 'float',
        'interview' => 'float',
        'seniority_years_override' => 'float',
        'score_specialty' => 'float',
        'score_path' => 'float',
        'score_training' => 'float',
        'score_research' => 'float',
        'score_experience' => 'float',
        'score_seniority' => 'float',
        'score_interview' => 'float',
        'total' => 'float',
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
        return round(($this->total / ScoreCalculator::MAX_TOTAL) * 100, 2);
    }

    public function computeScores(): void
    {
        $result = ScoreCalculator::make()->calculate([
            ...$this->only([
                'specialty_match', 'average', 'master_mention', 'is_grande_ecole', 'is_major',
                'trainings_count', 'research_count', 'experiences', 'seniority_years_override', 'interview',
            ]),
            'diploma_date' => $this->diploma_date,
            'reference_date' => $this->competition?->reference_date ?? $this->competition?->exam_date,
        ]);

        unset($result['percentage']);
        $this->forceFill($result);
    }

    /** الترتيب داخل المسابقة */
    public function getRankAttribute(): ?int
    {
        if (! $this->competition) {
            return null;
        }

        $ids = $this->competition->rankedCandidates()->pluck('id')->values();
        $pos = $ids->search($this->id);

        return $pos === false ? null : $pos + 1;
    }
}
