<?php

namespace Projects13\Database\Factories;

use Projects13\Models\Project;
use Customers13\Models\Customer;
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
        $dates = $this->getDateRange();

        return [
            'name' => fake()->company(),
            'customer_id' => static::getRecord(Customer::class),
            'start_date' => $dates['start_date'],
            'end_date' => $dates['end_date'],
        ];
    }
}
