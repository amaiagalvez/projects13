<?php

namespace Projects13\Database\Factories;

use Projects13\Models\Project;
use Projects13\Enums\ProjectRole;
use Projects13\Models\ProjectMember;
use Basics13\Database\Factories\HasStates;
use Illuminate\Contracts\Auth\Authenticatable;
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

    public function hasMember(Authenticatable $user, ProjectRole $role = ProjectRole::Editor): static
    {
        return $this->afterCreating(function (Project $project) use ($user, $role) {
            ProjectMember::factory()->create([
                'project_id' => $project->id,
                'user_id' => $user->getAuthIdentifier(),
                'role' => $role,
            ]);
        });
    }
}
