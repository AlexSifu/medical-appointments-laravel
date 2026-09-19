# Cumplimiento — Prueba Técnica E2 (PHP Laravel)

Estado: **Cumplido** = implementado y verificado · **Parcial** = implementado, verificación incompleta · **Pendiente** = no hecho.

| Requisito | Implementación | Evidencia | Estado |
|---|---|---|---|
| **Migración integral** VB.NET → Laravel | Los 8 formularios legacy tienen equivalente web (login, reserva, mis citas, pacientes, médicos, agendas, reportes, administración) y se agregan los casos que faltaban: reprogramar, cierre de cita (atendida/no asistió), bloqueos, auditoría, roles | `docs/MIGRACION_DESDE_VBNET.md` (mapa formulario → ruta → controlador → SP) | Cumplido |
| Conservar SQL Server | `ReservasMedicasWeb` en SQL Server 2022, driver `sqlsrv`; sin MySQL | `config/database.php`, `scripts/check-environment.ps1` | Cumplido |
| Lógica de negocio en BD | 60 SP `api.usp_*`, 16 vistas `api.vw_*`, 10 funciones, 13 SP internos, CHECK e índices únicos filtrados | `database/sql/ReservasMedicasWeb_MASTER.sql`, `docs/CATALOGO_STORED_PROCEDURES.md`, VALIDATE 187/187 | Cumplido |
| Laravel como capa web | Controller → FormRequest → Service → Repository → `StoredProcedureExecutor` (lista blanca `api.`) | `docs/ARQUITECTURA_LARAVEL.md`, `StoredProcedureExecutorTest` | Cumplido |
| **Blade** obligatorio | 63 vistas Blade, 16 componentes (`x-app-layout`, `x-status-badge`, `x-slot-grid`, …), sin `{!! !!}`; JS solo como mejora progresiva | `resources/views/**` | Cumplido |
| **Rutas** organizadas | `routes/web.php` por área con prefijos y nombres (`admin.*`, `reception.*`, `doctor.*`, `reservations.*`, `audit.index`, `reports.index`) y middleware `auth` + `permission:*` + throttle | `php artisan route:list` (63 rutas) · `docs/ROLES_PERMISOS.md` | Cumplido |
| **Controladores** organizados | 16 controladores por área (`Admin/`, `Reception/`, `Doctor/`, `Patient/`, `Auditor/`, `Auth/`), delgados: validan con FormRequest y delegan en servicios | `app/Http/Controllers/**` | Cumplido |
| **Modelos** organizados | 13 modelos de dominio en `app/Models` (Reservation, Doctor, Patient, Schedule, TimeSlot, …) **documentales, sin Eloquent** (constantes de estados, motivos, tabla de origen) + DTO inmutables en `app/DTO`. Decisión deliberada: Eloquent permitiría escribir tablas saltándose los SP | `app/Models`, `app/DTO`, `docs/ARQUITECTURA_LARAVEL.md` §"Por qué no Eloquent" | Cumplido |
| **Vistas** por rol | Paciente (asistente de 5 pasos, mis citas, perfil), recepción (agenda del día, pacientes, reservar por otro), médico (agenda y cierre), admin (usuarios, médicos, agendas, bloqueos, catálogos, configuración), auditor (bitácora, reportes con gráficos) | `resources/views/{patient,reception,doctor,admin,auditor}` | Cumplido |
| Seguridad | bcrypt 12, rehash de PBKDF2 legacy, bloqueo por intentos (SQL), throttle, CSRF, cabeceras, RBAC doble (middleware + SP), `nexa_app_role` sin acceso a tablas | `docs/SEGURIDAD.md`, `LoginTest`, `AccessControlTest` | Cumplido |
| Doble reserva imposible | Índice único filtrado `UX_Reservas_HorarioOcupado` + `SERIALIZABLE` + `sp_getapplock` | `ReservationConcurrencyTest` (6 procesos, 1 ganador) | Cumplido |
| Validaciones críticas (§116) | 13 validaciones resueltas en SQL | `CriticalValidationsTest`, `ReservationRulesTest` — tabla en `docs/QA_TESTING.md` | Cumplido |
| Auditoría y trazabilidad | `audit.BitacoraSistema` vía `audit.usp_Registrar` en cada comando, `X-Correlation-ID` de punta a punta | pantalla `/auditoria`, smoke de reprogramación (`RESERVA_REPROGRAMADA`) | Cumplido |
| Diseño profesional y **responsive** | Bootstrap 5.3 + tema propio, grilla responsive, `offcanvas` en móvil, `table-responsive` | Revisión estática del marcado; **no verificado visualmente en móvil** | Parcial |
| Accesibilidad | Enlace "saltar al contenido", 239 atributos `aria-*`, `:focus-visible`, `prefers-reduced-motion`, etiquetas en todos los campos | `resources/views/components/*-layout.blade.php`, `resources/css/app.css` | Parcial (sin auditoría con lector de pantalla) |
| No SPA | Sin React/Vue; Vite solo empaqueta Bootstrap y 4 scripts pequeños | `package.json`, `resources/js` | Cumplido |
| Pruebas | 56 pruebas / 249 aserciones (Unit, Feature con repos simulados, Integration contra `_Test`) | `docs/QA_TESTING.md` | Cumplido |
| Setup reproducible | `scripts/setup.ps1` (no destructivo), `check-environment`, `db-validate`, `start`, `test`, `test-concurrency` | `README.md` | Cumplido |
| **Documentación** | README + 11 documentos en `docs/` (arquitectura, migración, BD, catálogo de SP, seguridad, roles, flujos, QA, cumplimiento, guía de demo, bitácora) + checklist | `docs/` | Cumplido |
| Datos demo ficticios | Nombres inventados, dominio `nexasalud.test`, documentos no reales; contraseñas solo en `.env` | `DemoSeedService`, `.env.example` | Cumplido |
| Legacy intacto | `ReservasMedicasDB` y el proyecto VB.NET no se modificaron; migración V1→V2 es un script aparte, opcional | `database/sql/ReservasMedicasWeb_MIGRATE_FROM_V1.sql` | Cumplido |
| Fuera de alcance (§110) | Sin historia clínica, farmacia, laboratorio ni facturación | — | Cumplido |
