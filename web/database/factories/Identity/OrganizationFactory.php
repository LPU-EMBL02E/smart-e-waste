<?php

namespace Database\Factories\Identity;

use App\Modules\Identity\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Organizations for tests. Database_Schema.md §1.
 *
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'code' => fake()->unique()->bothify('ORG-####'),
            'description' => null,
            'is_active' => true,
        ];
    }

    /** Switched off: keeps its students, takes no new ones (API_Design.md P33). */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
