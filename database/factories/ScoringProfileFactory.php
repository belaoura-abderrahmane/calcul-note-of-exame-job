<?php

namespace Database\Factories;

use App\Models\ScoringProfile;
use App\Support\ScoringPresets;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoringProfile>
 */
class ScoringProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'سلم '.fake()->unique()->word(),
            'sector' => 'أخرى',
            'legal_reference' => fake()->sentence(),
            'description' => fake()->sentence(),
            'rules' => ScoringPresets::baseRules(),
            'is_default' => false,
            'is_builtin' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides  مثال: ['interview' => ['max' => 6]]
     */
    public function withRules(array $overrides): static
    {
        return $this->state(function () use ($overrides): array {
            $rules = ScoringPresets::baseRules();
            foreach ($overrides as $criterion => $values) {
                $rules[$criterion] = array_replace($rules[$criterion], $values);
            }

            return ['rules' => $rules];
        });
    }
}
