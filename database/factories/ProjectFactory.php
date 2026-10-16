<?php

namespace Projects13\Database\Factories;

use Projects13\Models\Project;
use Basics13\Database\Factories\HasStates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    use HasStates;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dates = $this->getDatesInRange();

        return [
            'name' => fake()->unique()->company(),
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
        ];
    }
}
