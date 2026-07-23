<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TimeOffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeOff extends Model
{
    /** @use HasFactory<TimeOffFactory> */
    use BelongsToCompany, HasFactory;

    protected $table = 'time_off';

    protected $fillable = [
        'employee_id',
        'branch_id',
        'starts_at',
        'ends_at',
        'type',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Disponibilidad extra puntual (suma agenda en vez de restarla).
     */
    public function isExtraAvailability(): bool
    {
        return $this->type === 'extra';
    }
}
