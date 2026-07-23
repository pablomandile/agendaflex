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
| 3 | Multi-tenancy + roles | ✅ |
| 4 | Modelo de datos / dominio | ✅ |
| 5 | Motor de disponibilidad + booking + calendario del panel | ✅ |
| 6 | Widget público + API REST | ✅ |
| 7 | Notificaciones email + reportes | ✅ |
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
**Estado:** ✅ Completada (UI del switcher pendiente para cuando se arme el panel) · **Depende de:** Etapa 2

- [x] `app/Tenancy/CurrentCompany.php` — singleton (`set/get/id/check`, `withoutScope(fn)` con restauración en `finally`)
- [x] `app/Models/Concerns/BelongsToCompany.php` + `app/Models/Scopes/CompanyScope.php` — scope global + auto-set de `company_id` en `creating`; `company_id` fuera de `$fillable`
- [x] `app/Http/Middleware/ResolveCompanyFromSession.php` — valida `current_company_id` contra membresías (si es inválida cae a la primera membresía) + `setPermissionsTeamId()`
- [x] `app/Http/Middleware/ResolveCompanyFromPublicKey.php` — valida `slug ↔ public_key ↔ empresa activa` (404 sin filtrar existencia), valida `Origin` contra `allowed_origins`; alias `tenant.public` (rate-limit se agrega con la API en Etapa 6)
- [x] `bootstrap/app.php` — `ResolveCompanyFromSession` appendeado al grupo web + `$middleware->priority()` con tenant **antes** de `SubstituteBindings`
- [x] spatie/laravel-permission **8.3** con `teams => true` y `team_foreign_key => 'company_id'` seteados antes de migrar
- [x] Roles globales (`company_id=null`): `super-admin` (vía `Gate::before` en AppServiceProvider), `owner` (todos los permisos), `staff` (agenda+clientes), `client` (fase 2) — `RolesAndPermissionsSeeder` con 13 permisos
- [x] Tablas base de tenancy: `companies` (slug/public_key únicos, timezone/locale/currency, settings, allowed_origins), `branches`, `company_user` + modelos con factories
- [x] `CompanySwitchController` (`POST company/switch`): valida membresía real (403 si no), setea sesión, invalida caché de roles — **UI del switcher se agrega al armar las pantallas del panel**
- [x] Tests: 11 nuevos (aislamiento de queries, auto-set de company_id, withoutScope, middleware de sesión, switch con/sin membresía, roles distintos por empresa, bypass super-admin, permisos de staff) — **56 tests en verde**

---

## Etapa 4 — Modelo de datos / dominio
**Estado:** ✅ Completada · **Depende de:** Etapa 3

Convención: casi toda tabla lleva `company_id` (FK + índice compuesto); timestamps + soft deletes; IDs `bigint` internos, `uuid`/`slug` público; unicidad **siempre compuesta con `company_id`**.

- [x] Tenancy/plataforma: `companies`, `branches`, `company_user`, tablas spatie — hechas en Etapa 3
- [x] Empleados: `employees` (`user_id` nullable, `branch_id`, `color`, uuid público), `employee_service` (skills + `custom_duration_minutes`/`custom_price`)
- [x] Servicios/recursos: `service_categories`, `services` (uuid, duración, buffers, precio, `max_capacity` para grupales, unique `(company_id, slug)`), `resource_types`, `resources`, `service_required_resources` (tipo + `quantity`)
- [x] Clientes: `customers` (uuid, `user_id` nullable para fase 2, índice `(company_id, email)` para matcheo del widget)
- [x] Horarios: `working_hours` (por empleado/día, `effective_from/to`, turnos partidos) y `time_off` (vacaciones/feriado de sucursal/cierre de empresa/extra, según combinación de `employee_id`/`branch_id` null)
- [x] Turnos: `appointments` (UTC, `status`, `source`, `rescheduled_from_id`, índices por empleado/sucursal/cliente), `appointment_resource` (N–N), `waitlist_entries` (con `priority`)
- [x] Notificaciones: `notification_logs` (channel/type/recipient/status/sent_at/error)
- [x] 11 modelos con trait `BelongsToCompany` + `HasUuids` en los públicos (uuid además de la PK), relaciones completas y helpers (`totalDurationMinutes()`, `isActive()`, `isExtraAvailability()`)
- [x] 10 factories del dominio
- [x] `DemoSeeder`: "Estudio Norte" (peluquería, 2 sucursales, 5 servicios, 3 empleados con skills y horario partido mar-sáb, 5 sillones, 12 clientes, 15 turnos) — logins demo: `owner@agendaflex.test` / `admin@agendaflex.test` (super-admin), password `password`
- [x] Tests: 8 nuevos (uuids, aislamiento, pivotes con overrides, recursos por turno, buffers, seeder completo) — **64 tests en verde**

