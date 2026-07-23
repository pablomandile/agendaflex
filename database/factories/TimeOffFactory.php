<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use App\Models\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+2 weeks');

        return [
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+3 days'),
            'type' => 'vacation',
        ];
    }
}
