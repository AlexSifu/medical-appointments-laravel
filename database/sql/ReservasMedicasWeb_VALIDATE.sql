/* ============================================================================
   Nexa Salud — ReservasMedicasWeb_VALIDATE.sql
   Validación estructural y de integridad. SOLO LECTURA: no modifica nada.

   Uso:
     sqlcmd -S localhost -E -d ReservasMedicasWeb -f 65001 -W -i ReservasMedicasWeb_VALIDATE.sql

   Termina con un resumen legible y con la línea:
     VALIDACION: OK            (todas las comprobaciones superadas)
     VALIDACION: FALLIDA (n)   (n comprobaciones fallidas; RAISERROR nivel 16 para -b)
   ============================================================================ */
SET NOCOUNT ON;

IF OBJECT_ID('tempdb..#Chk') IS NOT NULL DROP TABLE #Chk;
CREATE TABLE #Chk
(
    Id         INT IDENTITY(1,1) PRIMARY KEY,
    Categoria  VARCHAR(30)    NOT NULL,
    Elemento   NVARCHAR(200)  NOT NULL,
    Esperado   NVARCHAR(100)  NULL,
    Encontrado NVARCHAR(100)  NULL,
    Ok         BIT            NOT NULL
);

/* ---------- 1. Esquemas ---------- */
INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'ESQUEMA', v.n, N'existe', CASE WHEN SCHEMA_ID(v.n) IS NULL THEN N'falta' ELSE N'existe' END,
       CASE WHEN SCHEMA_ID(v.n) IS NULL THEN 0 ELSE 1 END
FROM (VALUES (N'seg'), (N'clin'), (N'audit'), (N'api')) v (n);

