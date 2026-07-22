# PLAN_AGENDAFLEX.md — Plan de implementación (MVP)

> **Cómo usar este archivo.** Es un tracker vivo, pensado para avanzar en distintos momentos. Cada etapa tiene un **Estado** y una lista de tareas con casillas. Al completar una tarea marcá `[x]`; al terminar una etapa poné su Estado en ✅ y actualizá el dashboard de abajo. Trabajá las etapas **en orden** (hay dependencias).
>
> **Leyenda de estado:** ⬜ Pendiente · 🟡 En progreso · ✅ Completada · ⏭️ Diferida (fase 2)

## Dashboard de progreso

| # | Etapa | Estado |
|---|---|---|
| 0 | Preparación del repo y este documento | ✅ |
| 1 | Setup base (Laravel 12 + Inertia + PrimeVue + Tailwind + dark mode + Vite dual) | ✅ |
| 2 | Autenticación (Fortify + Google OAuth) | ✅ |
| 3 | Multi-tenancy + roles | ⬜ |
| 4 | Modelo de datos / dominio | ⬜ |
| 5 | Motor de disponibilidad + booking + calendario del panel | ⬜ |
| 6 | Widget público + API REST | ⬜ |
| 7 | Notificaciones email + reportes | ⬜ |
| 8 | Responsive + verificación end-to-end + pulido | ⬜ |

---

## Context

Agendaflex es una plataforma **SaaS multi-empresa de gestión de turnos**. El objetivo (según `AGENDAFLEX.md`) es un motor de scheduling **genérico**, embebible en el sitio de cualquier negocio, que sirva a múltiples rubros (belleza, salud, wellness, servicios profesionales, educación, mascotas, automotriz, hogar). El proyecto arranca desde cero.

Decisiones tomadas para el MVP:

- **Multi-tenant (SaaS)** desde el inicio: muchas empresas (`companies`), datos aislados, con sucursales.
- **Roles en dos niveles**: plataforma (`super-admin`) + negocio (`owner`, `staff`, `client`).
- **Frontend híbrido**: panel admin con Inertia 2 + Vue 3 + PrimeVue 4.5, y un **widget de reservas público** standalone embebible en sitios de terceros vía API REST.
- **Módulos MVP**: núcleo (servicios, empleados, reservas: crear/cancelar/reprogramar/waitlist) + notificaciones email + reportes + recursos (salas/equipos). **Sin pagos** (fase 2).
- **Base UI**: partir del starter kit oficial Vue de Laravel 12 y **migrar de shadcn-vue a PrimeVue 4.5**.
- **Cliente sin login en MVP**: reserva por widget capturando nombre+email+teléfono (se crea `customer`, sin cuenta). Gestiona (cancelar/reprogramar) por **link firmado en el email**. Rol `client` con login → fase 2.
- **Tema claro/oscuro** switcheable (ícono sol/luna en la navbar), **responsive**, **Google OAuth** para el panel.

### Hallazgos clave de la investigación (verificados, julio 2026)
- **PrimeVue 4.5.5 es la última versión MIT/gratuita**; repo archivado el 28-jun-2026, v5 (PrimeUI) es paga → **pinear `primevue@4.5.5` exacto** y asumir mantenimiento propio.
- Theming renombrado: usar **`@primeuix/themes`** (preset `Aura` desde `@primeuix/themes/aura`); `@primevue/themes` está deprecado.
- El **starter kit Vue trae shadcn-vue, no PrimeVue** → reemplazar componentes UI.
- **Multi-tenancy**: single-database con `company_id` + scope propio (ni `stancl/tenancy` ni `spatie/laravel-multitenancy`, orientados a multi-DB/dominio).
- **Widget en dominios de terceros**: Sanctum modo SPA (cookies) exige mismo top-level domain → inservible; el widget va **stateless** con **clave pública de tenant** + rate limiting + CORS wildcard con `supports_credentials=false`.

