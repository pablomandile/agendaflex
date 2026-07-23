<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Service;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    public function definition(): array
    {
        $from = fake()->dateTimeBetween('+1 day', '+1 week');

        return [
            'company_id' => Company::factory(),
            'branch_id' => Branch::factory(),
            'customer_id' => Customer::factory(),
            'service_id' => Service::factory(),
            'desired_from' => $from,
            'desired_to' => (clone $from)->modify('+4 hours'),
            'status' => 'waiting',
            'priority' => 0,
        ];
    }
}