/* ---------- 2. Objetos esperados ---------- */
DECLARE @Obj TABLE (Nombre NVARCHAR(200), Tipo VARCHAR(2), Categoria VARCHAR(30));
INSERT @Obj (Nombre, Tipo, Categoria) VALUES
-- Tablas
('seg.Roles','U','TABLA'),('seg.Permisos','U','TABLA'),('seg.RolPermiso','U','TABLA'),('seg.Usuarios','U','TABLA'),
('seg.UsuarioRol','U','TABLA'),('seg.ConfiguracionSistema','U','TABLA'),
('clin.Especialidades','U','TABLA'),('clin.Sedes','U','TABLA'),('clin.Consultorios','U','TABLA'),('clin.TiposAtencion','U','TABLA'),
('clin.Pacientes','U','TABLA'),('clin.Medicos','U','TABLA'),('clin.MedicoEspecialidad','U','TABLA'),('clin.MedicoSede','U','TABLA'),
('clin.PlantillasAgenda','U','TABLA'),('clin.AgendasMedicas','U','TABLA'),('clin.BloqueosMedico','U','TABLA'),('clin.HorariosMedicos','U','TABLA'),
('clin.EstadosReserva','U','TABLA'),('clin.TransicionesEstadoReserva','U','TABLA'),('clin.MotivosCancelacion','U','TABLA'),
('clin.Reservas','U','TABLA'),('clin.ReservaHistorial','U','TABLA'),('audit.BitacoraSistema','U','TABLA'),
-- Vistas
('api.vw_AgendasDetalle','V','VISTA'),('api.vw_AuditoriaDetalle','V','VISTA'),('api.vw_Configuracion','V','VISTA'),('api.vw_Consultorios','V','VISTA'),
('api.vw_Especialidades','V','VISTA'),('api.vw_EstadosReserva','V','VISTA'),('api.vw_HorariosDisponibilidad','V','VISTA'),('api.vw_MedicosCatalogo','V','VISTA'),
('api.vw_MotivosCancelacion','V','VISTA'),('api.vw_PacientesResumen','V','VISTA'),('api.vw_Permisos','V','VISTA'),('api.vw_ReservasDetalle','V','VISTA'),
('api.vw_Roles','V','VISTA'),('api.vw_Sedes','V','VISTA'),('api.vw_TiposAtencion','V','VISTA'),('api.vw_UsuariosRoles','V','VISTA'),
-- Funciones
('clin.fn_AhoraLocal','FN','FUNCION'),('clin.fn_FechaHora','FN','FUNCION'),('clin.fn_LocalAUtc','FN','FUNCION'),('clin.fn_PuedeVerReserva','FN','FUNCION'),
('seg.fn_ConfigEntero','FN','FUNCION'),('seg.fn_TienePermiso','FN','FUNCION'),('seg.fn_TieneRol','FN','FUNCION'),
('clin.fn_DisponibilidadMedico','IF','FUNCION'),('clin.fn_HorariosReservables','IF','FUNCION'),('seg.fn_PermisosUsuario','IF','FUNCION'),
-- Procedimientos internos
('audit.usp_Registrar','P','SP_INTERNO'),('seg.usp_ErrorCapturado','P','SP_INTERNO'),('seg.usp_ExigirPermiso','P','SP_INTERNO'),
('seg.usp_ObtenerLock','P','SP_INTERNO'),('seg.usp_Rechazo','P','SP_INTERNO'),('seg.usp_Resultado','P','SP_INTERNO'),
('clin.usp_AgendaCrearInterno','P','SP_INTERNO'),('clin.usp_AgendaValidarMaestros','P','SP_INTERNO'),('clin.usp_PacienteValidarDatos','P','SP_INTERNO'),
('clin.usp_ReporteRango','P','SP_INTERNO'),('clin.usp_ReservaCerrar','P','SP_INTERNO'),('clin.usp_ReservaTomarLocks','P','SP_INTERNO'),
('clin.usp_ReservaValidarEInsertar','P','SP_INTERNO'),
-- Procedimientos api (contrato con Laravel)
('api.usp_AuthObtenerUsuario','P','SP_API'),('api.usp_AuthObtenerPermisosUsuario','P','SP_API'),('api.usp_AuthRegistrarExito','P','SP_API'),
('api.usp_AuthRegistrarFallo','P','SP_API'),('api.usp_AuthRegistrarLogout','P','SP_API'),('api.usp_UsuarioActualizarPasswordHash','P','SP_API'),
('api.usp_SistemaInicializarSuperadmin','P','SP_API'),
('api.usp_AdminUsuarioCrear','P','SP_API'),('api.usp_AdminUsuarioActualizar','P','SP_API'),('api.usp_AdminUsuarioAsignarRol','P','SP_API'),
('api.usp_AdminUsuarioObtener','P','SP_API'),('api.usp_AdminUsuariosBuscar','P','SP_API'),('api.usp_AdminUsuarioVincularPerfil','P','SP_API'),
('api.usp_AdminConfiguracionActualizar','P','SP_API'),('api.usp_AdminEspecialidadGuardar','P','SP_API'),('api.usp_AdminSedeGuardar','P','SP_API'),
('api.usp_AdminConsultorioGuardar','P','SP_API'),
('api.usp_PacienteBuscar','P','SP_API'),('api.usp_PacienteObtener','P','SP_API'),('api.usp_PacienteActualizarPerfil','P','SP_API'),
('api.usp_RecepcionPacienteCrear','P','SP_API'),('api.usp_RecepcionPacienteActualizar','P','SP_API'),
('api.usp_MedicosBuscar','P','SP_API'),('api.usp_MedicoObtener','P','SP_API'),('api.usp_AdminMedicoCrear','P','SP_API'),
('api.usp_AdminMedicoActualizar','P','SP_API'),('api.usp_AdminMedicoCambiarEstado','P','SP_API'),('api.usp_AdminMedicoAsignarEspecialidad','P','SP_API'),
('api.usp_AdminMedicoAsignarSede','P','SP_API'),
('api.usp_AdminAgendaCrear','P','SP_API'),('api.usp_AdminAgendaGenerarRango','P','SP_API'),('api.usp_AdminAgendaListar','P','SP_API'),
('api.usp_AdminAgendaDesactivar','P','SP_API'),('api.usp_AdminHorarioBloquear','P','SP_API'),('api.usp_AdminHorarioDesbloquear','P','SP_API'),
('api.usp_AdminBloqueosListar','P','SP_API'),('api.usp_AgendaFechasDisponibles','P','SP_API'),('api.usp_AgendaHorariosDisponibles','P','SP_API'),('api.usp_AgendaHorariosEstado','P','SP_API'),
('api.usp_AgendaObtenerDia','P','SP_API'),('api.usp_MedicoAgendaPropia','P','SP_API'),
('api.usp_ReservaCrear','P','SP_API'),('api.usp_RecepcionReservaCrear','P','SP_API'),('api.usp_ReservaCancelar','P','SP_API'),
('api.usp_ReservaReprogramar','P','SP_API'),('api.usp_ReservaObtenerDetalle','P','SP_API'),('api.usp_ReservaHistorial','P','SP_API'),
('api.usp_ReservaMisReservas','P','SP_API'),('api.usp_AdminReservasBuscar','P','SP_API'),('api.usp_MedicoReservasDia','P','SP_API'),
('api.usp_MedicoMarcarAtendida','P','SP_API'),('api.usp_MedicoMarcarNoAsistio','P','SP_API'),
('api.usp_AuditoriaBuscar','P','SP_API'),('api.usp_AuditoriaAcciones','P','SP_API'),('api.usp_DashboardResumen','P','SP_API'),('api.usp_DashboardSerieDiaria','P','SP_API'),
('api.usp_ReporteReservasPorEspecialidad','P','SP_API'),('api.usp_ReporteOcupacionMedicos','P','SP_API'),
('api.usp_ReporteCancelaciones','P','SP_API'),('api.usp_ReporteNoAsistencia','P','SP_API');

INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT o.Categoria, o.Nombre, N'existe',
       CASE WHEN x.object_id IS NULL THEN N'falta' ELSE N'existe' END,
       CASE WHEN x.object_id IS NULL THEN 0 ELSE 1 END
FROM @Obj o
LEFT JOIN sys.objects x ON x.object_id = OBJECT_ID(o.Nombre) AND RTRIM(x.type) COLLATE DATABASE_DEFAULT = o.Tipo COLLATE DATABASE_DEFAULT;

/* ---------- 3. Módulos compilables (dependencias rotas) ---------- */
INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'DEPENDENCIA', CONCAT(OBJECT_SCHEMA_NAME(d.referencing_id), '.', OBJECT_NAME(d.referencing_id), N' -> ', d.referenced_schema_name, '.', d.referenced_entity_name),
       N'resuelta', N'no existe', 0
FROM sys.sql_expression_dependencies d
WHERE d.referenced_id IS NULL AND d.is_ambiguous = 0 AND d.referenced_database_name IS NULL
  AND d.referenced_schema_name IN (N'seg', N'clin', N'audit', N'api')
  AND OBJECT_ID(QUOTENAME(d.referenced_schema_name) + '.' + QUOTENAME(d.referenced_entity_name)) IS NULL
  AND TYPE_ID(QUOTENAME(d.referenced_schema_name) + '.' + QUOTENAME(d.referenced_entity_name)) IS NULL;

/* ---------- 4. Constraints e índices críticos ---------- */
INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'CONSTRAINT', v.n, N'existe y confiable',
       CASE WHEN c.object_id IS NULL THEN N'falta'
            WHEN ISNULL(fk.is_not_trusted, ISNULL(ck.is_not_trusted, 0)) = 1 THEN N'NO confiable'
            WHEN ISNULL(fk.is_disabled, ISNULL(ck.is_disabled, 0)) = 1 THEN N'deshabilitado' ELSE N'ok' END,
       CASE WHEN c.object_id IS NULL OR ISNULL(fk.is_not_trusted, ISNULL(ck.is_not_trusted, 0)) = 1
                 OR ISNULL(fk.is_disabled, ISNULL(ck.is_disabled, 0)) = 1 THEN 0 ELSE 1 END
FROM (VALUES (N'CK_Reservas_OcupaHorario'), (N'CK_Reservas_Horas'), (N'CK_Reservas_Cancelacion'), (N'CK_Reservas_Reprogramada'),
             (N'CK_Reservas_Cierre'), (N'CK_Horarios_Horas'), (N'CK_Horarios_Bloqueo'), (N'CK_Usuarios_Algoritmo'), (N'CK_Usuarios_Email'),
             (N'FK_Reservas_Horario'), (N'FK_Reservas_Paciente'), (N'FK_Reservas_Estado'), (N'FK_Horarios_Agenda')) v (n)