### Arquitectura general
```
Panel admin (Inertia + Vue + PrimeVue)      Widget público (Vue standalone)
   guard web / sesión                          stateless / clave pública
        │                                              │
        ▼                                              ▼
  ResolveCompanyFromSession               ResolveCompanyFromPublicKey
        └──────────────► CurrentCompany (singleton) ◄──────────────┘
                              │  CompanyScope global → todo scopeado por company_id
                              ▼
                        MySQL (single-DB, company_id en casi toda tabla)
```

### Benchmark de features (Fresha) — cobertura
Casi todo el scheduling de Fresha ya está en el MVP: gestión de citas, horarios de personal, bloqueos/descansos/vacaciones, buffers (tiempo adicional), recordatorios, reservas online 24/7, lista de espera, citas de grupo, recursos/salas, perfiles de cliente y reportes. Ajustes surgidos: se agrega **vistas de calendario día/semana/mes** al panel (Etapa 5); quedan para fase 2 los **formularios/consultas digitales previas**, pruebas cutáneas y depósitos/pagos. **Rubros:** Fresha se enfoca en belleza/wellness; Agendaflex apunta más amplio → el **modelo se mantiene agnóstico al rubro** (servicios/empleados/recursos/clientes genéricos + categorías que cada empresa define, sin lógica hardcodeada por vertical).

---

## Etapa 0 — Preparación del repo y este documento
**Estado:** ✅ Completada · **Depende de:** —

- [x] Guardar este plan como `PLAN_AGENDAFLEX.md` en la raíz del proyecto
- [x] `git init` (rama `main`); el `.gitignore` llega con el scaffold de Laravel en Etapa 1
- [x] Confirmar entorno Laragon: PHP 8.4.22, Composer 2.9.4, Node 22.22.0, npm 10.9.4, MySQL 8.4.3 ✅

---

## Etapa 1 — Setup base
**Estado:** ✅ Completada (migración shadcn→PrimeVue: progresiva, ver nota) · **Depende de:** Etapa 0

Crear proyecto y dejar el panel renderizando con PrimeVue y dark mode funcional.

- [x] Crear proyecto con starter kit oficial Vue. **Nota:** los tags de Packagist estaban desactualizados (v1.0.2 = Tailwind 3/Inertia beta) y `dev-main` ya saltó a Laravel 13 → se clonó el kit en el **commit `b634812`** (último estado Laravel 12: framework 12.64 + Inertia 2.0.24 + Fortify 1.37 + Wayfinder + Tailwind 4)
- [x] Deps front instaladas: `primevue@4.5.5` (**pineada exacta, sin `^`** — última MIT), `@primeuix/themes`, `primeicons`, `tailwindcss-primeui`, `vite-plugin-css-injected-by-js@3` (**la v5 exige Vite 8**; el kit usa Vite 7)
- [x] PrimeVue registrado en `resources/js/app.ts` (preset Aura + ToastService + ConfirmationService). **Nota:** `darkModeSelector: '.dark'` (no `.app-dark`) para reutilizar la clase que ya maneja el kit
- [x] Tailwind 4 + PrimeVue en `resources/css/app.css`: `@layer theme, base, primevue, components, utilities` + imports de `tailwindcss-primeui` y `primeicons` (el `@custom-variant dark` del kit ya usa `.dark`)
- [x] Dark mode: el kit ya trae `useAppearance` (light/dark/system, localStorage + cookie SSR) → se creó `AppearanceToggle.vue` (sol/luna, PrimeVue Button) y se agregó al header (`AppSidebarHeader.vue`)
- [x] Anti-FOUC: ya incluido por el kit en `resources/views/app.blade.php`
- [ ] 🔄 Migrar de shadcn-vue a PrimeVue: **progresiva** — PrimeVue conviviendo con los componentes del kit; los reemplazos se hacen al construir cada pantalla real (Etapas 3–7); quitar deps reka-ui al final
- [x] Vite dual-build: `vite.widget.config.ts` (lib mode iife, `publicDir:false`, `cssFileName`) + esqueleto `resources/widget/` (`main.ts` con `window.Agendaflex.mount()`, `Widget.vue` placeholder) → `public/widget/agendaflex-widget.js` (290 KB / 67 KB gzip)
- [x] Verificado: **40 tests pasan**, `/login` responde 200 con locale `es` y anti-FOUC, `npm run build` y `build:widget` OK, ESLint/Prettier/Pint limpios (lo que corre el CI del kit)
- [x] `config/inertia.php` con `js/pages` en minúscula ya venía en el kit (cubre el gotcha de case-sensitivity de CI Linux del SKILL.md)

