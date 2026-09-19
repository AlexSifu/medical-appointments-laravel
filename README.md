# Nexa Salud — Gestión de Citas Médicas (Laravel + SQL Server)

Migración integral del sistema de escritorio **VB.NET / WinForms** (`medical-appointment-system-vbnet`) a una aplicación web
**Laravel 12 + Blade + Vite + Bootstrap 5.3** sobre **SQL Server**. El alcance es la gestión de reservas y citas.

> **Principio de diseño:** SQL Server es la autoridad del negocio. Laravel presenta, valida el formato y orquesta, pero cada regla
> (permisos, disponibilidad, concurrencia, estados, plazos, auditoría) se resuelve en procedimientos `api.usp_*`.
> Blade → Controller → FormRequest → Service → Repository → `api.usp_*` / `api.vw_*`.

## Funcionalidad

| Rol | Puede |
|---|---|
| Paciente | Buscar médicos, reservar en 5 pasos (especialidad → médico/sede → fecha → horario → confirmar), ver *Mis citas*, cancelar y reprogramar (respetando plazos), editar su perfil |
| Recepcionista | Registrar/editar pacientes, reservar/cancelar/reprogramar para cualquier paciente, ver la agenda del día |
| Médico | Ver su agenda y citas del día, marcar **Atendida** / **No asistió** |
| Administrador | Médicos, especialidades, sedes/consultorios, agendas (día o rango), bloqueos, usuarios, reservas |
| Superadministrador | Todo lo anterior + configuración y roles de administración |
| Auditor | Bitácora y reportes (solo lectura, exportación CSV) |

Garantías verificadas con pruebas automatizadas contra SQL Server:
- **Doble reserva imposible**: índice único filtrado + transacción `SERIALIZABLE` con locks; 6 procesos simultáneos → 1 reserva.
- **Idempotencia**: un doble clic o reintento devuelve la misma reserva (`RESERVA_EXISTENTE`).
- **Control optimista**: editar con una versión vieja devuelve `CONFLICTO_EDICION`.
- **Permisos en SQL**: aunque se llame al SP directamente, un paciente no puede reservar para otro ni un auditor escribir.

## Requisitos

| Componente | Versión probada |
|---|---|
| PHP | 8.3 (Laragon) con extensiones `sqlsrv` y `pdo_sqlsrv` |
| Composer | 2.x |
| Node / npm | 20.x / 10.x |
| SQL Server | 2022 Developer (2019+ debería funcionar: usa `AT TIME ZONE`, `STRING_SPLIT`, `CREATE OR ALTER`) |
| ODBC Driver | 17 u 18 for SQL Server |
| sqlcmd | Para aplicar los scripts |

## Puesta en marcha

### Opción A — script (Windows PowerShell)

```powershell
cd D:\Git-Hub\medical-appointments-laravel
.\scripts\check-environment.ps1           # solo lectura: verifica PHP, extensiones, Composer, Node, SQL Server
Copy-Item .env.example .env               # la primera vez; completar DB_* y DEMO_*_PASSWORD
.\scripts\setup.ps1 -WithTestDatabase     # dependencias, key, BD (crea si falta), MASTER, VALIDATE, seed, build
.\scripts\start.ps1                       # http://127.0.0.1:8000
```

`setup.ps1` nunca borra datos, nunca sobrescribe `.env` y se niega a tocar `ReservasMedicasDB` (legacy).

### Opción B — manual

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
# 1. Crear la BD vacía (una sola vez):
sqlcmd -S localhost -E -Q "IF DB_ID('ReservasMedicasWeb') IS NULL CREATE DATABASE ReservasMedicasWeb"
# 2. Aplicar el esquema (idempotente, no destructivo) y validarlo:
sqlcmd -S localhost -E -d ReservasMedicasWeb -b -f 65001 -i database\sql\ReservasMedicasWeb_MASTER.sql
sqlcmd -S localhost -E -d ReservasMedicasWeb -b -f 65001 -i database\sql\ReservasMedicasWeb_VALIDATE.sql
# 3. Datos demo ficticios (contraseñas tomadas de DEMO_*_PASSWORD en .env; nunca se imprimen):
php artisan clinic:seed-demo
npm run build
php artisan serve
```

### Conexión

`.env` admite autenticación Windows (`DB_USERNAME` vacío) o SQL. En un entorno compartido cree un login propio y agréguelo
**solo** al rol `nexa_app_role` (EXECUTE/SELECT sobre el esquema `api`); nunca use `sa` ni `db_owner` para la aplicación.
Hay una plantilla comentada al final de la sección de seguridad del MASTER.

## Scripts

| Script | Qué hace |
|---|---|
| `scripts/check-environment.ps1` | Diagnóstico de solo lectura |
| `scripts/setup.ps1` | Instalación idempotente (`-WithTestDatabase`, `-SkipDependencies`, `-SkipSeed`, `-SkipBuild`) |
| `scripts/start.ps1` | Servidor de desarrollo (compila assets si faltan) |
| `scripts/test.ps1` | Pruebas (`-Suite Unit|Feature|Integration`, `-PrepareTestDatabase`, `-Lint`) |
| `scripts/test-concurrency.ps1` | Carrera real de 6 procesos contra la BD `_Test` (`-Repeat N`) |
| `scripts/db-validate.ps1` | Ejecuta `ReservasMedicasWeb_VALIDATE.sql` |

## Base de datos

| Script | Uso |
|---|---|
| `database/sql/ReservasMedicasWeb_MASTER.sql` | Esquema completo, idempotente, sin `DROP` de datos |
| `database/sql/ReservasMedicasWeb_VALIDATE.sql` | 187 comprobaciones (objetos, índices, permisos, reglas) |
| `database/sql/ReservasMedicasWeb_MIGRATE_FROM_V1.sql` | Diagnóstico de solo lectura de la BD legacy `ReservasMedicasDB` |
| `database/sql/ReservasMedicasWeb_RESET_DEV.sql` | **Destructivo**, solo desarrollo, exige `-v CONFIRMAR_RESET="SI"` |

24 tablas (`seg`, `clin`, `audit`), 16 vistas y 60 procedimientos en el esquema `api`. Ver
[DISENO_BASE_DATOS](docs/DISENO_BASE_DATOS.md) y [CATALOGO_STORED_PROCEDURES](docs/CATALOGO_STORED_PROCEDURES.md).

## Pruebas

```powershell
.\scripts\test.ps1 -PrepareTestDatabase -Lint   # Unit + Feature (sin BD) + Integration (ReservasMedicasWeb_Test)
```

Las pruebas de integración solo corren contra una BD cuyo nombre termina en `_Test` y se omiten si no está disponible.
Detalle en [QA_TESTING](docs/QA_TESTING.md).

## Datos demo

`php artisan clinic:seed-demo` crea datos **ficticios** (documentos con prefijo reservado, teléfonos y correos `.test`, sin diagnósticos).
Los usuarios demo se definen en `.env` (`DEMO_*_USER`, `DEMO_*_PASSWORD`); las contraseñas no están en el repositorio.