LEFT JOIN sys.objects c            ON c.name COLLATE DATABASE_DEFAULT = v.n AND c.type IN ('C', 'F')
LEFT JOIN sys.foreign_keys fk      ON fk.object_id = c.object_id
LEFT JOIN sys.check_constraints ck ON ck.object_id = c.object_id;

INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'CONSTRAINT', N'Claves foráneas / checks no confiables o deshabilitados', N'0', CONVERT(NVARCHAR(10), COUNT(*)), CASE WHEN COUNT(*) = 0 THEN 1 ELSE 0 END
FROM (SELECT object_id, schema_id FROM sys.foreign_keys WHERE is_not_trusted = 1 OR is_disabled = 1
      UNION ALL SELECT object_id, schema_id FROM sys.check_constraints WHERE is_not_trusted = 1 OR is_disabled = 1) x
WHERE SCHEMA_NAME(x.schema_id) IN (N'seg', N'clin', N'audit');

INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'INDICE_UNICO', v.n, N'único' + CASE WHEN v.filtro = 1 THEN N' filtrado' ELSE N'' END,
       CASE WHEN i.index_id IS NULL THEN N'falta' WHEN i.is_unique = 0 THEN N'no único'
            WHEN v.filtro = 1 AND i.has_filter = 0 THEN N'sin filtro' ELSE N'ok' END,
       CASE WHEN i.index_id IS NULL OR i.is_unique = 0 OR (v.filtro = 1 AND i.has_filter = 0) THEN 0 ELSE 1 END
FROM (VALUES (N'clin.Reservas', N'UX_Reservas_HorarioOcupado', 1), (N'clin.Reservas', N'UX_Reservas_IdempotencyKey', 0),
             (N'clin.Reservas', N'UX_Reservas_Codigo', 0), (N'seg.Usuarios', N'UX_Usuarios_Email', 1),
             (N'clin.Pacientes', N'UX_Pacientes_Usuario', 1), (N'clin.Medicos', N'UX_Medicos_Usuario', 1),
             (N'clin.MedicoEspecialidad', N'UX_MedicoEspecialidad_Principal', 1)) v (t, n, filtro)
LEFT JOIN sys.indexes i ON i.object_id = OBJECT_ID(v.t) AND i.name COLLATE DATABASE_DEFAULT = v.n;

INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'ROWVERSION', v.t + N'.VersionFila', N'rowversion',
       ISNULL(TYPE_NAME(c.system_type_id), N'falta'), CASE WHEN c.system_type_id = 189 THEN 1 ELSE 0 END
FROM (VALUES (N'seg.Usuarios'), (N'clin.Pacientes'), (N'clin.Medicos'), (N'clin.AgendasMedicas'), (N'clin.HorariosMedicos'), (N'clin.Reservas')) v (t)
LEFT JOIN sys.columns c ON c.object_id = OBJECT_ID(v.t) AND c.name = N'VersionFila';

/* ---------- 5. Catálogos, roles y permisos ---------- */
INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'CATALOGO', v.n, CONVERT(NVARCHAR(10), v.esperado), CONVERT(NVARCHAR(10), v.encontrado), CASE WHEN v.encontrado >= v.esperado THEN 1 ELSE 0 END
FROM (VALUES
    (N'seg.Roles (6 roles base)',                 6,  (SELECT COUNT(*) FROM seg.Roles WHERE Codigo IN ('SUPERADMIN','ADMINISTRADOR','RECEPCIONISTA','MEDICO','PACIENTE','AUDITOR'))),
    (N'seg.Permisos',                             25, (SELECT COUNT(*) FROM seg.Permisos)),
    (N'seg.ConfiguracionSistema',                 6,  (SELECT COUNT(*) FROM seg.ConfiguracionSistema)),
    (N'clin.EstadosReserva',                      5,  (SELECT COUNT(*) FROM clin.EstadosReserva)),
    (N'clin.TransicionesEstadoReserva',           4,  (SELECT COUNT(*) FROM clin.TransicionesEstadoReserva)),
    (N'clin.MotivosCancelacion',                  6,  (SELECT COUNT(*) FROM clin.MotivosCancelacion)),
    (N'clin.TiposAtencion',                       2,  (SELECT COUNT(*) FROM clin.TiposAtencion))
) v (n, esperado, encontrado);

INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'RBAC', N'Permisos del rol ' + r.Codigo, N'>= 1', CONVERT(NVARCHAR(10), COUNT(rp.PermisoId)), CASE WHEN COUNT(rp.PermisoId) > 0 THEN 1 ELSE 0 END
FROM seg.Roles r LEFT JOIN seg.RolPermiso rp ON rp.RolId = r.RolId
GROUP BY r.Codigo;

INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'RBAC', v.n, v.e, CASE WHEN v.ok = 1 THEN v.e ELSE N'incorrecto' END, v.ok
FROM (VALUES
    (N'SUPERADMIN tiene todos los permisos', N'sí',
        CASE WHEN (SELECT COUNT(*) FROM seg.Permisos) = (SELECT COUNT(*) FROM seg.RolPermiso rp JOIN seg.Roles r ON r.RolId = rp.RolId WHERE r.Codigo = 'SUPERADMIN') THEN 1 ELSE 0 END),
    (N'AUDITOR sin permisos de escritura', N'sí',
        CASE WHEN NOT EXISTS (SELECT 1 FROM seg.RolPermiso rp JOIN seg.Roles r ON r.RolId = rp.RolId JOIN seg.Permisos p ON p.PermisoId = rp.PermisoId
                              WHERE r.Codigo = 'AUDITOR' AND p.Codigo NOT IN ('auditoria.ver', 'reportes.ver') AND p.Codigo NOT LIKE '%.ver') THEN 1 ELSE 0 END),
    (N'PACIENTE sin reservas.gestionar', N'sí',
        CASE WHEN NOT EXISTS (SELECT 1 FROM seg.RolPermiso rp JOIN seg.Roles r ON r.RolId = rp.RolId JOIN seg.Permisos p ON p.PermisoId = rp.PermisoId
                              WHERE r.Codigo = 'PACIENTE' AND p.Codigo = 'reservas.gestionar') THEN 1 ELSE 0 END)
) v (n, e, ok);

/* ---------- 6. Permisos de base de datos (mínimo privilegio) ---------- */
INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'SEGURIDAD_BD', v.n, v.e, CASE WHEN v.ok = 1 THEN v.e ELSE N'incorrecto' END, v.ok
FROM (VALUES
    (N'Rol nexa_app_role', N'existe', CASE WHEN DATABASE_PRINCIPAL_ID(N'nexa_app_role') IS NOT NULL THEN 1 ELSE 0 END),
    (N'nexa_app_role: EXECUTE en api', N'GRANT',
        CASE WHEN EXISTS (SELECT 1 FROM sys.database_permissions WHERE grantee_principal_id = DATABASE_PRINCIPAL_ID(N'nexa_app_role')
                          AND class = 3 AND major_id = SCHEMA_ID(N'api') AND permission_name = 'EXECUTE' AND state = 'G') THEN 1 ELSE 0 END),
    (N'nexa_app_role: DENY SELECT en clin/seg/audit', N'DENY x3',
        CASE WHEN (SELECT COUNT(*) FROM sys.database_permissions WHERE grantee_principal_id = DATABASE_PRINCIPAL_ID(N'nexa_app_role')
                   AND class = 3 AND major_id IN (SCHEMA_ID(N'clin'), SCHEMA_ID(N'seg'), SCHEMA_ID(N'audit')) AND permission_name = 'SELECT' AND state = 'D') = 3 THEN 1 ELSE 0 END),
    (N'Objetos de dominio propiedad de dbo (encadenamiento)', N'sí',
        CASE WHEN NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name IN (N'seg', N'clin', N'audit', N'api') AND principal_id <> 1) THEN 1 ELSE 0 END)
) v (n, e, ok);