---

## Etapa 2 — Autenticación (Fortify + Google OAuth)
**Estado:** ✅ Completada (falta solo la credencial real de Google, acción manual) · **Depende de:** Etapa 1

- [x] `composer require laravel/socialite` (v5.29)
- [x] `config/services.php` bloque `google` + `.env` (`GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI` — valores vacíos hasta crear la credencial)
- [x] Migración `add_google_oauth_fields_to_users_table`: `google_id` (nullable, unique) + `avatar` + `password` nullable + `is_super_admin` (bool)
- [x] `app/Http/Controllers/Auth/GoogleController.php` + rutas `guest` `/auth/google/redirect` y `/auth/google/callback`; matchea por `google_id`, vincula cuentas existentes por email, marca email verificado, `Auth::login(remember)`
- [x] Botón "Continuar con Google" en `Login.vue` como **anchor real** (`<a href>` vía Button as-child), con separador "o" bajo el login de Fortify
- [x] Tests: 5 casos (redirect, crear usuario, vincular por email, login existente, guest-only) mockeando Socialite — 45 tests en verde
- [ ] ⚠️ **Acción manual (Pablo):** crear OAuth 2.0 Client ID (Web) en Google Cloud Console con redirect URI `http://localhost:8000/auth/google/callback` y completar `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` en `.env`

---

## Etapa 3 — Multi-tenancy + roles
**Estado:** ⬜ Pendiente · **Depende de:** Etapa 2

- [ ] `app/Tenancy/CurrentCompany.php` — singleton (`set/get/id`, `withoutScope(fn)` para super-admin)
- [ ] `app/Models/Concerns/BelongsToCompany.php` + `app/Models/Scopes/CompanyScope.php` — scope global + auto-set de `company_id` en `creating`; `company_id` **fuera de `$fillable`**
- [ ] `app/Http/Middleware/ResolveCompanyFromSession.php` — panel: `current_company_id` validado contra `company_user` + `setPermissionsTeamId()`
- [ ] `app/Http/Middleware/ResolveCompanyFromPublicKey.php` — widget: valida `slug ↔ public_key ↔ empresa activa`, valida `Origin`, rate-limit por public_key (no setea rol)
- [ ] `bootstrap/app.php` — orden de middleware: tenant **antes** de `SubstituteBindings` y de `setPermissionsTeamId`; activar `->scopeBindings()` en rutas del panel
- [ ] `composer require spatie/laravel-permission`; `config/permission.php`: `'teams' => true`, `'team_foreign_key' => 'company_id'` **antes de migrar**
- [ ] Roles: `super-admin` global (`company_id=null`) + `Gate::before()`; `owner`/`staff`/`client` definidos como globales, **asignados** por `company_id`
- [ ] Al cambiar de empresa: `setPermissionsTeamId()` + `$user->unsetRelation('roles')->unsetRelation('permissions')`; company switcher para usuarios multi-empresa

---

## Etapa 4 — Modelo de datos / dominio
**Estado:** ⬜ Pendiente · **Depende de:** Etapa 3

