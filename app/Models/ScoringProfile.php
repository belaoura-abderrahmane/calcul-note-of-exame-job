<?php

namespace App\Models;

use App\Services\ScoreCalculator;
use App\Support\ScoringPresets;
use Database\Factories\ScoringProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * سلم تنقيط (نوع مسابقة): يحدد معايير الحساب وشرحها القانوني.
 */
class ScoringProfile extends Model
{
    /** @use HasFactory<ScoringProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'sector', 'legal_reference', 'description', 'rules', 'is_default', 'is_builtin', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_default' => 'boolean',
            'is_builtin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ScoringProfile $profile): void {
            $profile->slug = $profile->slug ?: Str::slug($profile->name).'-'.Str::lower(Str::random(5));
        });

        static::saved(function (ScoringProfile $profile): void {
            if ($profile->is_default) {
                static::query()->whereKeyNot($profile->getKey())->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    public static function default(): ?self
    {
        return static::query()->orderByDesc('is_default')->orderBy('sort')->orderBy('id')->first();
    }

    /**
     * القواعد الكاملة: قواعد السلم مكمّلة بالقيم الافتراضية لأي مفتاح ناقص.
     *
     * @return array<string, mixed>
     */
    public function fullRules(): array
    {
        return static::completeRules($this->rules ?? []);
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    public static function completeRules(array $rules): array
    {
        $base = ScoringPresets::baseRules();

        foreach ($base as $criterion => $defaults) {
            $rules[$criterion] = array_replace($defaults, $rules[$criterion] ?? []);
        }

        return $rules;
    }

    /**
     * قواعد السلم الافتراضي (أو السلم العام إن لم توجد سلالم في قاعدة البيانات).
     *
     * @return array<string, mixed>
     */
    public static function defaultRules(): array
    {
        try {
            $profile = static::default();
        } catch (\Throwable) {
            $profile = null;
        }

        return $profile ? $profile->fullRules() : ScoringPresets::baseRules();
    }

    public function calculator(): ScoreCalculator
    {
        return ScoreCalculator::make($this->fullRules());
    }

    public function maxTotal(): float
    {
        return $this->calculator()->maxTotal();
    }

    /** إعادة حساب نقاط كل المترشحين في المسابقات التي تستعمل هذا السلم */
    public function recalculate(): int
    {
        return $this->competitions()->get()->sum(fn (Competition $competition): int => $competition->recalculate());
    }
}