/* ---------- 7. Integridad de datos ---------- */
INSERT #Chk (Categoria, Elemento, Esperado, Encontrado, Ok)
SELECT 'DATOS', v.n, N'0', CONVERT(NVARCHAR(10), v.c), CASE WHEN v.c = 0 THEN 1 ELSE 0 END
FROM (VALUES
    (N'Slots con más de una reserva que ocupa',
        (SELECT COUNT(*) FROM (SELECT HorarioMedicoId FROM clin.Reservas WHERE OcupaHorario = 1 GROUP BY HorarioMedicoId HAVING COUNT(*) > 1) x)),
    (N'Reservas cuya instantánea no coincide con el slot',
        (SELECT COUNT(*) FROM clin.Reservas r JOIN clin.HorariosMedicos h ON h.HorarioMedicoId = r.HorarioMedicoId
         JOIN clin.AgendasMedicas a ON a.AgendaId = h.AgendaId
         WHERE r.MedicoId <> a.MedicoId OR r.FechaCita <> a.Fecha OR r.HoraInicio <> h.HoraInicio OR r.HoraFin <> h.HoraFin
            OR r.EspecialidadId <> a.EspecialidadId OR r.SedeId <> a.SedeId OR r.ConsultorioId <> a.ConsultorioId)),
    (N'Pacientes con citas activas solapadas',
        (SELECT COUNT(*) FROM clin.Reservas r1 JOIN clin.Reservas r2
            ON r2.PacienteId = r1.PacienteId AND r2.ReservaId > r1.ReservaId AND r2.FechaCita = r1.FechaCita
           AND r1.EstadoReservaId = 1 AND r2.EstadoReservaId = 1
           AND r2.HoraInicio < r1.HoraFin AND r1.HoraInicio < r2.HoraFin)),
    (N'Médicos con agendas activas solapadas',
        (SELECT COUNT(*) FROM clin.AgendasMedicas a1 JOIN clin.AgendasMedicas a2
            ON a2.MedicoId = a1.MedicoId AND a2.AgendaId > a1.AgendaId AND a2.Fecha = a1.Fecha AND a1.Activo = 1 AND a2.Activo = 1
           AND a2.HoraInicio < a1.HoraFin AND a1.HoraInicio < a2.HoraFin)),
    (N'Consultorios con agendas presenciales solapadas',
        (SELECT COUNT(*) FROM clin.AgendasMedicas a1 JOIN clin.AgendasMedicas a2
            ON a2.ConsultorioId = a1.ConsultorioId AND a2.AgendaId > a1.AgendaId AND a2.Fecha = a1.Fecha AND a1.Activo = 1 AND a2.Activo = 1
           AND a1.TipoAtencionId = 1 AND a2.TipoAtencionId = 1
           AND a2.HoraInicio < a1.HoraFin AND a1.HoraInicio < a2.HoraFin)),
    (N'Reprogramadas sin reserva de reemplazo enlazada',
        (SELECT COUNT(*) FROM clin.Reservas r LEFT JOIN clin.Reservas n ON n.ReservaId = r.ReservaReemplazoId
         WHERE r.EstadoReservaId = 3 AND (n.ReservaId IS NULL OR n.ReservaOrigenId <> r.ReservaId))),
    (N'Reservas sin historial inicial',
        (SELECT COUNT(*) FROM clin.Reservas r WHERE NOT EXISTS (SELECT 1 FROM clin.ReservaHistorial h WHERE h.ReservaId = r.ReservaId AND h.EstadoAnteriorId IS NULL))),
    (N'Reservas cuyo estado no coincide con el último historial',
        (SELECT COUNT(*) FROM clin.Reservas r
         CROSS APPLY (SELECT TOP (1) h.EstadoNuevoId FROM clin.ReservaHistorial h WHERE h.ReservaId = r.ReservaId ORDER BY h.FechaUtc DESC, h.ReservaHistorialId DESC) u
         WHERE u.EstadoNuevoId <> r.EstadoReservaId)),
    (N'Slots bloqueados sin bloqueo activo',
        (SELECT COUNT(*) FROM clin.HorariosMedicos h LEFT JOIN clin.BloqueosMedico b ON b.BloqueoId = h.BloqueoId
         WHERE h.Bloqueado = 1 AND (b.BloqueoId IS NULL OR b.Activo = 0))),
    (N'Usuarios PACIENTE sin perfil de paciente activo',
        (SELECT COUNT(*) FROM seg.UsuarioRol ur JOIN seg.Roles r ON r.RolId = ur.RolId AND r.Codigo = 'PACIENTE'
         JOIN seg.Usuarios u ON u.UsuarioId = ur.UsuarioId AND u.Activo = 1
         WHERE NOT EXISTS (SELECT 1 FROM clin.Pacientes p WHERE p.UsuarioId = u.UsuarioId))),
    (N'Usuarios MEDICO sin perfil de médico',
        (SELECT COUNT(*) FROM seg.UsuarioRol ur JOIN seg.Roles r ON r.RolId = ur.RolId AND r.Codigo = 'MEDICO'
         JOIN seg.Usuarios u ON u.UsuarioId = ur.UsuarioId AND u.Activo = 1
         WHERE NOT EXISTS (SELECT 1 FROM clin.Medicos m WHERE m.UsuarioId = u.UsuarioId))),
    (N'Hashes con algoritmo desconocido o texto plano aparente',
        (SELECT COUNT(*) FROM seg.Usuarios WHERE PasswordAlgoritmo NOT IN ('BCRYPT', 'ARGON2ID', 'PBKDF2_SHA256')
            OR (PasswordAlgoritmo = 'BCRYPT' AND PasswordHash NOT LIKE '$2_$%')
            OR (PasswordAlgoritmo = 'ARGON2ID' AND PasswordHash NOT LIKE '$argon2id$%'))),
    (N'Superadministradores activos = 0',
        CASE WHEN EXISTS (SELECT 1 FROM seg.UsuarioRol ur JOIN seg.Roles r ON r.RolId = ur.RolId AND r.Codigo = 'SUPERADMIN'
                          JOIN seg.Usuarios u ON u.UsuarioId = ur.UsuarioId AND u.Activo = 1)
                  OR NOT EXISTS (SELECT 1 FROM seg.Usuarios) THEN 0 ELSE 1 END)
) v (n, c);

