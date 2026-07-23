<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'branch_id',
        'customer_id',
        'service_id',
        'employee_id',
        'desired_from',
        'desired_to',
        'status',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'desired_from' => 'datetime',
            'desired_to' => 'datetime',
            'priority' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
