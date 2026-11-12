<?php

namespace App\Services;

use App\Models\ScoringProfile;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * محرك حساب نقاط مسابقات التوظيف على أساس الشهادة.
 * المجموع الأقصى = مجموع الحدود القصوى للمعايير في سلم التنقيط المختار (30 للسلم العام، 33 لسلم الأساتذة...).
 */
class ScoreCalculator
{
    /**
     * @param  array<string, mixed>|null  $rules  قواعد سلم التنقيط (null = السلم الافتراضي)
     */
    public function __construct(protected ?array $rules = null)
    {
        $this->rules = $this->rules === null ? ScoringProfile::defaultRules() : ScoringProfile::completeRules($this->rules);
    }

    public static function make(?array $rules = null): static
    {
        return new static($rules);
    }

    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * @param  array  $input  specialty_match, average, master_mention, is_grande_ecole, is_major,
     *                        trainings_count, research_count, experiences[], diploma_date,
     *                        reference_date, seniority_years_override, interview
     */
    public function calculate(array $input): array
    {
        $scores = [
            'score_specialty' => $this->specialty($input['specialty_match'] ?? 0),
            'score_path' => $this->path(
                $input['average'] ?? null,
                $input['master_mention'] ?? null,
                (bool) ($input['is_grande_ecole'] ?? false),
                (bool) ($input['is_major'] ?? false),
            ),
            'score_training' => $this->countOrManual('training', $input['trainings_count'] ?? 0, $input['training_points'] ?? null),
            'score_research' => $this->countOrManual('research', $input['research_count'] ?? 0, $input['research_points'] ?? null),
            'score_experience' => $this->experience($input['experiences'] ?? []),
            'score_seniority' => $this->seniority(
                $input['diploma_date'] ?? null,
                $input['reference_date'] ?? null,
                $input['seniority_years_override'] ?? null,
            ),
            'score_interview' => $this->clamp($input['interview'] ?? 0, $this->rules['interview']['max']),
        ];

        $total = round(array_sum($scores), 2);
        $maxTotal = $this->maxTotal();

        return $scores + [
            'total' => $total,
            'max_total' => $maxTotal,
            'percentage' => $maxTotal > 0 ? round($total / $maxTotal * 100, 2) : 0,
        ];
    }

    public function specialty(mixed $value): float
    {
        return $this->clamp($value, $this->rules['specialty']['max']);
    }

    /** نقاط المعدل وحده حسب السلم */
    public function averagePoints(mixed $average): float
    {
        if ($average === null || $average === '') {
            return 0;
        }

        $average = (float) $average;
        $bands = collect($this->rules['path']['bands'])->sortByDesc('min');

        foreach ($bands as $band) {
            if ($average >= (float) $band['min']) {
                return (float) $band['points'];
            }
        }

        return 0;
    }

    public function mentionBonus(?string $mention): float
    {
        if (! $mention) {
            return 0;
        }

        $row = collect($this->rules['path']['mentions'])->firstWhere('key', $mention);

        return (float) ($row['bonus'] ?? 0);
    }

    /**
     * المسار الدراسي: نقاط المعدل + علاوة التقدير (+ علاوات المدرسة العليا والأول على الدفعة).
     * إذا كانت bonus_outside_cap مفعلة (سلم الأساتذة) تُضاف العلاوتان الأخيرتان خارج سقف المعيار.
     */
    public function path(mixed $average, ?string $mention, bool $grandeEcole, bool $major): float
    {
        $p = $this->rules['path'];
        $extra = ($grandeEcole ? (float) $p['grande_ecole_bonus'] : 0) + ($major ? (float) $p['major_bonus'] : 0);
        $base = $this->averagePoints($average) + $this->mentionBonus($mention);

        if (! empty($p['bonus_outside_cap'])) {
            return round($this->clamp($base, $p['max']) + max(0, $extra), 2);
        }

        return $this->clamp($base + $extra, $p['max']);
    }

    /** التكوين أو الأشغال: بالعدد × النقاط لكل وحدة، أو نقاط تُدخل يدوياً (mode = manual) */
    public function countOrManual(string $criterion, mixed $count, mixed $manualPoints): float
    {
        if (($this->rules[$criterion]['mode'] ?? 'count') === 'manual') {
            return $this->clamp($manualPoints ?? 0, $this->rules[$criterion]['max']);
        }

        return $this->perItem($criterion, $count);
    }

