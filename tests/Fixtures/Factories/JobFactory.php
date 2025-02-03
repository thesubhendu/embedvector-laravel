<?php

namespace Subhendu\EmbedVector\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Subhendu\EmbedVector\Tests\Fixtures\Models\Job;

/**
 * @template TModel of Job
 *
 * @extends Factory<TModel>
 */
class JobFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = Job::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->title,
            'department' => $this->faker->randomElement(['ICU', 'ER', 'OR', 'Post Op', 'Pre Op', 'Urology', 'Pediatrics']),

        ];
    }
}
