<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\WorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'day_of_week' => fake()->numberBetween(1, 5),
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
        ];
    }
}