    public function isManual(string $criterion): bool
    {
        return ($this->rules[$criterion]['mode'] ?? 'count') === 'manual';
    }

    public function perItem(string $criterion, mixed $count): float
    {
        $r = $this->rules[$criterion];

        return $this->clamp(max(0, (int) $count) * (float) $r['per_item'], $r['max']);
    }

    /** نقاط سطر خبرة واحد، في حدود سقف نوعه ثم سقف المعيار */
    public function experienceLine(?string $type, mixed $years, mixed $months = 0): float
    {
        $r = $this->rules['experience'];
        $row = collect($r['types'])->firstWhere('key', $type);

        if (! $row) {
            return 0;
        }

        $duration = max(0, (float) $years);
        if (! empty($r['count_months'])) {
            $duration += max(0, min(11, (int) $months)) / 12;
        }

        $typeMax = (isset($row['max']) && $row['max'] !== '' && $row['max'] !== null) ? (float) $row['max'] : (float) $r['max'];

        return $this->clamp($duration * (float) $row['per_year'], min($typeMax, (float) $r['max']));
    }

    /**
     * نقاط الخبرة المهنية.
     * combine = best: الأنواع لا تُجمع، يُعتمد النوع الأكثر نقاطاً (القاعدة العامة).
     * combine = sum: تُجمع نقاط كل الأنواع في حدود سقف المعيار.
     *
     * @param  array<int, array{type?: string, years?: mixed, months?: mixed}>|null  $experiences
     */
    public function experience(?array $experiences): float
    {
        $perType = [];

        foreach ($experiences ?? [] as $line) {
            $type = $line['type'] ?? null;
            if (! $type) {
                continue;
            }
            // عدة أسطر من نفس النوع تُجمع مدتها أولاً
            $perType[$type]['years'] = ($perType[$type]['years'] ?? 0) + max(0, (float) ($line['years'] ?? 0));
            $perType[$type]['months'] = ($perType[$type]['months'] ?? 0) + max(0, min(11, (int) ($line['months'] ?? 0)));
        }

        $points = collect($perType)->map(function (array $d, string $type): float {
            $years = $d['years'] + intdiv($d['months'], 12);

            return $this->experienceLine($type, $years, $d['months'] % 12);
        });

        $result = ($this->rules['experience']['combine'] ?? 'best') === 'sum' ? $points->sum() : (float) $points->max();

        return $this->clamp($result, $this->rules['experience']['max']);
    }

    /**
     * مدة أقدمية الشهادة (سنوات كاملة + أشهر كاملة متبقية) بين تاريخ الشهادة والتاريخ المرجعي.
     *
     * @return array{years: int, months: int}
     */
    public function seniorityDuration(mixed $diplomaDate, mixed $referenceDate = null): array
    {
        if (! $diplomaDate) {
            return ['years' => 0, 'months' => 0];
        }

        $from = $diplomaDate instanceof CarbonInterface ? $diplomaDate : Carbon::parse($diplomaDate);
        $to = $referenceDate
            ? ($referenceDate instanceof CarbonInterface ? $referenceDate : Carbon::parse($referenceDate))
            : now();

        if ($from->greaterThan($to)) {
            return ['years' => 0, 'months' => 0];
        }

        $totalMonths = (int) $from->diffInMonths($to);

        return ['years' => intdiv($totalMonths, 12), 'months' => $totalMonths % 12];
    }

    /** عدد السنوات الكاملة بين تاريخ الشهادة والتاريخ المرجعي */
    public function seniorityYears(mixed $diplomaDate, mixed $referenceDate = null): int
    {
        return $this->seniorityDuration($diplomaDate, $referenceDate)['years'];
    }

