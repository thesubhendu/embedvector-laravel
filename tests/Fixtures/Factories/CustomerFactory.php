<?php

namespace Subhendu\EmbedVector\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Subhendu\EmbedVector\Tests\Fixtures\Models\Customer;

/**
 * @template TModel of Customer
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'department' => $this->faker->randomElement(['ICU', 'ER', 'OR', 'Post Op', 'Pre Op', 'Urology', 'Pediatrics']),
        ];
    }
}