---

## Etapa 5 — Motor de disponibilidad + booking + calendario del panel
**Estado:** ✅ Completada · **Depende de:** Etapa 4

- [x] `AvailabilityService::slotsFor(branch, service, ?employee, from, to)`: candidatos por skill → ventanas de `working_hours` (vigencia + turnos partidos, en tz de sucursal) − `time_off` (empleado/sucursal/empresa) + `extra` → − turnos activos expandidos por buffers → slots por `slot_step` (settings de empresa, default 15') → chequeo de recursos requeridos por tipo/cantidad → cupos grupales (`max_capacity`: el inicio exacto de un grupo con cupo se ofrece)
- [x] `BookingService::book()` transaccional: `SELECT … FOR UPDATE` sobre turnos del empleado (serializa reservas concurrentes) → re-verifica solape con buffers → valida agenda → lockea y asigna recursos libres (grupos comparten recursos) → precio/duración con overrides de `employee_service` → matchea/crea `customer` por email
- [x] `cancel()` (libera slot + promueve `waitlist` → email en Etapa 7) y `reschedule()` (cancela primero para permitir mover con solape, crea turno nuevo con `rescheduled_from_id`)
- [x] Endpoints: `GET /calendar` (Inertia con props scopeadas), `GET /calendar/events` (formato FullCalendar + time_off como fondo), `GET /availability` (slots del diálogo), `GET /customers/search`, `POST /appointments` + `/cancel` + `/reschedule` — todos con `can:` por permiso + middleware `company.selected` (nuevo `EnsureCompanySelected`)
- [x] Calendario UI ([pages/calendar/Index.vue](resources/js/pages/calendar/Index.vue)): FullCalendar 6 (MIT) día/semana/mes en español, filtros por sucursal/empleado, colores por empleado, diálogo de reserva (servicio → empleado → fecha → slots → cliente existente o nuevo → notas), diálogo de detalle con cancelar/reprogramar, toasts; vista día en mobile; estilos integrados a tokens PrimeVue; ítem "Agenda" en el sidebar
- [x] Tests: 26 nuevos — disponibilidad (horario partido, buffers, vacaciones, feriado de sucursal, extra, recursos entre empleados, cupos grupales), booking (doble reserva serializada, fuera de agenda, recursos agotados, grupos hasta capacidad, cross-company, cancelar+waitlist, reprogramar) y endpoints (scoping, validaciones, 404 cross-tenant, permisos) — **90 tests en verde**

Pendiente menor (a Etapa 8): bloquear/desbloquear `time_off` desde la UI del calendario (hoy se ven como fondo gris; el ABM llega con las pantallas de gestión).

---

## Etapa 6 — Widget público + API REST
**Estado:** ✅ Completada · **Depende de:** Etapa 5

- [x] `php artisan install:api` (Sanctum instalado para API de partners futura; el widget es stateless por clave pública)
- [x] API v1 `/api/v1/{company}/...` bajo `tenant.public`: `GET catalog` (empresa+branding, sucursales, servicios con skills, empleados — **todo por uuid/slug, cero IDs internos**), `GET availability` (slots con `employee_uuid`), `POST bookings` (matchea/crea customer por email, `source=widget`, booking transaccional, devuelve resumen + `manage_url` firmado)
- [x] Rate limiting por clave pública + IP: `throttle:widget` (120/min lecturas) y `throttle:widget-book` (10/min reservas). Captcha diferido a fase 2 (requiere cuenta externa reCAPTCHA/hCaptcha)
- [x] Gestión sin login: rutas firmadas `GET/POST /booking/{uuid}` (`PublicBookingController` + Blade standalone) — ver detalle y **cancelar** con confirmación; reprogramar sin login = cancelar + reservar de nuevo (la reprogramación asistida vive en el panel)
- [x] Widget Vue completo en `resources/widget/`: `api.ts` (cliente tipado con X-Public-Key), `Widget.vue` (flujo sucursal → servicio por categoría → profesional/"cualquiera" → fecha inline + slots → datos del cliente → confirmación con link de gestión), theming por tenant vía `settings.branding.primary` → CSS vars `--p-primary-*`, ancho 100% del host, `box-sizing` propio — **121 KB gzip**
- [x] `config/cors.php` publicado: `api/*`, orígenes `*`, `supports_credentials:false`, preflight cacheado; validación adicional de `Origin` por empresa vía `allowed_origins` (403)
- [x] Demo de embebido: [public/widget-demo.html](public/widget-demo.html) (simula sitio de terceros, lee `?tenant=&key=`) + ruta local `/widget-demo` que la abre con la empresa seed
- [x] Tests: 11 nuevos (sin key 401, key inválida/suspendida 404, Origin no permitido 403, catálogo sin IDs internos y scopeado, slots, reserva con manage_url, conflicto 422, página firmada 403/200, cancelación firmada) — **101 tests en verde**
- [x] **Smoke test E2E real** (server + HTTP): catálogo → disponibilidad → reserva → página de gestión firmada → 401 sin key ✅. Probar visual: `php artisan serve` + `/widget-demo`

---

## Etapa 7 — Notificaciones email + reportes
**Estado:** ✅ Completada · **Depende de:** Etapa 6

- [x] 5 Mailables encolados (markdown, en español): confirmación, recordatorio, cancelación, reprogramación (sobre el turno nuevo, con horario anterior tachado) y aviso de lista de espera — todos con link firmado de gestión donde aplica
- [x] `AppointmentNotifier`: encola el mail + auditoría en `notification_logs`; el log **hereda `company_id` del turno** (los flujos públicos como el link firmado corren sin tenant). Integrado a `BookingService`: emails SIEMPRE fuera de la transacción (un rollback no encola), reprogramar suprime la confirmación duplicada (`notify: false`), cancelar promueve y avisa a la lista de espera
- [x] `appointments:send-reminders`: itera empresas activas seteando `CurrentCompany` + `setPermissionsTeamId` por vuelta, ventana configurable por empresa (`settings.reminder_hours`, default 24 h), dedupe por `notification_logs`; agendado cada 15' con `withoutOverlapping` en [routes/console.php](routes/console.php)
- [x] `ReportService` (derivado de `appointments`, sin tablas nuevas): ingresos (confirmado+completado), turnos facturables, clientes únicos, tasa de cancelación/ausencias, servicios más pedidos, y rendimiento por empleado con **ocupación** (minutos reservados / minutos de agenda publicada por `working_hours` × días del rango)
- [x] Página **Reportes** ([reports/Index.vue](resources/js/pages/reports/Index.vue)): filtro de rango (default mes en curso), 4 KPI tiles + 2 tablas (números en tokens de texto, color solo como chip de identidad del empleado), ítem en sidebar; ruta `can:reports.view` (staff 403, testeado)
- [x] Tests: 11 nuevos (confirmación+log, cancelación, reprogramación sin doble confirmación, waitlist, ventana de recordatorios + dedupe + iteración multi-empresa + empresas suspendidas excluidas + ventana por empresa, totales de reportes + scoping + permisos + rango custom) — **112 tests en verde**

Verificación manual (opcional): `composer run dev` corre el queue worker; con `MAIL_MAILER=log` los emails quedan en `storage/logs/laravel.log` (o configurá Mailpit con `MAIL_MAILER=smtp`, puerto 1025). Scheduler local: `php artisan schedule:work`.

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
