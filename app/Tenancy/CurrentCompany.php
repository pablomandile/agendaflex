<?php

namespace App\Tenancy;

use App\Models\Company;

/**
 * Contexto del tenant activo (singleton en el container).
 *
 * Lo setean los middlewares ResolveCompanyFromSession (panel) y
 * ResolveCompanyFromPublicKey (API pública del widget). Jobs y comandos
 * deben setearlo explícitamente por iteración de empresa.
 */
class CurrentCompany
{
    protected ?Company $company = null;

    protected bool $bypassed = false;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function check(): bool
    {
        return $this->company !== null && ! $this->bypassed;
    }

    /**
     * Ejecuta el callback SIN el scope de empresa (flujos de plataforma
     * del super-admin). Uso deliberado y auditable, nunca el default.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }
}
