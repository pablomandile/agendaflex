<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'address',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Branch $branch) {
            if (blank($branch->slug)) {
                $branch->slug = Str::slug($branch->name);
            }
        });
    }

    /**
     * Zona horaria efectiva: propia o heredada de la empresa.
     */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?? $this->company->timezone;
    }
}
