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
                'trainings_count', 'research_count', 'training_points', 'research_points', 'experiences', 'seniority_years_override', 'interview',
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
