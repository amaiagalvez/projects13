<?php

namespace Projects13\Database\Factories;

use Projects13\Models\Project;
use Projects13\Enums\ProjectRole;
use Projects13\Models\ProjectMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectMember>
 */
class ProjectMemberFactory extends Factory
{
    protected $model = ProjectMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userModel = config()->string('auth.providers.users.model');

        if (! is_subclass_of($userModel, Model::class) || ! method_exists($userModel, 'factory')) {
            throw new \InvalidArgumentException('The users authentication provider must use an Eloquent model with a factory.');
        }

        return [
            'project_id' => Project::factory(),
            'user_id' => $userModel::factory(),
            'role' => ProjectRole::Viewer,
        ];
    }

    public function role(ProjectRole $role): static
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }
}
