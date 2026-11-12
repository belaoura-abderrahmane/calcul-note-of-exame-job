<?php

namespace Tests\Unit;

use App\Services\ScoreCalculator;
use App\Support\ScoringPresets;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScoreCalculatorTest extends TestCase
{
    private function calc(): ScoreCalculator
    {
        return ScoreCalculator::make(ScoringPresets::baseRules());
    }

    public static function averages(): array
    {
        return [[null, 0], [10.49, 0], [10.50, 1], [10.99, 1], [11.00, 2], [12.5, 3], [13.99, 4], [14.0, 5], [15.99, 6], [16.0, 7], [19.5, 7]];
    }

    #[DataProvider('averages')]
    public function test_average_scale(?float $avg, float $expected): void
    {
        $this->assertSame($expected, $this->calc()->averagePoints($avg));
    }

    public function test_path_is_capped_at_seven_whatever_the_bonuses(): void
    {
        $this->assertSame(7.0, $this->calc()->path(15.5, 'excellent', true, true));
        $this->assertSame(6.0, $this->calc()->path(14.2, 'bien', false, false));
    }

    public function test_experience_types_are_not_summed_and_each_type_has_its_own_cap(): void
    {
        $score = $this->calc()->experience([
            ['type' => 'private', 'years' => 10, 'months' => 0],         // 0.5/سنة، سقف 2
            ['type' => 'other_public', 'years' => 6, 'months' => 0],     // 1/سنة، سقف 4
            ['type' => 'same_institution', 'years' => 2, 'months' => 6], // 2.5
        ]);

        $this->assertSame(4.0, $score);
        $this->assertSame(2.0, $this->calc()->experienceLine('private', 10));
        $this->assertSame(6.0, $this->calc()->experience([['type' => 'same_institution', 'years' => 9]]));
    }

    public function test_lines_of_the_same_experience_type_are_added_before_capping(): void
    {
        $score = $this->calc()->experience([
            ['type' => 'same_institution', 'years' => 1, 'months' => 8],
            ['type' => 'same_institution', 'years' => 1, 'months' => 4],
        ]);

        $this->assertSame(3.0, $score);
    }

    public function test_experience_can_be_summed_across_types_when_the_scale_says_so(): void
    {
        $calc = ScoreCalculator::make(ScoringPresets::higherEducation()['rules']);

        $score = $calc->experience([
            ['type' => 'lectures', 'years' => 2],      // 2
            ['type' => 'td', 'years' => 5],            // 2.5 → سقف 1.5
            ['type' => 'professional', 'years' => 2],  // 1
        ]);

        $this->assertSame(4.5, $score);
    }

    public function test_seniority_counts_years_and_remaining_months_and_is_capped(): void
    {
        // 3 سنوات و 5 أشهر = 3 × 0.5 + 5 × 0.04
        $this->assertSame(1.7, $this->calc()->seniority('2023-01-01', '2026-06-30'));
        $this->assertSame(['years' => 6, 'months' => 0], $this->calc()->seniorityDuration('2020-11-01', '2026-11-12'));
        $this->assertSame(5.0, $this->calc()->seniority('2005-01-01', '2026-01-01'));
        $this->assertSame(0.0, $this->calc()->seniority('2027-01-01', '2026-01-01'));
        $this->assertSame(2.0, $this->calc()->seniority(null, null, 4));
        $this->assertSame(1.7, $this->calc()->seniority('2023-01-01', '2026-06-30', 0), 'القيمة اليدوية 0 لا تلغي الحساب التلقائي');
    }

    public function test_interview_is_the_sum_of_its_items_each_capped(): void
    {
        $calc = $this->calc();

        $this->assertSame(2.5, $calc->interview(0, ['analysis' => 1, 'communication' => 0.5, 'qualifications' => 1]));
        $this->assertSame(2.0, $calc->interview(0, ['analysis' => 5, 'communication' => 1]), 'كل عنصر في حدود نقطته');
        $this->assertSame(2.5, $calc->interview(2.5, null), 'بدون تفصيل تُعتمد العلامة الإجمالية');
        $this->assertSame(3.0, ScoreCalculator::make(ScoringPresets::teachers()['rules'])->interview(3, ['analysis' => 1]), 'سلم بدون عناصر');
    }

    public function test_seniority_counts_full_years_only_when_no_monthly_points(): void
    {
        $calc = ScoreCalculator::make(['seniority' => ['per_year' => 0.5, 'per_month' => 0, 'max' => 5]]);

        $this->assertSame(1.5, $calc->seniority('2023-01-01', '2026-06-30'));
    }

    public function test_full_calculation_max_is_thirty(): void
    {
        $r = $this->calc()->calculate([
            'specialty_match' => 9, 'average' => 18, 'trainings_count' => 10, 'research_count' => 10,
            'experiences' => [['type' => 'same_institution', 'years' => 20]],
            'diploma_date' => '2000-01-01', 'reference_date' => '2026-01-01', 'interview' => 5,
        ]);
        $this->assertSame(30.0, $r['total']);
        $this->assertSame(100.0, $r['percentage']);
    }

    public function test_teachers_scale_adds_bonuses_outside_the_path_cap_for_a_total_of_33(): void
    {
        $calc = ScoreCalculator::make(ScoringPresets::teachers()['rules']);

        $this->assertSame(33.0, $calc->maxTotal());
        $this->assertSame(10.0, $calc->path(16.5, 'excellent', true, true));
        $this->assertSame(7.0, $calc->path(16.5, null, false, false));
    }

    public function test_manual_criteria_use_the_points_entered_by_the_committee(): void
    {
        $calc = ScoreCalculator::make(ScoringPresets::higherEducation()['rules']);

        $this->assertSame(4.0, $calc->countOrManual('research', 99, 4));
        $this->assertSame(5.0, $calc->countOrManual('research', 0, 12));
        $this->assertSame('المنشورات العلمية', $calc->criterionLabels()['score_research']);
    }

    public function test_every_builtin_scale_has_a_positive_total_and_legal_reference(): void
    {
        foreach (ScoringPresets::all() as $preset) {
            $this->assertGreaterThan(0, ScoreCalculator::make($preset['rules'])->maxTotal(), $preset['slug']);
            $this->assertNotEmpty($preset['legal_reference'], $preset['slug']);
        }
    }
}
