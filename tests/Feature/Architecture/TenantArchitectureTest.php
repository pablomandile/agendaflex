<?php

namespace Tests\Feature\Architecture;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guardián del aislamiento multi-tenant: si un modelo tiene company_id
 * y NO usa BelongsToCompany, sus queries no quedan scopeadas y hay
 * riesgo de fuga entre empresas. Este test falla ante cualquier modelo
 * nuevo que se olvide del trait.
 */
class TenantArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_model_with_company_id_uses_the_belongs_to_company_trait()
    {
        $violations = [];

        foreach ($this->applicationModels() as $class) {
            $model = new $class;

            if (! Schema::hasColumn($model->getTable(), 'company_id')) {
                continue;
            }

            if (! in_array(BelongsToCompany::class, class_uses_recursive($class), true)) {
                $violations[] = $class;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'Modelos con company_id SIN el trait BelongsToCompany (fuga potencial entre tenants): '
                .implode(', ', $violations),
        );
    }

    public function test_no_tenant_model_exposes_company_id_as_fillable()
    {
        $violations = [];

        foreach ($this->applicationModels() as $class) {
            $model = new $class;

            if (in_array('company_id', $model->getFillable(), true)) {
                $violations[] = $class;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'company_id NUNCA debe ser fillable (se asigna desde CurrentCompany, no desde el request): '
                .implode(', ', $violations),
        );
    }

    /**
     * @return array<int, class-string<Model>>
     */
    private function applicationModels(): array
    {
        $classes = [];

        foreach (Finder::create()->files()->in(app_path('Models'))->name('*.php') as $file) {
            $class = 'App\\Models\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                $file->getRelativePathname(),
            );

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}
