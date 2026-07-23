<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ResourceTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceType extends Model
{
    /** @use HasFactory<ResourceTypeFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'name',
    ];

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }
}
