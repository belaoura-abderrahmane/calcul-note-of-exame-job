<?php

namespace App\Services;

use App\Models\ScoringSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * محرك حساب نقاط مسابقة التوظيف على أساس الشهادة (من 30 نقطة).
 */
class ScoreCalculator
{
    public const MAX_TOTAL = 30;

    public function __construct(protected ?array $rules = null)
    {
        $this->rules ??= ScoringSetting::rules();
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
            'score_training' => $this->perItem('training', $input['trainings_count'] ?? 0),
            'score_research' => $this->perItem('research', $input['research_count'] ?? 0),
            'score_experience' => $this->experience($input['experiences'] ?? []),
            'score_seniority' => $this->seniority(
                $input['diploma_date'] ?? null,
                $input['reference_date'] ?? null,
                $input['seniority_years_override'] ?? null,
            ),
            'score_interview' => $this->clamp($input['interview'] ?? 0, $this->rules['interview']['max']),
        ];

        $total = round(array_sum($scores), 2);

        return $scores + [
            'total' => $total,
            'percentage' => round($total / self::MAX_TOTAL * 100, 2),
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

    public function path(mixed $average, ?string $mention, bool $grandeEcole, bool $major): float
    {
        $p = $this->rules['path'];

        $points = $this->averagePoints($average)
            + $this->mentionBonus($mention)
            + ($grandeEcole ? (float) $p['grande_ecole_bonus'] : 0)
            + ($major ? (float) $p['major_bonus'] : 0);

        return $this->clamp($points, $p['max']);
    }

    public function perItem(string $criterion, mixed $count): float
    {
        $r = $this->rules[$criterion];

        return $this->clamp(max(0, (int) $count) * (float) $r['per_item'], $r['max']);
    }

    /** نقاط كل سطر خبرة */
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

        return $this->clamp($duration * (float) $row['per_year'], $r['max']);
    }

    /** الخبرات لا تُجمع: يُعتمد السطر الذي يمنح أكبر عدد من النقاط */
    public function experience(?array $experiences): float
    {
        $best = 0;

        foreach ($experiences ?? [] as $line) {
            $best = max($best, $this->experienceLine(
                $line['type'] ?? null,
                $line['years'] ?? 0,
                $line['months'] ?? 0,
            ));
        }

        return round($best, 2);
    }

    /** عدد السنوات الكاملة بين تاريخ الشهادة والتاريخ المرجعي */
    public function seniorityYears(mixed $diplomaDate, mixed $referenceDate = null): int
    {
        if (! $diplomaDate) {
            return 0;
        }

        $from = $diplomaDate instanceof CarbonInterface ? $diplomaDate : Carbon::parse($diplomaDate);
        $to = $referenceDate
            ? ($referenceDate instanceof CarbonInterface ? $referenceDate : Carbon::parse($referenceDate))
            : now();

        return $from->greaterThan($to) ? 0 : (int) $from->diffInYears($to);
    }

    public function seniority(mixed $diplomaDate, mixed $referenceDate = null, mixed $overrideYears = null): float
    {
        $r = $this->rules['seniority'];

        $years = ($overrideYears !== null && $overrideYears !== '')
            ? (float) $overrideYears
            : $this->seniorityYears($diplomaDate, $referenceDate);

        return $this->clamp($years * (float) $r['per_year'], $r['max']);
    }

    // ---------------------------------------------------------------

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
            'score_path' => (float) $this->rules['path']['max'],
            'score_training' => (float) $this->rules['training']['max'],
            'score_research' => (float) $this->rules['research']['max'],
            'score_experience' => (float) $this->rules['experience']['max'],
            'score_seniority' => (float) $this->rules['seniority']['max'],
            'score_interview' => (float) $this->rules['interview']['max'],
        ];
    }

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
