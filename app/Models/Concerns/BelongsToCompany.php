<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Models\Scopes\CompanyScope;
use App\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla el modelo por tenant: aplica el CompanyScope global y auto-setea
 * company_id al crear desde la empresa activa.
 *
 * IMPORTANTE: company_id NUNCA va en $fillable — se asigna acá, jamás
 * desde input del request.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if ($model->getAttribute('company_id') === null) {
                $model->setAttribute('company_id', app(CurrentCompany::class)->id());
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
