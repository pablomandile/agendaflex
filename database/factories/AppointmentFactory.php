<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+1 week');

        return [
            'company_id' => Company::factory(),
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'service_id' => Service::factory(),
            'employee_id' => Employee::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+60 minutes'),
            'status' => 'confirmed',
            'price' => fake()->numberBetween(10, 100) * 100,
            'currency' => 'ARS',
            'source' => 'panel',
        ];
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
