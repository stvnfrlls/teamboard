<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'key' => strtoupper(fake()->unique()->bothify('PRJ???????')),
            'description' => fake()->paragraph(),
            'created_by' => User::factory(),
        ];
    }
}
