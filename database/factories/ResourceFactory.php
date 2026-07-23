<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use App\Models\ResourceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Resource>
 */
class ResourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'branch_id' => Branch::factory(),
            'resource_type_id' => ResourceType::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'is_active' => true,
        ];
    }
}
