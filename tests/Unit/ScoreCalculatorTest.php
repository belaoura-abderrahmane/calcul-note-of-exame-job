<?php

namespace Tests\Unit;

use App\Services\ScoreCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScoreCalculatorTest extends TestCase
{
    private function calc(): ScoreCalculator
    {
        return ScoreCalculator::make(config('scoring'));
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

    public function test_experience_types_are_not_summed_best_one_is_kept(): void
    {
        $score = $this->calc()->experience([
            ['type' => 'private', 'years' => 10, 'months' => 0],         // 2.5
            ['type' => 'other_public', 'years' => 6, 'months' => 0],     // 3
            ['type' => 'same_institution', 'years' => 2, 'months' => 6], // 2.5
        ]);
        $this->assertSame(3.0, $score);
        $this->assertSame(6.0, $this->calc()->experience([['type' => 'same_institution', 'years' => 9]]));
    }

    public function test_seniority_counts_full_years_and_is_capped(): void
    {
        $this->assertSame(1.5, $this->calc()->seniority('2023-01-01', '2026-06-30'));
        $this->assertSame(5.0, $this->calc()->seniority('2005-01-01', '2026-01-01'));
        $this->assertSame(0.0, $this->calc()->seniority('2027-01-01', '2026-01-01'));
        $this->assertSame(2.0, $this->calc()->seniority(null, null, 4));
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
}
