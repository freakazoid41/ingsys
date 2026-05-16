<?php

namespace Database\Factories;

use App\Models\Persons;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Persons>
 */
class PersonsFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\Persons>
     */
    protected $model = Persons::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qnid' => $this->faker->unique()->uuid(),
            'name' => $this->faker->firstName(),
            'surname' => $this->faker->lastName(),
            'type_id' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
