<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'price' => fake()->numberBetween(10, 100) * 100,
            'max_capacity' => 1,
            'is_active' => true,
        ];
    }
}
