---
name: ci-linux-case-sensitivity
description: >-
  Diagnostica y corrige fallos de tests/CI que ocurren SOLO en Linux/GitHub Actions
  pero pasan en local (Windows o macOS). Disparadores: "los tests pasan en local pero
  fallan en CI", "passes on my machine but fails on CI", el job de tests en GitHub
  Actions en rojo mientras local está verde, o errores de Laravel + Inertia como
  "Inertia page component file [X] does not exist". Causa típica: CASE-SENSITIVITY del
  filesystem (Linux distingue mayúsculas/minúsculas; Windows y macOS por defecto NO) —
  p. ej. resources/js/pages vs el default de Inertia resources/js/Pages, o una vista
  Blade / ruta referenciada con el case equivocado.
---

# CI falla solo en Linux / pasa en local (case-sensitivity)

Cuando un test o build **pasa en local (Windows/macOS) pero falla en CI (Linux)** —o
"nunca pasó" en CI desde el primer commit— el sospechoso #1 es la **sensibilidad a
mayúsculas del filesystem**. Windows y macOS (por defecto) son *case-insensitive*: una
ruta con el case equivocado igual resuelve. Linux es *case-sensitive*: la misma ruta
no existe y algo falla.

## 1. Reconocer el patrón
- Local ✅ / CI ❌, de forma **determinística** (no flaky). Si además "nunca pasó", refuerza la hipótesis.
- Suele afectar solo a algunos tests (los que tocan la ruta con case incorrecto), no a todos.

## 2. Traer el log real de CI (no adivinar)
Preferí ver el error exacto antes de tocar nada.

- Si está `gh`: `gh run list` y `gh run view <run-id> --log-failed`.
- Sin `gh`, vía API de GitHub:
  1. Últimos runs y el que falló:
     `GET https://api.github.com/repos/{owner}/{repo}/actions/runs?per_page=5`
  2. Qué **paso** falló:
     `GET .../actions/runs/{run_id}/jobs` → mirar `jobs[].steps[].conclusion`
  3. Log del job (requiere auth):
     `GET .../actions/jobs/{job_id}/logs`
     Token desde la credencial de git ya guardada (no pedirla ni imprimirla):
     ```bash
     TOKEN=$(printf 'protocol=https\nhost=github.com\n\n' | git credential fill 2>/dev/null | sed -n 's/^password=//p')
     curl -sL -H "Authorization: Bearer $TOKEN" -H "User-Agent: x" \
       "https://api.github.com/repos/{owner}/{repo}/actions/jobs/{job_id}/logs" | grep -niE 'not exist|no such|inertia|FAILURES|Error'
     ```
  (La API pública sin auth sirve para runs/jobs/steps; el endpoint de **logs** sí pide token.)

## 3. Causas frecuentes de case-mismatch
- **Laravel + Inertia (la más común):** en testing Inertia verifica que exista el archivo de la página. Su default es `resource_path('js/Pages')` (**P mayúscula**) con `testing.ensure_pages_exist = true`. Si el proyecto usa `resources/js/pages` (**minúscula**), en Linux los tests con `assertInertia` fallan con:
  `Inertia page component file [X] does not exist`.
- **Vistas Blade** referenciadas con case distinto al del archivo (`view('emails.Contact')` vs `contact.blade.php`).
- **Rutas de archivos** en el código (Storage, includes, traducciones, assets) con case incorrecto.
- OJO: los **nombres de clase PHP son case-insensitive** — un `use App\Models\certificate` NO falla por case ni en Linux. El problema es siempre de **rutas de archivo**, no de nombres de clase.

## 4. Fix
- **Inertia:** publicar/crear `config/inertia.php` apuntando al case REAL de la carpeta (minúscula):
  ```php
  'page_paths' => [resource_path('js/pages')],
  'testing' => [
      'ensure_pages_exist' => true,
      'page_paths' => [resource_path('js/pages')],
      'page_extensions' => ['js','jsx','svelte','ts','tsx','vue'],
  ],
  ```
  (Base: copiar `vendor/inertiajs/inertia-laravel/config/inertia.php` y corregir el case. Alternativa menos recomendada: `testing.ensure_pages_exist => false`.)
- **General:** hacer que la referencia coincida EXACTAMENTE con el case del archivo real (o renombrar el archivo). En Windows, `git mv viejo tmp && git mv tmp Nuevo` para forzar el cambio de case en git.

## 5. Verificar
No se puede reproducir en Windows/macOS (case-insensitive). Confirmá:
- corrigiendo el path y **pusheando**, luego consultá la conclusión del run
  (`GET .../actions/workflows/<archivo>.yml/runs?per_page=1` → `status`/`conclusion`), o
- razonando: con el case correcto, en Linux la ruta ahora existe.

## Notas
- Config no suele estar cacheada en CI (entorno fresco), así que un nuevo `config/inertia.php` se lee sin más.
- Muchas veces hay **dos workflows** (p. ej. `tests` y `lint`) → dos runs por push; filtrá por workflow para no confundir resultados.
