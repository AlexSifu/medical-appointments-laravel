/* ============================================================================
   Nexa Salud — ReservasMedicasWeb_MIGRATE_FROM_V1.sql
   EVALUACIÓN PREVIA DE MIGRACIÓN V1 (VB.NET, ReservasMedicasDB) → V2 (ReservasMedicasWeb)

   *** SOLO LECTURA ***  Este script NO escribe en ninguna base de datos.
   No modifica ReservasMedicasDB (legacy) ni ReservasMedicasWeb.

   Por qué no hay migración automática de datos:
   El modelo V1 no contiene información que V2 exige como obligatoria:
     - V1 no distingue paciente de usuario: no existe documento de identidad,
       fecha de nacimiento ni ficha de paciente (V2: clin.Pacientes, documento único).
     - V1 no tiene sedes ni consultorios; las agendas no indican especialidad
       (V2: agenda = médico + especialidad + sede + consultorio + tipo de atención).
     - V1 tiene 2 roles (ADMINISTRADOR, USUARIO); V2 tiene 6 roles con permisos.
     - V1 guarda fechas en hora local sin zona; V2 guarda auditoría en UTC.
   Migrar automáticamente obligaría a INVENTAR documentos, sedes, consultorios y
   especialidades. Según la regla del proyecto ("si no puede ser segura, documentar
   el plan en vez de inventar"), se entrega:
     1. este diagnóstico ejecutable (detecta V1, valida objetos, compara conteos,
        lista lo que falta para migrar), y
     2. el plan paso a paso en docs/MIGRACION_DESDE_VBNET.md.

   Compatibilidad de contraseñas: V1 usa PBKDF2-SHA256 (salt 32 bytes, 100 000
   iteraciones). V2 acepta PasswordAlgoritmo = 'PBKDF2_SHA256' con el formato
   'pbkdf2_sha256$<iteraciones>$<salt base64>$<hash base64>' y Laravel lo verifica
   (App\Support\Security\LegacyPbkdf2Hasher) y lo re-hashea a bcrypt en el primer
   login correcto. La consulta de la sección 5 muestra cómo se construiría ese valor.

   Uso:
     sqlcmd -S localhost -E -f 65001 -W -i ReservasMedicasWeb_MIGRATE_FROM_V1.sql
   ============================================================================ */
SET NOCOUNT ON;

PRINT '=== 0. RECOMENDACIÓN ===';
PRINT 'Antes de cualquier migración real: BACKUP DATABASE ReservasMedicasDB y ReservasMedicasWeb (COPY_ONLY).';
PRINT '  BACKUP DATABASE ReservasMedicasDB  TO DISK = N''<ruta>\ReservasMedicasDB_pre_v2.bak''  WITH COPY_ONLY, CHECKSUM;';
PRINT '  BACKUP DATABASE ReservasMedicasWeb TO DISK = N''<ruta>\ReservasMedicasWeb_pre_v2.bak'' WITH COPY_ONLY, CHECKSUM;';
PRINT '';

/* ---------- 1. Detectar V1 y V2 ---------- */
PRINT '=== 1. DETECCIÓN ===';
IF DB_ID(N'ReservasMedicasDB') IS NULL
BEGIN
    PRINT 'V1 (ReservasMedicasDB) NO encontrada en este servidor. Nada que evaluar.';
    RETURN;
END
PRINT 'V1 (ReservasMedicasDB) encontrada.';
IF DB_ID(N'ReservasMedicasWeb') IS NULL
    PRINT 'V2 (ReservasMedicasWeb) NO existe todavía: ejecute primero ReservasMedicasWeb_MASTER.sql.';
ELSE
    PRINT 'V2 (ReservasMedicasWeb) encontrada.';
PRINT '';
GO

/* ---------- 2. Validar objetos V1 esperados ---------- */
PRINT '=== 2. OBJETOS V1 ===';
SELECT v.Tabla,
       CASE WHEN t.object_id IS NULL THEN 'FALTA' ELSE 'OK' END AS Estado