Convención: casi toda tabla lleva `company_id` (FK + índice compuesto); timestamps + soft deletes; IDs `bigint` internos, `uuid`/`slug` público; unicidad **siempre compuesta con `company_id`**.

- [ ] Tenancy/plataforma: `companies` (`slug` unique, `public_key` unique, `timezone`, `locale`, `currency`, `status`, `settings` json, dominios permitidos del widget), `branches`, `company_user`, tablas spatie (con `company_id` en pivotes)
- [ ] Empleados: `employees` (`user_id` nullable, `branch_id`, `color`), `employee_service` (skills + override opcional duración/precio)
- [ ] Servicios/recursos: `service_categories`, `services` (`duration_minutes`, `buffer_before/after_minutes`, `price`, `max_capacity`), `resource_types`, `resources` (`branch_id`), `service_required_resources` (`resource_type_id`, `quantity`)
- [ ] Clientes: `customers` (`user_id` nullable, índice `(company_id, email)`)
- [ ] Horarios: `working_hours` (recurrente por empleado/día, `effective_from/to`, varias filas por día para turnos partidos), `time_off`/`schedule_exceptions` (vacaciones, feriados de sucursal, ausencias, disponibilidad extra)
- [ ] Turnos: `appointments` (`starts_at`/`ends_at` en **UTC**, `status`, `source`, `rescheduled_from_id` self-FK; índices `(company_id, employee_id, starts_at)`), `appointment_resource` (N–N), `waitlist_entries`
- [ ] Notificaciones: `notification_logs` (`type`, `channel`, `status`, `sent_at`)
- [ ] Seeders de demo (empresa + sucursal + servicios + empleados + horarios) para probar

---

## Etapa 5 — Motor de disponibilidad + booking + calendario del panel
**Estado:** ⬜ Pendiente · **Depende de:** Etapa 4

- [ ] `app/Services/AvailabilityService.php` → `slotsFor(company, branch, service, ?employee, dateRange)`: candidatos por skill → ventana de trabajo (unir `working_hours` vigentes en tz branch, restar `time_off`/feriados, sumar `extra_availability`) → restar ocupación (con buffers) → generar slots (`slot_step` por empresa) → chequeo de recursos requeridos → capacidad grupal (`max_capacity`)
- [ ] Booking transaccional (concurrencia): transacción + `SELECT … FOR UPDATE` sobre turnos/recursos, re-verificar solape (empleado + cada recurso), insertar `appointment` + `appointment_resource`, validar que branch/employee/service/resources compartan `company_id`
- [ ] Cancelar/reprogramar: liberar slots, disparar `waitlist` (notificar al primero), guardar `rescheduled_from_id`
- [ ] Calendario del panel (UI): vistas **día/semana/mes** con PrimeVue, filtrables por empleado/sucursal/recurso, color por empleado; crear/editar/cancelar/reprogramar desde el calendario; bloquear/desbloquear horarios (`time_off`); vista día primaria en mobile
- [ ] Tests: disponibilidad (horarios+buffers+recursos) y concurrencia (dos reservas al mismo slot no duplican)

---

## Etapa 6 — Widget público + API REST
**Estado:** ⬜ Pendiente · **Depende de:** Etapa 5

- [ ] `php artisan install:api` (Sanctum + `routes/api.php`)
- [ ] Endpoints `/api/v1/{company:slug}/...` bajo `ResolveCompanyFromPublicKey`: `GET services`, `GET employees`, `GET availability`, `POST bookings` (nunca listar `customers`; devolver uuid/slug)
- [ ] `POST bookings`: crea/matchea `customer` por email + `appointment` (source=widget) vía booking transaccional; rate-limit + captcha
- [ ] Links firmados (`URL::signedRoute`/temporary) en el email para cancelar/reprogramar sin login
- [ ] Widget Vue en `resources/widget/`: `main.ts` expone `window.Agendaflex.mount('#sel', { tenant, apiBase })`; `Widget.vue` (flujo servicio → empleado → fecha/slot → datos → confirmación); `api.ts`; solo imports nombrados de PrimeVue; ancho `100%` del host (nunca `100vw`), `box-sizing:border-box`; theming por tenant vía CSS vars `--p-primary-*`
- [ ] `config/cors.php`: `paths:['api/*']`, `allowed_origins:['*']`, `supports_credentials:false`
- [ ] Verificar embebido: `npm run build:widget` → HTML de prueba en otro origen que monta el widget, lista servicios y crea reserva (CORS OK)