/* ---------- 8. Resumen ---------- */
PRINT '';
PRINT '==================== DETALLE DE FALLOS ====================';
SELECT Categoria, Elemento, Esperado, Encontrado FROM #Chk WHERE Ok = 0 ORDER BY Id;

PRINT '';
PRINT '==================== RESUMEN POR CATEGORÍA ====================';
SELECT Categoria, COUNT(*) AS Comprobaciones, SUM(CONVERT(INT, Ok)) AS Correctas, COUNT(*) - SUM(CONVERT(INT, Ok)) AS Fallidas
FROM #Chk GROUP BY Categoria ORDER BY MIN(Id);

PRINT '';
PRINT '==================== DATOS ACTUALES ====================';
SELECT (SELECT COUNT(*) FROM seg.Usuarios) AS Usuarios, (SELECT COUNT(*) FROM clin.Pacientes) AS Pacientes,
       (SELECT COUNT(*) FROM clin.Medicos) AS Medicos, (SELECT COUNT(*) FROM clin.AgendasMedicas) AS Agendas,
       (SELECT COUNT(*) FROM clin.HorariosMedicos) AS Horarios, (SELECT COUNT(*) FROM clin.Reservas) AS Reservas,
       (SELECT COUNT(*) FROM audit.BitacoraSistema) AS Bitacora;

DECLARE @Total INT = (SELECT COUNT(*) FROM #Chk), @Fallos INT = (SELECT COUNT(*) FROM #Chk WHERE Ok = 0);
PRINT '';
PRINT CONCAT('Base de datos : ', DB_NAME(), '   Servidor: ', @@SERVERNAME, '   Fecha: ', CONVERT(VARCHAR(19), SYSDATETIME(), 120));
PRINT CONCAT('Comprobaciones: ', @Total, '   Correctas: ', @Total - @Fallos, '   Fallidas: ', @Fallos);
IF @Fallos = 0
    PRINT 'VALIDACION: OK';
ELSE
    RAISERROR('VALIDACION: FALLIDA (%d)', 16, 1, @Fallos);
