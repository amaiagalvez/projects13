<?php

namespace Projects13\Database\Factories;

use Carbon\CarbonImmutable;
use Projects13\Models\Epic;
use Projects13\Models\Project;
use Basics13\Database\Factories\HasStates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Epic>
 */
class EpicFactory extends Factory
{
    use HasStates;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = static::getRecord(Project::class);

        // Epic dates must be within project dates
        $projectStart = CarbonImmutable::parse($project->start_date);
        $projectEnd = $project->end_date ? CarbonImmutable::parse($project->end_date) : CarbonImmutable::now()->addYear();

        // Ensure epic start is after project start and before project end
        $earliestStart = $projectStart;
        $latestStart = $projectEnd->subDay(); // At least 1 day before project end for end_date to fit

        // The window vanishes when the project carries no room between its own dates, which the
        // domain allows: both bounds collapse onto the project start so faker never sees an
        // inverted range. Tests that pin their own dates discard these values anyway.
        if ($latestStart->lessThan($earliestStart)) {
            $latestStart = $earliestStart;
        }

        $startDate = fake()->dateTimeBetween($earliestStart, $latestStart)->format('Y-m-d');

        // Epic end must be after epic start and before project end
        $endFloor = CarbonImmutable::parse($startDate)->addDay();
        $endDate = fake()->dateTimeBetween($endFloor, $projectEnd->max($endFloor))->format('Y-m-d');

        return [
            'name' => fake()->unique()->sentence(3),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'project_id' => $project->id,
        ];
    }

    /** Indicate that the epic has no start or end date. */
    public function withoutDates(): static
    {
        return $this->state([
            'start_date' => null,
            'end_date' => null,
        ]);
    }
}
