<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'locale',
        'currency',
        'status',
        'settings',
        'allowed_origins',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'allowed_origins' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (blank($company->slug)) {
                $company->slug = Str::slug($company->name);
            }

            // Clave pública del widget: identificable y rotable
            if (blank($company->public_key)) {
                $company->public_key = 'pk_'.Str::random(32);
            }
        });
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Route-model binding público por slug (nunca exponer el id interno).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
