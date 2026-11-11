<?php

namespace App\Models;

use App\Services\ScoreCalculator;
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

    /** المترشحون مرتبون: النقاط تنازلياً، ثم الأكبر سناً */
    public function rankedCandidates()
    {
        return $this->candidates()
            ->orderByDesc('total')
            ->orderByRaw('birth_date IS NULL, birth_date ASC')
            ->orderBy('id')
            ->get();
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
