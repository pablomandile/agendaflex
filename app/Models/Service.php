<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToCompany, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'duration_minutes',
        'buffer_before_minutes',
        'buffer_after_minutes',
        'price',
        'max_capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'buffer_before_minutes' => 'integer',
            'buffer_after_minutes' => 'integer',
            'price' => 'decimal:2',
            'max_capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Solo la columna uuid usa UUIDs (la PK sigue siendo id autoincremental).
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            if (blank($service->slug)) {
                $service->slug = Str::slug($service->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class)
            ->withPivot(['custom_duration_minutes', 'custom_price']);
    }

    /**
     * Tipos de recurso que el servicio necesita para dictarse.
     */
    public function requiredResourceTypes(): BelongsToMany
    {
        return $this->belongsToMany(ResourceType::class, 'service_required_resources')
            ->withPivot('quantity');
    }

    /**
     * Duración total que bloquea agenda (incluye buffers).
     */
    public function totalDurationMinutes(): int
    {
        return $this->buffer_before_minutes
            + $this->duration_minutes
            + $this->buffer_after_minutes;
    }
}
