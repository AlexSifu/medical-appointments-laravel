# Seguridad

## Defensa en profundidad

| Capa | Control |
|---|---|
| Ruta | `auth` + `refresh.user` + `permission:<códigos>` (OR) → 403 si falta el permiso |
| FormRequest | Tipos, longitudes, formatos (UUID, versión `0x…`), listas cerradas; nunca IDs de actor desde el formulario |
| Service | El actor siempre sale de la sesión (`$request->actor()`); un `patient_id` enviado por un paciente se ignora |
| Repository / Executor | Solo `api.usp_*` / `api.vw_*` (lista blanca por regex), nombres constantes, parámetros enlazados |
| SQL (autoridad) | Cada SP verifica permiso (`seg.fn_TienePermiso` / `seg.usp_ExigirPermiso`) y **pertenencia** (paciente dueño, médico tratante) |
| Motor | `DENY` sobre `seg`/`clin`/`audit` para `nexa_app_role`; restricciones `CHECK` e índices únicos |

Una prueba de integración llama a los repositorios con actores sin permiso y confirma que SQL responde `SIN_PERMISO`
(`tests/Integration/ReservationRulesTest.php`).

## Autenticación

- Login por usuario o correo; mensaje **genérico** "Usuario o contraseña incorrectos." para usuario inexistente o clave errónea.
- Usuario inexistente: se ejecuta igualmente `password_verify` contra un hash ficticio (tiempo de respuesta similar).
- Intentos fallidos y bloqueo temporal los decide SQL (`MAX_INTENTOS_LOGIN`, `MINUTOS_BLOQUEO_LOGIN`).
- Rate limit adicional en Laravel: 5/min por usuario+IP y 20/min por IP (`throttle:login`).
- Hash bcrypt (`BCRYPT_ROUNDS=12`). Hashes PBKDF2-SHA256 del sistema VB.NET se aceptan y se re-hashean a bcrypt en el primer login correcto.
- Política de contraseñas para cuentas creadas por administración: mínimo 10, mayúsculas, minúsculas y números.
- `session()->regenerate()` al iniciar sesión; `invalidate()` + `regenerateToken()` al salir.
- Roles/permisos se releen cada 5 minutos: desactivar un usuario o quitarle un rol surte efecto sin esperar a que cierre sesión.

## Autorización

Ver [ROLES_PERMISOS](ROLES_PERMISOS.md). Protecciones especiales en SQL:
- Solo un SUPERADMIN puede asignar/retirar roles SUPERADMIN o ADMINISTRADOR.
- Nadie puede quitarse a sí mismo un rol de administración ni dejar el sistema sin SUPERADMIN activo.
- Un paciente solo ve y opera sus reservas; un médico solo cierra citas propias y después de su hora de inicio.

## Web

- CSRF (`@csrf`) en todos los formularios POST/PUT; 419 si falta (verificado). Los `fetch` del asistente son GET de solo lectura.
- Blade escapa toda salida (`{{ }}`); no hay `{!! !!}` en las vistas.
- Cabeceras: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`,
  `Permissions-Policy` (cámara, micrófono y geolocalización deshabilitados) y `Cache-Control: no-store, private` en páginas autenticadas.
- `X-Correlation-ID` por petición; un valor entrante que no sea UUID se descarta (no se refleja).
- Throttle en creación y reprogramación de reservas (30/min).
- Errores: el usuario ve mensajes de negocio; los detalles técnicos solo en `storage/logs` (con `APP_DEBUG=false` en producción).

## Base de datos

- La aplicación debe conectarse con un login miembro **solo** de `nexa_app_role` (nunca `sa`/`db_owner`).
- Los nombres de procedimiento nunca provienen del request; los filtros de vistas validan el nombre de columna y el `ORDER BY` es de lista blanca.
- `RESET_DEV.sql` exige `-v CONFIRMAR_RESET="SI"`, solo actúa sobre `ReservasMedicasWeb`/`_Test` y jamás sobre `ReservasMedicasDB`.
- Scripts de PowerShell rechazan el nombre de la BD legacy (`Assert-SafeDatabaseName`).

## Auditoría

`audit.BitacoraSistema` registra éxitos **y** rechazos (login, reservas, cambios administrativos, denegaciones) con usuario,
IP, User-Agent, CorrelationId y fecha UTC. Nunca guarda contraseñas, hashes ni tokens. Los auditores la consultan en `/auditoria`.

## Secretos

- Contraseñas y claves solo en `.env` (ignorado por git). `.env.example` contiene placeholders vacíos.
- Las contraseñas demo se leen de `DEMO_*_PASSWORD`; `clinic:seed-demo` las convierte a bcrypt y nunca las imprime.
- La página de login no muestra credenciales demo (hay una prueba Feature que lo verifica).
- Antes de cada commit: `git grep -n -i "password\|secret\|token\|api_key"` y revisión de resultados.

## Datos demo

Todos ficticios: documentos con prefijo `DEMO`, teléfonos `555…`, correos `@nexasalud.test`, direcciones marcadas como
ficticias, sin diagnósticos ni datos clínicos.