    /**
     * نقاط أقدمية الشهادة: per_year عن كل سنة كاملة + per_month عن كل شهر متبقٍّ، في حدود السقف.
     * القيمة اليدوية (إن وُجدت) عدد سنوات قد يكون عشرياً.
     */
    public function seniority(mixed $diplomaDate, mixed $referenceDate = null, mixed $overrideYears = null): float
    {
        $r = $this->rules['seniority'];

        if ($overrideYears !== null && $overrideYears !== '') {
            return $this->clamp((float) $overrideYears * (float) $r['per_year'], $r['max']);
        }

        $duration = $this->seniorityDuration($diplomaDate, $referenceDate);
        $points = $duration['years'] * (float) $r['per_year'] + $duration['months'] * (float) ($r['per_month'] ?? 0);

        return $this->clamp($points, $r['max']);
    }

    /** وصف مقروء للمدة ونقاطها، مثل: «6 سنوات و 3 أشهر ← 3.12 نقطة» */
    public function seniorityDescription(mixed $diplomaDate, mixed $referenceDate = null): string
    {
        $duration = $this->seniorityDuration($diplomaDate, $referenceDate);
        $text = 'المدة: '.$duration['years'].' سنة';
        if ((float) ($this->rules['seniority']['per_month'] ?? 0) > 0) {
            $text .= ' و '.$duration['months'].' شهر';
        }

        return $text.' ← '.static::format($this->seniority($diplomaDate, $referenceDate)).' نقطة';
    }

    // ---------------------------------------------------------------

    public function maxTotal(): float
    {
        return round(array_sum($this->maxima()), 2);
    }

    /** @return array<string, string> مستويات مطابقة التخصص (النقاط => الوصف) */
    public function specialtyLevelOptions(): array
    {
        return collect($this->rules['specialty']['levels'] ?? [])
            ->filter(fn ($level): bool => filled($level['label'] ?? null))
            ->mapWithKeys(fn (array $level): array => [
                (string) (float) $level['points'] => $level['label'].' ('.static::format((float) $level['points']).')',
            ])
            ->all();
    }

    public function help(string $criterion): ?string
    {
        return $this->rules[$criterion]['help'] ?? null;
    }

    public function unitLabel(string $criterion, string $fallback): string
    {
        return filled($this->rules[$criterion]['unit_label'] ?? null) ? $this->rules[$criterion]['unit_label'] : $fallback;
    }

    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.') ?: '0';
    }

    public function mentionOptions(): array
    {
        return collect($this->rules['path']['mentions'])->pluck('label', 'key')->all();
    }

    public function experienceTypeOptions(): array
    {
        return collect($this->rules['experience']['types'])->pluck('label', 'key')->all();
    }

    /** الحد الأقصى لكل معيار */
    public function maxima(): array
    {
        return [
            'score_specialty' => (float) $this->rules['specialty']['max'],
            'score_path' => (float) $this->rules['path']['max'] + (! empty($this->rules['path']['bonus_outside_cap'])
                ? (float) $this->rules['path']['grande_ecole_bonus'] + (float) $this->rules['path']['major_bonus']
                : 0),
            'score_training' => (float) $this->rules['training']['max'],
            'score_research' => (float) $this->rules['research']['max'],
            'score_experience' => (float) $this->rules['experience']['max'],
            'score_seniority' => (float) $this->rules['seniority']['max'],
            'score_interview' => (float) $this->rules['interview']['max'],
        ];
    }

    /**
     * أسماء المعايير في هذا السلم (يمكن للسلم أن يعيد تسمية معيار، مثل "المنشورات العلمية").
     *
     * @return array<string, string>
     */
    public function criterionLabels(): array
    {
        $map = ['score_specialty' => 'specialty', 'score_path' => 'path', 'score_training' => 'training', 'score_research' => 'research',
            'score_experience' => 'experience', 'score_seniority' => 'seniority', 'score_interview' => 'interview'];

        return collect(static::labels())
            ->map(fn (string $label, string $key): string => filled($this->rules[$map[$key]]['label'] ?? null) ? $this->rules[$map[$key]]['label'] : $label)
            ->all();
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'score_specialty' => 'مطابقة التخصص',
            'score_path' => 'المسار الدراسي',
            'score_training' => 'التكوين التكميلي',
            'score_research' => 'البحث العلمي',
            'score_experience' => 'الخبرة المهنية',
            'score_seniority' => 'أقدمية الشهادة',
            'score_interview' => 'المقابلة الشفهية',
        ];
    }

    protected function clamp(mixed $value, mixed $max): float
    {
        return round(max(0, min((float) $max, (float) $value)), 2);
    }
}