FROM (VALUES (N'Usuarios'), (N'Roles'), (N'Medicos'), (N'Especialidades'), (N'MedicoEspecialidad'),
             (N'AgendasMedicas'), (N'HorariosMedicos'), (N'Reservas'), (N'EstadosReserva'), (N'BitacoraSistema')) v (Tabla)
LEFT JOIN ReservasMedicasDB.sys.tables t
       ON t.name COLLATE DATABASE_DEFAULT = v.Tabla AND t.schema_id = 1;
GO

/* ---------- 3. Conteos V1 vs V2 ---------- */
PRINT '=== 3. CONTEOS V1 vs V2 ===';
IF DB_ID(N'ReservasMedicasWeb') IS NOT NULL AND OBJECT_ID(N'ReservasMedicasWeb.clin.Reservas') IS NOT NULL
    SELECT x.Entidad, x.V1, x.V2
    FROM (VALUES
        (N'Usuarios',         (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.Usuarios),       (SELECT COUNT(*) FROM ReservasMedicasWeb.seg.Usuarios)),
        (N'Médicos',          (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.Medicos),        (SELECT COUNT(*) FROM ReservasMedicasWeb.clin.Medicos)),
        (N'Especialidades',   (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.Especialidades), (SELECT COUNT(*) FROM ReservasMedicasWeb.clin.Especialidades)),
        (N'Agendas',          (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.AgendasMedicas), (SELECT COUNT(*) FROM ReservasMedicasWeb.clin.AgendasMedicas)),
        (N'Horarios (slots)', (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.HorariosMedicos),(SELECT COUNT(*) FROM ReservasMedicasWeb.clin.HorariosMedicos)),
        (N'Reservas',         (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.Reservas),       (SELECT COUNT(*) FROM ReservasMedicasWeb.clin.Reservas)),
        (N'Bitácora',         (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.BitacoraSistema),(SELECT COUNT(*) FROM ReservasMedicasWeb.audit.BitacoraSistema))
    ) x (Entidad, V1, V2);
ELSE
    SELECT N'Usuarios' AS Entidad, COUNT(*) AS V1 FROM ReservasMedicasDB.dbo.Usuarios
    UNION ALL SELECT N'Médicos', COUNT(*) FROM ReservasMedicasDB.dbo.Medicos
    UNION ALL SELECT N'Agendas', COUNT(*) FROM ReservasMedicasDB.dbo.AgendasMedicas
    UNION ALL SELECT N'Reservas', COUNT(*) FROM ReservasMedicasDB.dbo.Reservas;
GO

