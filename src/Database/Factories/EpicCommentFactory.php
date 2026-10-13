<?php

namespace Projects13\Database\Factories;

use Projects13\Models\Epic;
use Projects13\Models\EpicComment;
use Illuminate\Database\Eloquent\Model;
use Basics13\Database\Factories\HasStates;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EpicComment>
 */
class EpicCommentFactory extends Factory
{
    use HasStates;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userModel = config()->string('auth.providers.users.model');

        if (! is_subclass_of($userModel, Model::class)) {
            throw new \InvalidArgumentException('The users authentication provider must use an Eloquent model.');
        }

        return [
            'body' => fake()->paragraph(),
            'user_id' => static::getRecord($userModel),
            'epic_id' => static::getRecord(Epic::class),
        ];
    }
}