---

## Etapa 7 — Notificaciones email + reportes
**Estado:** ⬜ Pendiente · **Depende de:** Etapa 6

- [ ] Mailables + queue: confirmación, recordatorio, cancelación, reprogramación; registrar en `notification_logs`
- [ ] Recordatorios: job en scheduler que **itera empresas** con `CurrentCompany::set()` + `setPermissionsTeamId()` por iteración
- [ ] Reportes derivados de `appointments` (agregando por `company_id`/`employee_id`/`service_id`/rango): ocupación, ingresos, servicios populares, rendimiento por empleado — dashboard PrimeVue
- [ ] Verificar con Mailpit (emails + links firmados) y `php artisan schedule:test`

---

## Etapa 8 — Responsive + verificación end-to-end + pulido
**Estado:** ⬜ Pendiente · **Depende de:** Etapas 1–7

- [ ] Responsive panel: contenedor `max-w-screen-2xl mx-auto` + layout sidebar; utilities responsive + breakpoints PrimeVue; sin anchos fijos px; verificar que no exceda ancho en desktop y funcione en mobile
- [ ] Test de arquitectura (Pest): todo modelo con `company_id` usa `BelongsToCompany`; aislamiento cross-tenant (query scopeada + route binding 404 cross-tenant)
- [ ] Test de roles: super-admin ve todo; `owner` su empresa; `staff` solo su agenda; `setPermissionsTeamId` al cambiar de empresa
- [ ] Recorrido E2E completo: crear 2 empresas → configurar servicios/empleados/horarios/recursos → reservar por widget → recibir email → cancelar por link → ver reporte
- [ ] Pulido de UI/UX y estados de error/carga

---

## Archivos críticos (a crear)

| Archivo | Rol | Etapa |
|---|---|---|
| `app/Tenancy/CurrentCompany.php` | Contexto del tenant (singleton) | 3 |
| `app/Models/Concerns/BelongsToCompany.php` + `Scopes/CompanyScope.php` | Aislamiento por `company_id` | 3 |
| `app/Http/Middleware/ResolveCompanyFrom{Session,PublicKey}.php` | Resolución de tenant | 3 |
| `app/Services/AvailabilityService.php` | Slots + booking transaccional | 5 |
| `config/permission.php` | `teams`/`team_foreign_key=company_id` (antes de migrar) | 3 |
| `bootstrap/app.php` | Orden de middleware | 3 |
| `resources/js/app.ts` · `resources/css/app.css` | PrimeVue + dark mode + capas Tailwind | 1 |
| `vite.widget.config.ts` · `resources/widget/main.ts` | Build y entry del widget | 1, 6 |
| `config/cors.php` · `config/services.php` | CORS widget / Socialite Google | 6, 2 |

---

## Fuera de alcance (fase 2+)

Pagos (Stripe/Mercado Pago, señas/depósitos, política de pago), **formularios/consultas digitales previas a la cita** y pruebas cutáneas (patch test), portal de cliente con login (rol `client` activo), notificaciones SMS/WhatsApp/push, subdominio/dominio propio white-label, multi-DB por tenant enterprise, API de partners con Sanctum Bearer, sincronización de calendarios (Google/Outlook/Apple), reprogramación por drag & drop en el calendario, y módulos futuros (CRM, loyalty, gift cards, memberships, POS, marketplace).