/* ---------- 4. Brechas de datos (lo que V2 exige y V1 no tiene) ---------- */
PRINT '=== 4. BRECHAS QUE IMPIDEN UNA MIGRACIÓN AUTOMÁTICA SEGURA ===';
SELECT b.Brecha, b.Registros, b.Accion
FROM (VALUES
    (N'Usuarios rol USUARIO sin ficha de paciente (sin documento, fecha nac.)',
        (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.Usuarios u JOIN ReservasMedicasDB.dbo.Roles r ON r.RolId = u.RolId WHERE r.Nombre = 'USUARIO'),
        N'Recepción registra la ficha (api.usp_RecepcionPacienteCrear) y vincula la cuenta.'),
    (N'Médicos sin CMP',
        (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.Medicos WHERE CMP IS NULL OR LTRIM(RTRIM(CMP)) = ''),
        N'Completar CMP antes de crear el médico en V2 (dato obligatorio y único).'),
    (N'Médicos sin especialidad principal',
        (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.Medicos m WHERE NOT EXISTS (SELECT 1 FROM ReservasMedicasDB.dbo.MedicoEspecialidad me WHERE me.MedicoId = m.MedicoId AND me.EsPrincipal = 1)),
        N'Definir especialidad principal.'),
    (N'Agendas V1 (sin sede, consultorio ni especialidad)',
        (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.AgendasMedicas),
        N'Definir sede/consultorio por médico; recrear agendas FUTURAS con api.usp_AdminAgendaCrear.'),
    (N'Agendas V1 futuras (a recrear)',
        (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.AgendasMedicas WHERE Fecha >= CAST(GETDATE() AS DATE) AND Activo = 1),
        N'Recrear vía SP; las pasadas quedan como histórico en V1.'),
    (N'Reservas V1 confirmadas futuras',
        (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.Reservas r JOIN ReservasMedicasDB.dbo.HorariosMedicos h ON h.HorarioMedicoId = r.HorarioMedicoId
         JOIN ReservasMedicasDB.dbo.AgendasMedicas a ON a.AgendaId = h.AgendaId
         WHERE r.EstadoReservaId = 1 AND a.Fecha >= CAST(GETDATE() AS DATE)),
        N'Re-registrar vía api.usp_RecepcionReservaCrear tras migrar paciente y agenda.'),
    (N'Reservas V1 históricas (pasadas o canceladas)',
        (SELECT COUNT(*) FROM ReservasMedicasDB.dbo.Reservas r JOIN ReservasMedicasDB.dbo.HorariosMedicos h ON h.HorarioMedicoId = r.HorarioMedicoId
         JOIN ReservasMedicasDB.dbo.AgendasMedicas a ON a.AgendaId = h.AgendaId
         WHERE r.EstadoReservaId <> 1 OR a.Fecha < CAST(GETDATE() AS DATE)),
        N'Conservar en V1 (solo lectura) como archivo histórico; no se reescribe.'),
    (N'Usuarios con correo duplicado (V2 exige correo único)',
        (SELECT COUNT(*) FROM (SELECT Email FROM ReservasMedicasDB.dbo.Usuarios WHERE Email IS NOT NULL GROUP BY Email HAVING COUNT(*) > 1) d),
        N'Depurar duplicados.')
) b (Brecha, Registros, Accion);
GO

/* ---------- 5. Vista previa del mapeo de cuentas (sin hashes) ---------- */
PRINT '=== 5. MAPEO PROPUESTO DE CUENTAS (vista previa, sin datos sensibles) ===';
SELECT u.UsuarioId                                   AS UsuarioIdV1,
       u.NombreUsuario,
       r.Nombre                                      AS RolV1,
       CASE r.Nombre WHEN 'ADMINISTRADOR' THEN 'ADMINISTRADOR' ELSE 'PACIENTE' END AS RolV2Propuesto,
       CASE WHEN u.Activo = 1 THEN 'Activo' ELSE 'Inactivo' END AS Estado,
       'PBKDF2_SHA256'                               AS AlgoritmoV2,
       u.Iteraciones,
       DATALENGTH(u.PasswordSalt)                    AS BytesSalt,
       DATALENGTH(u.PasswordHash)                    AS BytesHash,
       CASE WHEN u.NombreUsuario LIKE '% %' OR LEN(u.NombreUsuario) < 3 THEN 'Revisar nombre de usuario' ELSE 'OK' END AS Observacion
FROM ReservasMedicasDB.dbo.Usuarios u
JOIN ReservasMedicasDB.dbo.Roles r ON r.RolId = u.RolId
ORDER BY u.UsuarioId;

-- Formato V2 del hash legacy (NO se imprime el valor; solo se valida que sea construible):
--   'pbkdf2_sha256$' + CONVERT(VARCHAR(10), Iteraciones) + '$'
--   + CAST(N'' AS XML).value('xs:base64Binary(sql:column("PasswordSalt"))', 'VARCHAR(64)') + '$'
--   + CAST(N'' AS XML).value('xs:base64Binary(sql:column("PasswordHash"))', 'VARCHAR(100)')
SELECT COUNT(*) AS CuentasConHashConvertible
FROM ReservasMedicasDB.dbo.Usuarios
WHERE Iteraciones > 0 AND DATALENGTH(PasswordSalt) >= 16 AND DATALENGTH(PasswordHash) >= 32;
GO

PRINT '';
PRINT '=== RESULTADO ===';
PRINT 'Migración automática NO ejecutada (por diseño). Ver docs/MIGRACION_DESDE_VBNET.md para el procedimiento asistido.';
PRINT 'ReservasMedicasDB no fue modificada.';
GO
