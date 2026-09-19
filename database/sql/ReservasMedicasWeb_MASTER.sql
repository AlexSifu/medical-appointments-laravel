/*
===============================================================================
  NEXA SALUD — Gestión de Citas Médicas
  SCRIPT MASTER V2 — ReservasMedicasWeb  (fuente única de verdad del dominio)

  - NO DESTRUCTIVO: no ejecuta DROP DATABASE ni DROP TABLE.
  - IDEMPOTENTE: IF NOT EXISTS / CREATE OR ALTER; seeds con INSERT ... WHERE NOT EXISTS (sin MERGE).
  - Se ejecuta SOBRE la base destino ya creada:

      sqlcmd -S localhost -E -Q "IF DB_ID('ReservasMedicasWeb') IS NULL CREATE DATABASE ReservasMedicasWeb"
      sqlcmd -S localhost -E -d ReservasMedicasWeb -b -f 65001 -i database\sql\ReservasMedicasWeb_MASTER.sql

  Schemas:
      seg   -> seguridad, RBAC, configuración
      clin  -> dominio clínico (pacientes, médicos, agenda, reservas)
      audit -> bitácora de negocio
      api   -> ÚNICA superficie que consume Laravel (SP + Views)

  Fechas:
      * Timestamps técnicos: SYSUTCDATETIME() (columnas *Utc).
      * Fecha/Hora de agenda y citas: hora civil de la clínica (America/Lima,
        UTC-5 sin horario de verano). "Ahora" local = clin.fn_AhoraLocal().
===============================================================================
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_NULLS ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;
GO

IF DB_NAME() IN (N'master', N'model', N'msdb', N'tempdb', N'ReservasMedicasDB')
BEGIN
    RAISERROR(N'MASTER debe ejecutarse sobre la base destino (p.ej. ReservasMedicasWeb).', 20, 1) WITH LOG;
END
GO

/* ============================================================================
   1. SCHEMAS
   ============================================================================ */
IF SCHEMA_ID(N'seg')   IS NULL EXEC (N'CREATE SCHEMA seg AUTHORIZATION dbo;');
IF SCHEMA_ID(N'clin')  IS NULL EXEC (N'CREATE SCHEMA clin AUTHORIZATION dbo;');
IF SCHEMA_ID(N'audit') IS NULL EXEC (N'CREATE SCHEMA audit AUTHORIZATION dbo;');
IF SCHEMA_ID(N'api')   IS NULL EXEC (N'CREATE SCHEMA api AUTHORIZATION dbo;');
GO

/* ============================================================================
   2. SEGURIDAD
   ============================================================================ */
IF OBJECT_ID(N'seg.Roles', N'U') IS NULL
CREATE TABLE seg.Roles
(
    RolId        TINYINT        NOT NULL CONSTRAINT PK_Roles PRIMARY KEY,
    Codigo       VARCHAR(30)    NOT NULL CONSTRAINT UQ_Roles_Codigo UNIQUE,
    Nombre       NVARCHAR(60)   NOT NULL,
    Descripcion  NVARCHAR(200)  NULL,
    Nivel        TINYINT        NOT NULL CONSTRAINT DF_Roles_Nivel DEFAULT (0),
    Activo       BIT            NOT NULL CONSTRAINT DF_Roles_Activo DEFAULT (1)
);
GO

IF OBJECT_ID(N'seg.Permisos', N'U') IS NULL
CREATE TABLE seg.Permisos
(
    PermisoId    SMALLINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Permisos PRIMARY KEY,
    Codigo       VARCHAR(60)    NOT NULL CONSTRAINT UQ_Permisos_Codigo UNIQUE,
    Modulo       VARCHAR(30)    NOT NULL,
    Descripcion  NVARCHAR(200)  NOT NULL,
    CONSTRAINT CK_Permisos_Codigo CHECK (Codigo LIKE '%_._%')
);
GO

IF OBJECT_ID(N'seg.RolPermiso', N'U') IS NULL
CREATE TABLE seg.RolPermiso
(
    RolId      TINYINT  NOT NULL CONSTRAINT FK_RolPermiso_Rol REFERENCES seg.Roles(RolId),
    PermisoId  SMALLINT NOT NULL CONSTRAINT FK_RolPermiso_Permiso REFERENCES seg.Permisos(PermisoId),
    CONSTRAINT PK_RolPermiso PRIMARY KEY (RolId, PermisoId)
);
GO

IF OBJECT_ID(N'seg.Usuarios', N'U') IS NULL
CREATE TABLE seg.Usuarios
(
    UsuarioId              INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Usuarios PRIMARY KEY,
    NombreUsuario          VARCHAR(50)    NOT NULL,
    Email                  VARCHAR(150)   NULL,
    Nombres                NVARCHAR(80)   NOT NULL,
    Apellidos              NVARCHAR(100)  NOT NULL,
    PasswordHash           NVARCHAR(512)  NOT NULL,
    PasswordAlgoritmo      VARCHAR(20)    NOT NULL,
    PasswordActualizadoUtc DATETIME2(0)   NOT NULL CONSTRAINT DF_Usuarios_PwdUtc DEFAULT (SYSUTCDATETIME()),
    IntentosFallidos       TINYINT        NOT NULL CONSTRAINT DF_Usuarios_Intentos DEFAULT (0),
    BloqueadoHastaUtc      DATETIME2(0)   NULL,
    UltimoAccesoUtc        DATETIME2(0)   NULL,
    Activo                 BIT            NOT NULL CONSTRAINT DF_Usuarios_Activo DEFAULT (1),
    FechaCreacionUtc       DATETIME2(0)   NOT NULL CONSTRAINT DF_Usuarios_Creacion DEFAULT (SYSUTCDATETIME()),
    FechaModificacionUtc   DATETIME2(0)   NULL,
    VersionFila            ROWVERSION     NOT NULL,
    CONSTRAINT UQ_Usuarios_NombreUsuario UNIQUE (NombreUsuario),
    CONSTRAINT CK_Usuarios_NombreUsuario CHECK (LEN(NombreUsuario) >= 3 AND NombreUsuario NOT LIKE '% %'),
    CONSTRAINT CK_Usuarios_Email CHECK (Email IS NULL OR Email LIKE '%_@_%._%'),
    CONSTRAINT CK_Usuarios_Algoritmo CHECK (PasswordAlgoritmo IN ('BCRYPT', 'ARGON2ID', 'PBKDF2_SHA256')),
    CONSTRAINT CK_Usuarios_Hash CHECK (LEN(PasswordHash) >= 20)
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_Usuarios_Email' AND object_id = OBJECT_ID(N'seg.Usuarios'))
    CREATE UNIQUE INDEX UX_Usuarios_Email ON seg.Usuarios(Email) WHERE Email IS NOT NULL;
GO

IF OBJECT_ID(N'seg.UsuarioRol', N'U') IS NULL
CREATE TABLE seg.UsuarioRol
(
    UsuarioId             INT          NOT NULL CONSTRAINT FK_UsuarioRol_Usuario REFERENCES seg.Usuarios(UsuarioId),
    RolId                 TINYINT      NOT NULL CONSTRAINT FK_UsuarioRol_Rol REFERENCES seg.Roles(RolId),
    AsignadoPorUsuarioId  INT          NULL     CONSTRAINT FK_UsuarioRol_AsignadoPor REFERENCES seg.Usuarios(UsuarioId),
    FechaAsignacionUtc    DATETIME2(0) NOT NULL CONSTRAINT DF_UsuarioRol_Fecha DEFAULT (SYSUTCDATETIME()),
    CONSTRAINT PK_UsuarioRol PRIMARY KEY (UsuarioId, RolId)
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_UsuarioRol_Rol' AND object_id = OBJECT_ID(N'seg.UsuarioRol'))
    CREATE INDEX IX_UsuarioRol_Rol ON seg.UsuarioRol(RolId) INCLUDE (UsuarioId);
GO

IF OBJECT_ID(N'seg.ConfiguracionSistema', N'U') IS NULL
CREATE TABLE seg.ConfiguracionSistema
(
    Clave                  VARCHAR(60)    NOT NULL CONSTRAINT PK_ConfiguracionSistema PRIMARY KEY,
    Valor                  NVARCHAR(200)  NOT NULL,
    TipoDato               VARCHAR(10)    NOT NULL,
    ValorMinimo            INT            NULL,
    ValorMaximo            INT            NULL,
    Descripcion            NVARCHAR(250)  NOT NULL,
    FechaModificacionUtc   DATETIME2(0)   NOT NULL CONSTRAINT DF_Config_Fecha DEFAULT (SYSUTCDATETIME()),
    ModificadoPorUsuarioId INT            NULL CONSTRAINT FK_Config_Usuario REFERENCES seg.Usuarios(UsuarioId),
    CONSTRAINT CK_Config_Tipo CHECK (TipoDato IN ('INT', 'BIT', 'TEXT'))
);
GO

/* ============================================================================
   3. CLÍNICA: catálogos, pacientes, médicos
   ============================================================================ */
IF OBJECT_ID(N'clin.Especialidades', N'U') IS NULL
CREATE TABLE clin.Especialidades
(
    EspecialidadId INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Especialidades PRIMARY KEY,
    Nombre         NVARCHAR(100) NOT NULL CONSTRAINT UQ_Especialidades_Nombre UNIQUE,
    Descripcion    NVARCHAR(250) NULL,
    Activo         BIT NOT NULL CONSTRAINT DF_Especialidades_Activo DEFAULT (1),
    CONSTRAINT CK_Especialidades_Nombre CHECK (LEN(LTRIM(Nombre)) > 0)
);
GO

IF OBJECT_ID(N'clin.Sedes', N'U') IS NULL
CREATE TABLE clin.Sedes
(
    SedeId     INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Sedes PRIMARY KEY,
    Codigo     VARCHAR(20)   NOT NULL CONSTRAINT UQ_Sedes_Codigo UNIQUE,
    Nombre     NVARCHAR(100) NOT NULL CONSTRAINT UQ_Sedes_Nombre UNIQUE,
    Direccion  NVARCHAR(200) NULL,
    Activo     BIT NOT NULL CONSTRAINT DF_Sedes_Activo DEFAULT (1)
);
GO

IF OBJECT_ID(N'clin.Consultorios', N'U') IS NULL
CREATE TABLE clin.Consultorios
(
    ConsultorioId INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Consultorios PRIMARY KEY,
    SedeId        INT           NOT NULL CONSTRAINT FK_Consultorios_Sede REFERENCES clin.Sedes(SedeId),
    Codigo        VARCHAR(20)   NOT NULL,
    Nombre        NVARCHAR(100) NOT NULL,
    Piso          TINYINT       NULL,
    Activo        BIT NOT NULL CONSTRAINT DF_Consultorios_Activo DEFAULT (1),
    CONSTRAINT UQ_Consultorios_SedeCodigo UNIQUE (SedeId, Codigo),
    -- Clave alterna para FKs compuestas: garantiza que el consultorio pertenece a la sede.
    CONSTRAINT UQ_Consultorios_IdSede UNIQUE (ConsultorioId, SedeId)
);
GO

IF OBJECT_ID(N'clin.TiposAtencion', N'U') IS NULL
CREATE TABLE clin.TiposAtencion
(
    TipoAtencionId TINYINT      NOT NULL CONSTRAINT PK_TiposAtencion PRIMARY KEY,
    Codigo         VARCHAR(20)  NOT NULL CONSTRAINT UQ_TiposAtencion_Codigo UNIQUE,
    Nombre         NVARCHAR(60) NOT NULL
);
GO

IF OBJECT_ID(N'clin.Pacientes', N'U') IS NULL
CREATE TABLE clin.Pacientes
(
    PacienteId            INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Pacientes PRIMARY KEY,
    UsuarioId             INT           NULL CONSTRAINT FK_Pacientes_Usuario REFERENCES seg.Usuarios(UsuarioId),
    TipoDocumento         VARCHAR(10)   NOT NULL,
    NumeroDocumento       VARCHAR(20)   NOT NULL,
    Nombres               NVARCHAR(80)  NOT NULL,
    Apellidos             NVARCHAR(100) NOT NULL,
    FechaNacimiento       DATE          NOT NULL,
    Sexo                  CHAR(1)       NULL,
    Telefono              VARCHAR(20)   NULL,
    Email                 VARCHAR(150)  NULL,
    Direccion             NVARCHAR(200) NULL,
    ContactoEmergencia    NVARCHAR(150) NULL,
    Activo                BIT           NOT NULL CONSTRAINT DF_Pacientes_Activo DEFAULT (1),
    CreadoPorUsuarioId    INT           NULL CONSTRAINT FK_Pacientes_CreadoPor REFERENCES seg.Usuarios(UsuarioId),
    FechaCreacionUtc      DATETIME2(0)  NOT NULL CONSTRAINT DF_Pacientes_Creacion DEFAULT (SYSUTCDATETIME()),
    FechaModificacionUtc  DATETIME2(0)  NULL,
    VersionFila           ROWVERSION    NOT NULL,
    CONSTRAINT UQ_Pacientes_Documento UNIQUE (TipoDocumento, NumeroDocumento),
    CONSTRAINT CK_Pacientes_TipoDocumento CHECK (TipoDocumento IN ('DNI', 'CE', 'PASAPORTE')),
    CONSTRAINT CK_Pacientes_NumeroDocumento CHECK (LEN(NumeroDocumento) >= 6 AND NumeroDocumento NOT LIKE '%[^0-9A-Z]%'),
    CONSTRAINT CK_Pacientes_FechaNacimiento CHECK (FechaNacimiento >= '1900-01-01' AND FechaNacimiento <= CAST(SYSUTCDATETIME() AS DATE)),
    CONSTRAINT CK_Pacientes_Sexo CHECK (Sexo IS NULL OR Sexo IN ('F', 'M', 'X')),
    CONSTRAINT CK_Pacientes_Email CHECK (Email IS NULL OR Email LIKE '%_@_%._%')
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_Pacientes_Usuario' AND object_id = OBJECT_ID(N'clin.Pacientes'))
    CREATE UNIQUE INDEX UX_Pacientes_Usuario ON clin.Pacientes(UsuarioId) WHERE UsuarioId IS NOT NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Pacientes_Apellidos' AND object_id = OBJECT_ID(N'clin.Pacientes'))
    CREATE INDEX IX_Pacientes_Apellidos ON clin.Pacientes(Apellidos, Nombres) INCLUDE (NumeroDocumento, Activo);
GO

IF OBJECT_ID(N'clin.Medicos', N'U') IS NULL
CREATE TABLE clin.Medicos
(
    MedicoId              INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Medicos PRIMARY KEY,
    UsuarioId             INT           NULL CONSTRAINT FK_Medicos_Usuario REFERENCES seg.Usuarios(UsuarioId),
    CMP                   VARCHAR(20)   NOT NULL CONSTRAINT UQ_Medicos_CMP UNIQUE,
    Nombres               NVARCHAR(80)  NOT NULL,
    Apellidos             NVARCHAR(100) NOT NULL,
    Telefono              VARCHAR(20)   NULL,
    Email                 VARCHAR(150)  NULL,
    Activo                BIT           NOT NULL CONSTRAINT DF_Medicos_Activo DEFAULT (1),
    FechaCreacionUtc      DATETIME2(0)  NOT NULL CONSTRAINT DF_Medicos_Creacion DEFAULT (SYSUTCDATETIME()),
    FechaModificacionUtc  DATETIME2(0)  NULL,
    VersionFila           ROWVERSION    NOT NULL,
    CONSTRAINT CK_Medicos_CMP CHECK (LEN(LTRIM(CMP)) > 0 AND CMP NOT LIKE '%[^0-9A-Z-]%'),
    CONSTRAINT CK_Medicos_Email CHECK (Email IS NULL OR Email LIKE '%_@_%._%')
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_Medicos_Usuario' AND object_id = OBJECT_ID(N'clin.Medicos'))
    CREATE UNIQUE INDEX UX_Medicos_Usuario ON clin.Medicos(UsuarioId) WHERE UsuarioId IS NOT NULL;
GO

IF OBJECT_ID(N'clin.MedicoEspecialidad', N'U') IS NULL
CREATE TABLE clin.MedicoEspecialidad
(
    MedicoId       INT NOT NULL CONSTRAINT FK_MedicoEspecialidad_Medico REFERENCES clin.Medicos(MedicoId),
    EspecialidadId INT NOT NULL CONSTRAINT FK_MedicoEspecialidad_Especialidad REFERENCES clin.Especialidades(EspecialidadId),
    EsPrincipal    BIT NOT NULL CONSTRAINT DF_MedicoEspecialidad_Principal DEFAULT (0),
    Activo         BIT NOT NULL CONSTRAINT DF_MedicoEspecialidad_Activo DEFAULT (1),
    CONSTRAINT PK_MedicoEspecialidad PRIMARY KEY (MedicoId, EspecialidadId)
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_MedicoEspecialidad_Principal' AND object_id = OBJECT_ID(N'clin.MedicoEspecialidad'))
    CREATE UNIQUE INDEX UX_MedicoEspecialidad_Principal ON clin.MedicoEspecialidad(MedicoId) WHERE EsPrincipal = 1 AND Activo = 1;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_MedicoEspecialidad_Especialidad' AND object_id = OBJECT_ID(N'clin.MedicoEspecialidad'))
    CREATE INDEX IX_MedicoEspecialidad_Especialidad ON clin.MedicoEspecialidad(EspecialidadId) INCLUDE (EsPrincipal);
GO

IF OBJECT_ID(N'clin.MedicoSede', N'U') IS NULL
CREATE TABLE clin.MedicoSede
(
    MedicoId INT NOT NULL CONSTRAINT FK_MedicoSede_Medico REFERENCES clin.Medicos(MedicoId),
    SedeId   INT NOT NULL CONSTRAINT FK_MedicoSede_Sede REFERENCES clin.Sedes(SedeId),
    Activo   BIT NOT NULL CONSTRAINT DF_MedicoSede_Activo DEFAULT (1),
    CONSTRAINT PK_MedicoSede PRIMARY KEY (MedicoId, SedeId)
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_MedicoSede_Sede' AND object_id = OBJECT_ID(N'clin.MedicoSede'))
    CREATE INDEX IX_MedicoSede_Sede ON clin.MedicoSede(SedeId);
GO

/* ============================================================================
   4. AGENDA
   ============================================================================ */
IF OBJECT_ID(N'clin.PlantillasAgenda', N'U') IS NULL
CREATE TABLE clin.PlantillasAgenda
(
    PlantillaAgendaId   INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_PlantillasAgenda PRIMARY KEY,
    MedicoId            INT          NOT NULL CONSTRAINT FK_Plantillas_Medico REFERENCES clin.Medicos(MedicoId),
    EspecialidadId      INT          NOT NULL CONSTRAINT FK_Plantillas_Especialidad REFERENCES clin.Especialidades(EspecialidadId),
    SedeId              INT          NOT NULL,
    ConsultorioId       INT          NOT NULL,
    TipoAtencionId      TINYINT      NOT NULL CONSTRAINT FK_Plantillas_Tipo REFERENCES clin.TiposAtencion(TipoAtencionId),
    DiasSemana          VARCHAR(20)  NOT NULL,   -- ISO: 1=lunes ... 7=domingo, separados por coma
    HoraInicio          TIME(0)      NOT NULL,
    HoraFin             TIME(0)      NOT NULL,
    DuracionMinutos     SMALLINT     NOT NULL,
    FechaDesde          DATE         NOT NULL,
    FechaHasta          DATE         NOT NULL,
    AgendasGeneradas    INT          NOT NULL CONSTRAINT DF_Plantillas_Generadas DEFAULT (0),
    AgendasOmitidas     INT          NOT NULL CONSTRAINT DF_Plantillas_Omitidas DEFAULT (0),
    CreadoPorUsuarioId  INT          NOT NULL CONSTRAINT FK_Plantillas_Usuario REFERENCES seg.Usuarios(UsuarioId),
    FechaCreacionUtc    DATETIME2(0) NOT NULL CONSTRAINT DF_Plantillas_Creacion DEFAULT (SYSUTCDATETIME()),
    CONSTRAINT FK_Plantillas_Consultorio FOREIGN KEY (ConsultorioId, SedeId) REFERENCES clin.Consultorios(ConsultorioId, SedeId),
    CONSTRAINT CK_Plantillas_Horas CHECK (HoraFin > HoraInicio),
    CONSTRAINT CK_Plantillas_Duracion CHECK (DuracionMinutos BETWEEN 10 AND 120),
    CONSTRAINT CK_Plantillas_Rango CHECK (FechaHasta >= FechaDesde AND DATEDIFF(DAY, FechaDesde, FechaHasta) <= 92),
    CONSTRAINT CK_Plantillas_Dias CHECK (LEN(DiasSemana) > 0 AND DiasSemana NOT LIKE '%[^1-7,]%')
);
GO

IF OBJECT_ID(N'clin.AgendasMedicas', N'U') IS NULL
CREATE TABLE clin.AgendasMedicas
(
    AgendaId              BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_AgendasMedicas PRIMARY KEY,
    MedicoId              INT          NOT NULL CONSTRAINT FK_Agendas_Medico REFERENCES clin.Medicos(MedicoId),
    EspecialidadId        INT          NOT NULL,
    SedeId                INT          NOT NULL,
    ConsultorioId         INT          NOT NULL,
    TipoAtencionId        TINYINT      NOT NULL CONSTRAINT FK_Agendas_Tipo REFERENCES clin.TiposAtencion(TipoAtencionId),
    PlantillaAgendaId     INT          NULL CONSTRAINT FK_Agendas_Plantilla REFERENCES clin.PlantillasAgenda(PlantillaAgendaId),
    Fecha                 DATE         NOT NULL,
    HoraInicio            TIME(0)      NOT NULL,
    HoraFin               TIME(0)      NOT NULL,
    DuracionMinutos       SMALLINT     NOT NULL,
    Activo                BIT          NOT NULL CONSTRAINT DF_Agendas_Activo DEFAULT (1),
    CreadoPorUsuarioId    INT          NULL CONSTRAINT FK_Agendas_CreadoPor REFERENCES seg.Usuarios(UsuarioId),
    FechaCreacionUtc      DATETIME2(0) NOT NULL CONSTRAINT DF_Agendas_Creacion DEFAULT (SYSUTCDATETIME()),
    FechaModificacionUtc  DATETIME2(0) NULL,
    VersionFila           ROWVERSION   NOT NULL,
    -- El médico debe tener la especialidad y atender en la sede; el consultorio debe pertenecer a la sede.
    CONSTRAINT FK_Agendas_MedicoEspecialidad FOREIGN KEY (MedicoId, EspecialidadId) REFERENCES clin.MedicoEspecialidad(MedicoId, EspecialidadId),
    CONSTRAINT FK_Agendas_MedicoSede FOREIGN KEY (MedicoId, SedeId) REFERENCES clin.MedicoSede(MedicoId, SedeId),
    CONSTRAINT FK_Agendas_Consultorio FOREIGN KEY (ConsultorioId, SedeId) REFERENCES clin.Consultorios(ConsultorioId, SedeId),
    CONSTRAINT CK_Agendas_Horas CHECK (HoraFin > HoraInicio),
    CONSTRAINT CK_Agendas_Duracion CHECK (DuracionMinutos BETWEEN 10 AND 120),
    CONSTRAINT CK_Agendas_DuracionCabe CHECK (DATEDIFF(MINUTE, HoraInicio, HoraFin) >= DuracionMinutos)
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Agendas_MedicoFecha' AND object_id = OBJECT_ID(N'clin.AgendasMedicas'))
    CREATE INDEX IX_Agendas_MedicoFecha ON clin.AgendasMedicas(MedicoId, Fecha) INCLUDE (HoraInicio, HoraFin, Activo, SedeId, EspecialidadId);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Agendas_ConsultorioFecha' AND object_id = OBJECT_ID(N'clin.AgendasMedicas'))
    CREATE INDEX IX_Agendas_ConsultorioFecha ON clin.AgendasMedicas(ConsultorioId, Fecha) INCLUDE (HoraInicio, HoraFin, Activo);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Agendas_FechaEspecialidad' AND object_id = OBJECT_ID(N'clin.AgendasMedicas'))
    CREATE INDEX IX_Agendas_FechaEspecialidad ON clin.AgendasMedicas(Fecha, EspecialidadId) INCLUDE (MedicoId, SedeId, Activo);
GO

IF OBJECT_ID(N'clin.BloqueosMedico', N'U') IS NULL
CREATE TABLE clin.BloqueosMedico
(
    BloqueoId              BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_BloqueosMedico PRIMARY KEY,
    MedicoId               INT           NOT NULL CONSTRAINT FK_Bloqueos_Medico REFERENCES clin.Medicos(MedicoId),
    Fecha                  DATE          NOT NULL,
    HoraInicio             TIME(0)       NOT NULL,
    HoraFin                TIME(0)       NOT NULL,
    TipoBloqueo            VARCHAR(20)   NOT NULL,
    Motivo                 NVARCHAR(200) NOT NULL,
    Activo                 BIT           NOT NULL CONSTRAINT DF_Bloqueos_Activo DEFAULT (1),
    CreadoPorUsuarioId     INT           NOT NULL CONSTRAINT FK_Bloqueos_CreadoPor REFERENCES seg.Usuarios(UsuarioId),
    FechaCreacionUtc       DATETIME2(0)  NOT NULL CONSTRAINT DF_Bloqueos_Creacion DEFAULT (SYSUTCDATETIME()),
    LevantadoPorUsuarioId  INT           NULL CONSTRAINT FK_Bloqueos_LevantadoPor REFERENCES seg.Usuarios(UsuarioId),
    FechaLevantamientoUtc  DATETIME2(0)  NULL,
    CONSTRAINT CK_Bloqueos_Horas CHECK (HoraFin > HoraInicio),
    CONSTRAINT CK_Bloqueos_Tipo CHECK (TipoBloqueo IN ('SLOT', 'AUSENCIA', 'REUNION', 'CAPACITACION', 'LICENCIA', 'MANTENIMIENTO')),
    CONSTRAINT CK_Bloqueos_Motivo CHECK (LEN(LTRIM(Motivo)) >= 3),
    CONSTRAINT CK_Bloqueos_Levantado CHECK ((Activo = 1 AND FechaLevantamientoUtc IS NULL) OR (Activo = 0 AND FechaLevantamientoUtc IS NOT NULL))
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Bloqueos_MedicoFecha' AND object_id = OBJECT_ID(N'clin.BloqueosMedico'))
    CREATE INDEX IX_Bloqueos_MedicoFecha ON clin.BloqueosMedico(MedicoId, Fecha) INCLUDE (Activo, TipoBloqueo, HoraInicio, HoraFin);
GO

IF OBJECT_ID(N'clin.HorariosMedicos', N'U') IS NULL
CREATE TABLE clin.HorariosMedicos
(
    HorarioMedicoId  BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_HorariosMedicos PRIMARY KEY,
    AgendaId         BIGINT        NOT NULL CONSTRAINT FK_Horarios_Agenda REFERENCES clin.AgendasMedicas(AgendaId),
    HoraInicio       TIME(0)       NOT NULL,
    HoraFin          TIME(0)       NOT NULL,
    Bloqueado        BIT           NOT NULL CONSTRAINT DF_Horarios_Bloqueado DEFAULT (0),
    BloqueoId        BIGINT        NULL CONSTRAINT FK_Horarios_Bloqueo REFERENCES clin.BloqueosMedico(BloqueoId),
    Activo           BIT           NOT NULL CONSTRAINT DF_Horarios_Activo DEFAULT (1),
    VersionFila      ROWVERSION    NOT NULL,
    CONSTRAINT UQ_Horarios_AgendaHora UNIQUE (AgendaId, HoraInicio),
    CONSTRAINT CK_Horarios_Horas CHECK (HoraFin > HoraInicio),
    CONSTRAINT CK_Horarios_Bloqueo CHECK (Bloqueado = 1 OR BloqueoId IS NULL)
);
GO

/* ============================================================================
   5. RESERVAS
   ============================================================================ */
IF OBJECT_ID(N'clin.EstadosReserva', N'U') IS NULL
CREATE TABLE clin.EstadosReserva
(
    EstadoReservaId TINYINT       NOT NULL CONSTRAINT PK_EstadosReserva PRIMARY KEY,
    Codigo          VARCHAR(20)   NOT NULL CONSTRAINT UQ_EstadosReserva_Codigo UNIQUE,
    Nombre          NVARCHAR(40)  NOT NULL,
    OcupaHorario    BIT           NOT NULL,
    EsFinal         BIT           NOT NULL
);
GO

IF OBJECT_ID(N'clin.TransicionesEstadoReserva', N'U') IS NULL
CREATE TABLE clin.TransicionesEstadoReserva
(
    EstadoOrigenId  TINYINT NOT NULL CONSTRAINT FK_Transiciones_Origen REFERENCES clin.EstadosReserva(EstadoReservaId),
    EstadoDestinoId TINYINT NOT NULL CONSTRAINT FK_Transiciones_Destino REFERENCES clin.EstadosReserva(EstadoReservaId),
    CONSTRAINT PK_TransicionesEstadoReserva PRIMARY KEY (EstadoOrigenId, EstadoDestinoId),
    CONSTRAINT CK_Transiciones_Distintas CHECK (EstadoOrigenId <> EstadoDestinoId)
);
GO

IF OBJECT_ID(N'clin.MotivosCancelacion', N'U') IS NULL
CREATE TABLE clin.MotivosCancelacion
(
    MotivoCancelacionId TINYINT      NOT NULL CONSTRAINT PK_MotivosCancelacion PRIMARY KEY,
    Codigo              VARCHAR(30)  NOT NULL CONSTRAINT UQ_MotivosCancelacion_Codigo UNIQUE,
    Nombre              NVARCHAR(80) NOT NULL,
    SoloPersonal        BIT          NOT NULL CONSTRAINT DF_Motivos_SoloPersonal DEFAULT (0),
    Activo              BIT          NOT NULL CONSTRAINT DF_Motivos_Activo DEFAULT (1)
);
GO

IF OBJECT_ID(N'clin.Reservas', N'U') IS NULL
CREATE TABLE clin.Reservas
(
    ReservaId               BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_Reservas PRIMARY KEY,
    CodigoReserva           AS (CONVERT(VARCHAR(12), 'NX-' + RIGHT('00000000' + CONVERT(VARCHAR(10), ReservaId), 8))) PERSISTED,
    HorarioMedicoId         BIGINT        NOT NULL CONSTRAINT FK_Reservas_Horario REFERENCES clin.HorariosMedicos(HorarioMedicoId),
    PacienteId              INT           NOT NULL CONSTRAINT FK_Reservas_Paciente REFERENCES clin.Pacientes(PacienteId),
    -- Instantánea inmutable del slot (validaciones de solapamiento y reportes sin joins).
    MedicoId                INT           NOT NULL CONSTRAINT FK_Reservas_Medico REFERENCES clin.Medicos(MedicoId),
    EspecialidadId          INT           NOT NULL CONSTRAINT FK_Reservas_Especialidad REFERENCES clin.Especialidades(EspecialidadId),
    SedeId                  INT           NOT NULL CONSTRAINT FK_Reservas_Sede REFERENCES clin.Sedes(SedeId),
    ConsultorioId           INT           NOT NULL CONSTRAINT FK_Reservas_Consultorio REFERENCES clin.Consultorios(ConsultorioId),
    TipoAtencionId          TINYINT       NOT NULL CONSTRAINT FK_Reservas_Tipo REFERENCES clin.TiposAtencion(TipoAtencionId),
    FechaCita               DATE          NOT NULL,
    HoraInicio              TIME(0)       NOT NULL,
    HoraFin                 TIME(0)       NOT NULL,
    EstadoReservaId         TINYINT       NOT NULL CONSTRAINT FK_Reservas_Estado REFERENCES clin.EstadosReserva(EstadoReservaId),
    OcupaHorario            BIT           NOT NULL,
    Observacion             NVARCHAR(250) NULL,
    IdempotencyKey          UNIQUEIDENTIFIER NOT NULL,
    ReservaOrigenId         BIGINT        NULL CONSTRAINT FK_Reservas_Origen REFERENCES clin.Reservas(ReservaId),
    ReservaReemplazoId      BIGINT        NULL CONSTRAINT FK_Reservas_Reemplazo REFERENCES clin.Reservas(ReservaId),
    CreadoPorUsuarioId      INT           NOT NULL CONSTRAINT FK_Reservas_CreadoPor REFERENCES seg.Usuarios(UsuarioId),
    FechaCreacionUtc        DATETIME2(0)  NOT NULL CONSTRAINT DF_Reservas_Creacion DEFAULT (SYSUTCDATETIME()),
    MotivoCancelacionId     TINYINT       NULL CONSTRAINT FK_Reservas_MotivoCancelacion REFERENCES clin.MotivosCancelacion(MotivoCancelacionId),
    ObservacionCancelacion  NVARCHAR(250) NULL,
    CanceladoPorUsuarioId   INT           NULL CONSTRAINT FK_Reservas_CanceladoPor REFERENCES seg.Usuarios(UsuarioId),
    FechaCancelacionUtc     DATETIME2(0)  NULL,
    CerradoPorUsuarioId     INT           NULL CONSTRAINT FK_Reservas_CerradoPor REFERENCES seg.Usuarios(UsuarioId),
    FechaCierreUtc          DATETIME2(0)  NULL,
    FechaModificacionUtc    DATETIME2(0)  NULL,
    VersionFila             ROWVERSION    NOT NULL,
    -- 1=CONFIRMADA, 4=ATENDIDA, 5=NO_ASISTIO ocupan el slot; 2=CANCELADA y 3=REPROGRAMADA lo liberan.
    CONSTRAINT CK_Reservas_OcupaHorario CHECK (OcupaHorario = CASE WHEN EstadoReservaId IN (1, 4, 5) THEN 1 ELSE 0 END),
    CONSTRAINT CK_Reservas_Horas CHECK (HoraFin > HoraInicio),
    CONSTRAINT CK_Reservas_Cancelacion CHECK
    (
        (EstadoReservaId = 2 AND MotivoCancelacionId IS NOT NULL AND CanceladoPorUsuarioId IS NOT NULL AND FechaCancelacionUtc IS NOT NULL)
        OR (EstadoReservaId <> 2 AND MotivoCancelacionId IS NULL)
    ),
    CONSTRAINT CK_Reservas_Reprogramada CHECK (EstadoReservaId <> 3 OR ReservaReemplazoId IS NOT NULL),
    CONSTRAINT CK_Reservas_Cierre CHECK (EstadoReservaId NOT IN (4, 5) OR (CerradoPorUsuarioId IS NOT NULL AND FechaCierreUtc IS NOT NULL))
);
GO
-- Imposibilidad física de doble reserva: un slot solo admite UNA reserva que lo ocupe.
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_Reservas_HorarioOcupado' AND object_id = OBJECT_ID(N'clin.Reservas'))
    CREATE UNIQUE INDEX UX_Reservas_HorarioOcupado ON clin.Reservas(HorarioMedicoId) WHERE OcupaHorario = 1;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_Reservas_IdempotencyKey' AND object_id = OBJECT_ID(N'clin.Reservas'))
    CREATE UNIQUE INDEX UX_Reservas_IdempotencyKey ON clin.Reservas(IdempotencyKey);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'UX_Reservas_Codigo' AND object_id = OBJECT_ID(N'clin.Reservas'))
    CREATE UNIQUE INDEX UX_Reservas_Codigo ON clin.Reservas(CodigoReserva);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Reservas_PacienteFecha' AND object_id = OBJECT_ID(N'clin.Reservas'))
    CREATE INDEX IX_Reservas_PacienteFecha ON clin.Reservas(PacienteId, FechaCita) INCLUDE (EstadoReservaId, HoraInicio, HoraFin, OcupaHorario, EspecialidadId);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Reservas_FechaEstado' AND object_id = OBJECT_ID(N'clin.Reservas'))
    CREATE INDEX IX_Reservas_FechaEstado ON clin.Reservas(FechaCita, EstadoReservaId) INCLUDE (MedicoId, EspecialidadId, SedeId, PacienteId);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Reservas_MedicoFecha' AND object_id = OBJECT_ID(N'clin.Reservas'))
    CREATE INDEX IX_Reservas_MedicoFecha ON clin.Reservas(MedicoId, FechaCita) INCLUDE (EstadoReservaId, HoraInicio, HoraFin, OcupaHorario);
GO

IF OBJECT_ID(N'clin.ReservaHistorial', N'U') IS NULL
CREATE TABLE clin.ReservaHistorial
(
    ReservaHistorialId BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_ReservaHistorial PRIMARY KEY,
    ReservaId          BIGINT        NOT NULL CONSTRAINT FK_Historial_Reserva REFERENCES clin.Reservas(ReservaId),
    EstadoAnteriorId   TINYINT       NULL CONSTRAINT FK_Historial_EstadoAnterior REFERENCES clin.EstadosReserva(EstadoReservaId),
    EstadoNuevoId      TINYINT       NOT NULL CONSTRAINT FK_Historial_EstadoNuevo REFERENCES clin.EstadosReserva(EstadoReservaId),
    UsuarioActorId     INT           NOT NULL CONSTRAINT FK_Historial_Actor REFERENCES seg.Usuarios(UsuarioId),
    Motivo             NVARCHAR(250) NULL,
    CorrelationId      VARCHAR(64)   NULL,
    FechaUtc           DATETIME2(3)  NOT NULL CONSTRAINT DF_Historial_Fecha DEFAULT (SYSUTCDATETIME())
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Historial_Reserva' AND object_id = OBJECT_ID(N'clin.ReservaHistorial'))
    CREATE INDEX IX_Historial_Reserva ON clin.ReservaHistorial(ReservaId, FechaUtc);
GO

/* ============================================================================
   6. AUDITORÍA
   ============================================================================ */
IF OBJECT_ID(N'audit.BitacoraSistema', N'U') IS NULL
CREATE TABLE audit.BitacoraSistema
(
    BitacoraId     BIGINT IDENTITY(1,1) NOT NULL CONSTRAINT PK_BitacoraSistema PRIMARY KEY,
    UsuarioId      INT            NULL CONSTRAINT FK_Bitacora_Usuario REFERENCES seg.Usuarios(UsuarioId),
    Accion         VARCHAR(50)    NOT NULL,
    Entidad        VARCHAR(50)    NULL,
    EntidadId      BIGINT         NULL,
    Exitoso        BIT            NOT NULL,
    Detalle        NVARCHAR(1000) NULL,
    Ip             VARCHAR(45)    NULL,
    UserAgent      NVARCHAR(300)  NULL,
    CorrelationId  VARCHAR(64)    NULL,
    FechaUtc       DATETIME2(3)   NOT NULL CONSTRAINT DF_Bitacora_Fecha DEFAULT (SYSUTCDATETIME())
);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Bitacora_Fecha' AND object_id = OBJECT_ID(N'audit.BitacoraSistema'))
    CREATE INDEX IX_Bitacora_Fecha ON audit.BitacoraSistema(FechaUtc DESC) INCLUDE (Accion, UsuarioId, Exitoso);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Bitacora_UsuarioFecha' AND object_id = OBJECT_ID(N'audit.BitacoraSistema'))
    CREATE INDEX IX_Bitacora_UsuarioFecha ON audit.BitacoraSistema(UsuarioId, FechaUtc DESC);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'IX_Bitacora_Correlation' AND object_id = OBJECT_ID(N'audit.BitacoraSistema'))
    CREATE INDEX IX_Bitacora_Correlation ON audit.BitacoraSistema(CorrelationId) WHERE CorrelationId IS NOT NULL;
GO

/* ============================================================================
   7. CATÁLOGOS BASE (seeds no duplicables: INSERT ... WHERE NOT EXISTS)
   ============================================================================ */
INSERT INTO seg.Roles (RolId, Codigo, Nombre, Descripcion, Nivel)
SELECT v.RolId, v.Codigo, v.Nombre, v.Descripcion, v.Nivel
FROM (VALUES
    (1, 'SUPERADMIN',    N'Superadministrador', N'Control total de la plataforma', 100),
    (2, 'ADMINISTRADOR', N'Administrador',      N'Gestión de médicos, agenda, usuarios y reservas', 80),
    (3, 'RECEPCIONISTA', N'Recepcionista',      N'Registro de pacientes y gestión de reservas', 50),
    (4, 'MEDICO',        N'Médico',             N'Consulta de agenda propia y cierre de citas', 40),
    (5, 'PACIENTE',      N'Paciente',           N'Reserva y gestión de citas propias', 10),
    (6, 'AUDITOR',       N'Auditor',            N'Consulta de bitácora y reportes (solo lectura)', 30)
) v (RolId, Codigo, Nombre, Descripcion, Nivel)
WHERE NOT EXISTS (SELECT 1 FROM seg.Roles r WHERE r.RolId = v.RolId);
GO

INSERT INTO seg.Permisos (Codigo, Modulo, Descripcion)
SELECT v.Codigo, v.Modulo, v.Descripcion
FROM (VALUES
    ('usuarios.ver',             'usuarios',      N'Ver usuarios del sistema'),
    ('usuarios.crear',           'usuarios',      N'Crear usuarios'),
    ('usuarios.editar',          'usuarios',      N'Editar y activar/desactivar usuarios'),
    ('usuarios.roles',           'usuarios',      N'Asignar y revocar roles'),
    ('pacientes.ver',            'pacientes',     N'Ver y buscar pacientes'),
    ('pacientes.crear',          'pacientes',     N'Registrar pacientes'),
    ('pacientes.editar',         'pacientes',     N'Editar datos de pacientes'),
    ('medicos.ver',              'medicos',       N'Ver directorio de médicos'),
    ('medicos.crear',            'medicos',       N'Registrar médicos'),
    ('medicos.editar',           'medicos',       N'Editar médicos, especialidades y sedes'),
    ('especialidades.gestionar', 'catalogos',     N'Gestionar especialidades'),
    ('sedes.gestionar',          'catalogos',     N'Gestionar sedes'),
    ('consultorios.gestionar',   'catalogos',     N'Gestionar consultorios'),
    ('agenda.ver',               'agenda',        N'Ver agendas'),
    ('agenda.gestionar',         'agenda',        N'Crear, generar y desactivar agendas'),
    ('agenda.bloquear',          'agenda',        N'Bloquear y desbloquear horarios'),
    ('reservas.propias',         'reservas',      N'Gestionar reservas propias (paciente)'),
    ('reservas.crear',           'reservas',      N'Crear reservas'),
    ('reservas.reprogramar',     'reservas',      N'Reprogramar reservas'),
    ('reservas.cancelar',        'reservas',      N'Cancelar reservas'),
    ('reservas.gestionar',       'reservas',      N'Gestionar reservas de cualquier paciente'),
    ('reservas.estado',          'reservas',      N'Marcar reservas como atendidas o no asistió'),
    ('auditoria.ver',            'auditoria',     N'Consultar bitácora'),
    ('reportes.ver',             'reportes',      N'Consultar reportes y dashboard'),
    ('configuracion.gestionar',  'configuracion', N'Modificar configuración del sistema')
) v (Codigo, Modulo, Descripcion)
WHERE NOT EXISTS (SELECT 1 FROM seg.Permisos p WHERE p.Codigo = v.Codigo);
GO

WITH Matriz (RolCodigo, PermisoCodigo) AS
(
    SELECT CAST('SUPERADMIN' AS VARCHAR(30)), Codigo FROM seg.Permisos
    UNION ALL
    SELECT 'ADMINISTRADOR', Codigo FROM seg.Permisos WHERE Codigo NOT IN ('reservas.propias', 'configuracion.gestionar')
    UNION ALL
    SELECT 'RECEPCIONISTA', v.c FROM (VALUES
        ('pacientes.ver'), ('pacientes.crear'), ('pacientes.editar'), ('medicos.ver'), ('agenda.ver'),
        ('reservas.crear'), ('reservas.reprogramar'), ('reservas.cancelar'), ('reservas.gestionar')) v (c)
    UNION ALL
    SELECT 'MEDICO', v.c FROM (VALUES ('medicos.ver'), ('agenda.ver'), ('reservas.estado')) v (c)
    UNION ALL
    SELECT 'PACIENTE', v.c FROM (VALUES
        ('medicos.ver'), ('reservas.propias'), ('reservas.crear'), ('reservas.reprogramar'), ('reservas.cancelar')) v (c)
    UNION ALL
    SELECT 'AUDITOR', v.c FROM (VALUES ('auditoria.ver'), ('reportes.ver')) v (c)
)
INSERT INTO seg.RolPermiso (RolId, PermisoId)
SELECT DISTINCT r.RolId, p.PermisoId
FROM Matriz m
JOIN seg.Roles r    ON r.Codigo = m.RolCodigo
JOIN seg.Permisos p ON p.Codigo = m.PermisoCodigo
WHERE NOT EXISTS (SELECT 1 FROM seg.RolPermiso rp WHERE rp.RolId = r.RolId AND rp.PermisoId = p.PermisoId);
GO

INSERT INTO seg.ConfiguracionSistema (Clave, Valor, TipoDato, ValorMinimo, ValorMaximo, Descripcion)
SELECT v.Clave, v.Valor, v.TipoDato, v.ValorMinimo, v.ValorMaximo, v.Descripcion
FROM (VALUES
    ('HORAS_MINIMAS_CANCELACION',     N'2',  'INT', 0, 72,   N'Horas mínimas de anticipación para que un paciente cancele o reprograme.'),
    ('MAX_DIAS_RESERVA_FUTURA',       N'60', 'INT', 1, 365,  N'Días máximos hacia el futuro en los que se permite reservar.'),
    ('MINUTOS_MINIMOS_ANTICIPACION',  N'30', 'INT', 0, 1440, N'Minutos mínimos entre la hora actual y el inicio de la cita para reservar.'),
    ('MAX_RESERVAS_ACTIVAS_PACIENTE', N'5',  'INT', 1, 50,   N'Reservas confirmadas futuras simultáneas permitidas por paciente.'),
    ('MAX_INTENTOS_LOGIN',            N'5',  'INT', 3, 20,   N'Intentos fallidos antes de bloquear temporalmente la cuenta.'),
    ('MINUTOS_BLOQUEO_LOGIN',         N'15', 'INT', 1, 1440, N'Minutos de bloqueo temporal tras superar los intentos fallidos.')
) v (Clave, Valor, TipoDato, ValorMinimo, ValorMaximo, Descripcion)
WHERE NOT EXISTS (SELECT 1 FROM seg.ConfiguracionSistema c WHERE c.Clave = v.Clave);
GO

INSERT INTO clin.TiposAtencion (TipoAtencionId, Codigo, Nombre)
SELECT v.Id, v.Codigo, v.Nombre
FROM (VALUES (1, 'PRESENCIAL', N'Presencial'), (2, 'VIRTUAL', N'Virtual')) v (Id, Codigo, Nombre)
WHERE NOT EXISTS (SELECT 1 FROM clin.TiposAtencion t WHERE t.TipoAtencionId = v.Id);
GO

INSERT INTO clin.EstadosReserva (EstadoReservaId, Codigo, Nombre, OcupaHorario, EsFinal)
SELECT v.Id, v.Codigo, v.Nombre, v.Ocupa, v.Final
FROM (VALUES
    (1, 'CONFIRMADA',   N'Confirmada',   1, 0),
    (2, 'CANCELADA',    N'Cancelada',    0, 1),
    (3, 'REPROGRAMADA', N'Reprogramada', 0, 1),
    (4, 'ATENDIDA',     N'Atendida',     1, 1),
    (5, 'NO_ASISTIO',   N'No asistió',   1, 1)
) v (Id, Codigo, Nombre, Ocupa, Final)
WHERE NOT EXISTS (SELECT 1 FROM clin.EstadosReserva e WHERE e.EstadoReservaId = v.Id);
GO

-- Solo desde CONFIRMADA se puede transicionar. Los demás estados son finales.
INSERT INTO clin.TransicionesEstadoReserva (EstadoOrigenId, EstadoDestinoId)
SELECT v.o, v.d
FROM (VALUES (1, 2), (1, 3), (1, 4), (1, 5)) v (o, d)
WHERE NOT EXISTS (SELECT 1 FROM clin.TransicionesEstadoReserva t WHERE t.EstadoOrigenId = v.o AND t.EstadoDestinoId = v.d);
GO

INSERT INTO clin.MotivosCancelacion (MotivoCancelacionId, Codigo, Nombre, SoloPersonal)
SELECT v.Id, v.Codigo, v.Nombre, v.SoloPersonal
FROM (VALUES
    (1, 'PACIENTE_NO_PUEDE',    N'El paciente no puede asistir', 0),
    (2, 'PACIENTE_MEJORO',      N'El paciente ya no requiere la atención', 0),
    (3, 'CAMBIO_PLANES',        N'Cambio de planes o motivo personal', 0),
    (4, 'MEDICO_NO_DISPONIBLE', N'El médico no estará disponible', 1),
    (5, 'ERROR_REGISTRO',       N'Error en el registro de la cita', 1),
    (6, 'OTRO',                 N'Otro motivo', 0)
) v (Id, Codigo, Nombre, SoloPersonal)
WHERE NOT EXISTS (SELECT 1 FROM clin.MotivosCancelacion m WHERE m.MotivoCancelacionId = v.Id);
GO

/* ============================================================================
   8. FUNCTIONS
   ============================================================================ */

-- Hora civil actual de la clínica (America/Lima). Windows TZ id: 'SA Pacific Standard Time'.
CREATE OR ALTER FUNCTION clin.fn_AhoraLocal()
RETURNS DATETIME2(0)
AS
BEGIN
    RETURN CONVERT(DATETIME2(0), SYSUTCDATETIME() AT TIME ZONE 'UTC' AT TIME ZONE 'SA Pacific Standard Time');
END
GO

-- Permisos efectivos de un usuario activo (unión de sus roles activos).
CREATE OR ALTER FUNCTION seg.fn_PermisosUsuario (@UsuarioId INT)
RETURNS TABLE
AS
RETURN
(
    SELECT DISTINCT p.PermisoId, p.Codigo, p.Modulo
    FROM seg.Usuarios u
    JOIN seg.UsuarioRol ur ON ur.UsuarioId = u.UsuarioId
    JOIN seg.Roles r       ON r.RolId = ur.RolId AND r.Activo = 1
    JOIN seg.RolPermiso rp ON rp.RolId = r.RolId
    JOIN seg.Permisos p    ON p.PermisoId = rp.PermisoId
    WHERE u.UsuarioId = @UsuarioId
      AND u.Activo = 1
);
GO

CREATE OR ALTER FUNCTION seg.fn_TienePermiso (@UsuarioId INT, @Permiso VARCHAR(60))
RETURNS BIT
AS
BEGIN
    RETURN CASE WHEN EXISTS (SELECT 1 FROM seg.fn_PermisosUsuario(@UsuarioId) WHERE Codigo = @Permiso) THEN 1 ELSE 0 END;
END
GO

CREATE OR ALTER FUNCTION seg.fn_ConfigEntero (@Clave VARCHAR(60), @PorDefecto INT)
RETURNS INT
AS
BEGIN
    DECLARE @v INT = (SELECT TRY_CONVERT(INT, Valor) FROM seg.ConfiguracionSistema WHERE Clave = @Clave);
    RETURN ISNULL(@v, @PorDefecto);
END
GO

-- Slots de un médico en un rango, con su estado de ocupación. No aplica filtro de "ahora":
-- el SP consumidor filtra con clin.fn_AhoraLocal() evaluado una sola vez.
CREATE OR ALTER FUNCTION clin.fn_DisponibilidadMedico (@MedicoId INT, @FechaDesde DATE, @FechaHasta DATE)
RETURNS TABLE
AS
RETURN
(
    SELECT
        h.HorarioMedicoId,
        a.AgendaId,
        a.MedicoId,
        a.EspecialidadId,
        a.SedeId,
        a.ConsultorioId,
        a.TipoAtencionId,
        a.Fecha,
        h.HoraInicio,
        h.HoraFin,
        h.Bloqueado,
        CAST(CASE WHEN r.ReservaId IS NULL THEN 0 ELSE 1 END AS BIT) AS Ocupado,
        CAST(CASE WHEN h.Bloqueado = 0 AND r.ReservaId IS NULL THEN 1 ELSE 0 END AS BIT) AS Disponible
    FROM clin.AgendasMedicas a
    JOIN clin.HorariosMedicos h ON h.AgendaId = a.AgendaId AND h.Activo = 1
    LEFT JOIN clin.Reservas r   ON r.HorarioMedicoId = h.HorarioMedicoId AND r.OcupaHorario = 1
    WHERE a.MedicoId = @MedicoId
      AND a.Activo = 1
      AND a.Fecha BETWEEN @FechaDesde AND @FechaHasta
);
GO

/* ============================================================================
   9. VIEWS api.* (lectura; ROWVERSION se expone como texto hex '0x...')
   ============================================================================ */
CREATE OR ALTER VIEW api.vw_UsuariosRoles
AS
SELECT
    u.UsuarioId,
    u.NombreUsuario,
    u.Email,
    u.Nombres,
    u.Apellidos,
    u.Activo,
    u.PasswordAlgoritmo,
    u.IntentosFallidos,
    u.BloqueadoHastaUtc,
    u.UltimoAccesoUtc,
    u.FechaCreacionUtc,
    CONVERT(VARCHAR(18), CAST(u.VersionFila AS BINARY(8)), 1) AS VersionFila,
    roles.Roles,
    m.MedicoId,
    p.PacienteId
FROM seg.Usuarios u
OUTER APPLY
(
    SELECT STRING_AGG(r.Codigo, ',') WITHIN GROUP (ORDER BY r.Nivel DESC) AS Roles
    FROM seg.UsuarioRol ur
    JOIN seg.Roles r ON r.RolId = ur.RolId
    WHERE ur.UsuarioId = u.UsuarioId
) roles
LEFT JOIN clin.Medicos m   ON m.UsuarioId = u.UsuarioId
LEFT JOIN clin.Pacientes p ON p.UsuarioId = u.UsuarioId;
GO

CREATE OR ALTER VIEW api.vw_PacientesResumen
AS
SELECT
    p.PacienteId,
    p.UsuarioId,
    p.TipoDocumento,
    p.NumeroDocumento,
    p.Nombres,
    p.Apellidos,
    p.Apellidos + N', ' + p.Nombres AS NombreCompleto,
    p.FechaNacimiento,
    p.Sexo,
    p.Telefono,
    p.Email,
    p.Direccion,
    p.ContactoEmergencia,
    p.Activo,
    p.FechaCreacionUtc,
    CONVERT(VARCHAR(18), CAST(p.VersionFila AS BINARY(8)), 1) AS VersionFila,
    u.NombreUsuario,
    (SELECT COUNT(*) FROM clin.Reservas r WHERE r.PacienteId = p.PacienteId AND r.EstadoReservaId = 1) AS ReservasConfirmadas
FROM clin.Pacientes p
LEFT JOIN seg.Usuarios u ON u.UsuarioId = p.UsuarioId;
GO

CREATE OR ALTER VIEW api.vw_MedicosCatalogo
AS
SELECT
    m.MedicoId,
    m.UsuarioId,
    m.CMP,
    m.Nombres,
    m.Apellidos,
    N'Dr(a). ' + m.Nombres + N' ' + m.Apellidos AS NombreCompleto,
    m.Telefono,
    m.Email,
    m.Activo,
    CONVERT(VARCHAR(18), CAST(m.VersionFila AS BINARY(8)), 1) AS VersionFila,
    ep.EspecialidadId AS EspecialidadPrincipalId,
    ep.Nombre AS EspecialidadPrincipal,
    esp.Especialidades,
    esp.EspecialidadIds,
    sed.Sedes,
    sed.SedeIds
FROM clin.Medicos m
OUTER APPLY
(
    SELECT TOP (1) e.EspecialidadId, e.Nombre
    FROM clin.MedicoEspecialidad me
    JOIN clin.Especialidades e ON e.EspecialidadId = me.EspecialidadId
    WHERE me.MedicoId = m.MedicoId AND me.Activo = 1
    ORDER BY me.EsPrincipal DESC, e.Nombre
) ep
OUTER APPLY
(
    SELECT STRING_AGG(e.Nombre, ', ') WITHIN GROUP (ORDER BY me.EsPrincipal DESC, e.Nombre) AS Especialidades,
           STRING_AGG(CONVERT(VARCHAR(10), e.EspecialidadId), ',') AS EspecialidadIds
    FROM clin.MedicoEspecialidad me
    JOIN clin.Especialidades e ON e.EspecialidadId = me.EspecialidadId
    WHERE me.MedicoId = m.MedicoId AND me.Activo = 1
) esp
OUTER APPLY
(
    SELECT STRING_AGG(s.Nombre, ', ') WITHIN GROUP (ORDER BY s.Nombre) AS Sedes,
           STRING_AGG(CONVERT(VARCHAR(10), s.SedeId), ',') AS SedeIds
    FROM clin.MedicoSede ms
    JOIN clin.Sedes s ON s.SedeId = ms.SedeId
    WHERE ms.MedicoId = m.MedicoId AND ms.Activo = 1
) sed;
GO

CREATE OR ALTER VIEW api.vw_AgendasDetalle
AS
SELECT
    a.AgendaId,
    a.MedicoId,
    m.Nombres + N' ' + m.Apellidos AS MedicoNombre,
    m.CMP,
    a.EspecialidadId,
    e.Nombre AS Especialidad,
    a.SedeId,
    s.Nombre AS Sede,
    a.ConsultorioId,
    c.Nombre AS Consultorio,
    a.TipoAtencionId,
    t.Codigo AS TipoAtencion,
    a.PlantillaAgendaId,
    a.Fecha,
    a.HoraInicio,
    a.HoraFin,
    a.DuracionMinutos,
    a.Activo,
    CONVERT(VARCHAR(18), CAST(a.VersionFila AS BINARY(8)), 1) AS VersionFila,
    st.TotalSlots,
    st.SlotsOcupados,
    st.SlotsBloqueados,
    st.TotalSlots - st.SlotsOcupados - st.SlotsBloqueados AS SlotsLibres
FROM clin.AgendasMedicas a
JOIN clin.Medicos m        ON m.MedicoId = a.MedicoId
JOIN clin.Especialidades e ON e.EspecialidadId = a.EspecialidadId
JOIN clin.Sedes s          ON s.SedeId = a.SedeId
JOIN clin.Consultorios c   ON c.ConsultorioId = a.ConsultorioId
JOIN clin.TiposAtencion t  ON t.TipoAtencionId = a.TipoAtencionId
OUTER APPLY
(
    SELECT
        COUNT(*) AS TotalSlots,
        SUM(CASE WHEN r.ReservaId IS NOT NULL THEN 1 ELSE 0 END) AS SlotsOcupados,
        SUM(CASE WHEN r.ReservaId IS NULL AND h.Bloqueado = 1 THEN 1 ELSE 0 END) AS SlotsBloqueados
    FROM clin.HorariosMedicos h
    LEFT JOIN clin.Reservas r ON r.HorarioMedicoId = h.HorarioMedicoId AND r.OcupaHorario = 1
    WHERE h.AgendaId = a.AgendaId AND h.Activo = 1
) st;
GO

CREATE OR ALTER VIEW api.vw_HorariosDisponibilidad
AS
SELECT
    h.HorarioMedicoId,
    a.AgendaId,
    a.MedicoId,
    m.Nombres + N' ' + m.Apellidos AS MedicoNombre,
    a.EspecialidadId,
    e.Nombre AS Especialidad,
    a.SedeId,
    s.Nombre AS Sede,
    a.ConsultorioId,
    c.Nombre AS Consultorio,
    a.TipoAtencionId,
    t.Codigo AS TipoAtencion,
    a.Fecha,
    h.HoraInicio,
    h.HoraFin,
    h.Bloqueado,
    b.TipoBloqueo,
    b.Motivo AS MotivoBloqueo,
    r.ReservaId,
    CAST(CASE WHEN r.ReservaId IS NULL THEN 0 ELSE 1 END AS BIT) AS Ocupado,
    CAST(CASE WHEN h.Bloqueado = 0 AND r.ReservaId IS NULL AND a.Activo = 1 AND h.Activo = 1
              AND m.Activo = 1 THEN 1 ELSE 0 END AS BIT) AS Disponible
FROM clin.HorariosMedicos h
JOIN clin.AgendasMedicas a  ON a.AgendaId = h.AgendaId
JOIN clin.Medicos m         ON m.MedicoId = a.MedicoId
JOIN clin.Especialidades e  ON e.EspecialidadId = a.EspecialidadId
JOIN clin.Sedes s           ON s.SedeId = a.SedeId
JOIN clin.Consultorios c    ON c.ConsultorioId = a.ConsultorioId
JOIN clin.TiposAtencion t   ON t.TipoAtencionId = a.TipoAtencionId
LEFT JOIN clin.BloqueosMedico b ON b.BloqueoId = h.BloqueoId
LEFT JOIN clin.Reservas r   ON r.HorarioMedicoId = h.HorarioMedicoId AND r.OcupaHorario = 1
WHERE a.Activo = 1 AND h.Activo = 1;
GO

CREATE OR ALTER VIEW api.vw_ReservasDetalle
AS
SELECT
    r.ReservaId,
    r.CodigoReserva,
    r.HorarioMedicoId,
    r.PacienteId,
    p.Apellidos + N', ' + p.Nombres AS PacienteNombre,
    p.TipoDocumento + N' ' + p.NumeroDocumento AS PacienteDocumento,
    p.UsuarioId AS PacienteUsuarioId,
    r.MedicoId,
    m.Nombres + N' ' + m.Apellidos AS MedicoNombre,
    m.CMP,
    m.UsuarioId AS MedicoUsuarioId,
    r.EspecialidadId,
    e.Nombre AS Especialidad,
    r.SedeId,
    s.Nombre AS Sede,
    s.Direccion AS SedeDireccion,
    r.ConsultorioId,
    c.Nombre AS Consultorio,
    r.TipoAtencionId,
    t.Codigo AS TipoAtencion,
    r.FechaCita,
    r.HoraInicio,
    r.HoraFin,
    r.EstadoReservaId,
    er.Codigo AS EstadoCodigo,
    er.Nombre AS EstadoNombre,
    r.Observacion,
    r.MotivoCancelacionId,
    mc.Nombre AS MotivoCancelacion,
    r.ObservacionCancelacion,
    r.FechaCancelacionUtc,
    r.FechaCierreUtc,
    r.ReservaOrigenId,
    ro.CodigoReserva AS CodigoReservaOrigen,
    r.ReservaReemplazoId,
    rr.CodigoReserva AS CodigoReservaReemplazo,
    r.CreadoPorUsuarioId,
    uc.NombreUsuario AS CreadoPor,
    r.FechaCreacionUtc,
    CONVERT(VARCHAR(18), CAST(r.VersionFila AS BINARY(8)), 1) AS VersionFila
FROM clin.Reservas r
JOIN clin.Pacientes p          ON p.PacienteId = r.PacienteId
JOIN clin.Medicos m            ON m.MedicoId = r.MedicoId
JOIN clin.Especialidades e     ON e.EspecialidadId = r.EspecialidadId
JOIN clin.Sedes s              ON s.SedeId = r.SedeId
JOIN clin.Consultorios c       ON c.ConsultorioId = r.ConsultorioId
JOIN clin.TiposAtencion t      ON t.TipoAtencionId = r.TipoAtencionId
JOIN clin.EstadosReserva er    ON er.EstadoReservaId = r.EstadoReservaId
JOIN seg.Usuarios uc           ON uc.UsuarioId = r.CreadoPorUsuarioId
LEFT JOIN clin.MotivosCancelacion mc ON mc.MotivoCancelacionId = r.MotivoCancelacionId
LEFT JOIN clin.Reservas ro     ON ro.ReservaId = r.ReservaOrigenId
LEFT JOIN clin.Reservas rr     ON rr.ReservaId = r.ReservaReemplazoId;
GO

CREATE OR ALTER VIEW api.vw_AuditoriaDetalle
AS
SELECT
    b.BitacoraId,
    b.FechaUtc,
    b.UsuarioId,
    u.NombreUsuario,
    b.Accion,
    b.Entidad,
    b.EntidadId,
    b.Exitoso,
    b.Detalle,
    b.Ip,
    b.UserAgent,
    b.CorrelationId
FROM audit.BitacoraSistema b
LEFT JOIN seg.Usuarios u ON u.UsuarioId = b.UsuarioId;
GO

-- Catálogos para combos (solo lectura).
CREATE OR ALTER VIEW api.vw_Especialidades AS
SELECT EspecialidadId, Nombre, Descripcion, Activo,
       (SELECT COUNT(*) FROM clin.MedicoEspecialidad me JOIN clin.Medicos m ON m.MedicoId = me.MedicoId
        WHERE me.EspecialidadId = e.EspecialidadId AND me.Activo = 1 AND m.Activo = 1) AS MedicosActivos
FROM clin.Especialidades e;
GO
CREATE OR ALTER VIEW api.vw_Sedes AS
SELECT SedeId, Codigo, Nombre, Direccion, Activo FROM clin.Sedes;
GO
CREATE OR ALTER VIEW api.vw_Consultorios AS
SELECT c.ConsultorioId, c.SedeId, s.Nombre AS Sede, c.Codigo, c.Nombre, c.Piso, c.Activo
FROM clin.Consultorios c JOIN clin.Sedes s ON s.SedeId = c.SedeId;
GO
CREATE OR ALTER VIEW api.vw_TiposAtencion AS
SELECT TipoAtencionId, Codigo, Nombre FROM clin.TiposAtencion;
GO
CREATE OR ALTER VIEW api.vw_EstadosReserva AS
SELECT EstadoReservaId, Codigo, Nombre, OcupaHorario, EsFinal FROM clin.EstadosReserva;
GO
CREATE OR ALTER VIEW api.vw_MotivosCancelacion AS
SELECT MotivoCancelacionId, Codigo, Nombre, SoloPersonal FROM clin.MotivosCancelacion WHERE Activo = 1;
GO
CREATE OR ALTER VIEW api.vw_Roles AS
SELECT r.RolId, r.Codigo, r.Nombre, r.Descripcion, r.Nivel, r.Activo,
       (SELECT STRING_AGG(p.Codigo, ',') WITHIN GROUP (ORDER BY p.Codigo)
        FROM seg.RolPermiso rp JOIN seg.Permisos p ON p.PermisoId = rp.PermisoId WHERE rp.RolId = r.RolId) AS Permisos
FROM seg.Roles r;
GO
CREATE OR ALTER VIEW api.vw_Permisos AS
SELECT PermisoId, Codigo, Modulo, Descripcion FROM seg.Permisos;
GO
CREATE OR ALTER VIEW api.vw_Configuracion AS
SELECT Clave, Valor, TipoDato, ValorMinimo, ValorMaximo, Descripcion, FechaModificacionUtc FROM seg.ConfiguracionSistema;
GO

-- Combina fecha y hora civiles en un DATETIME2 (inlineable en SQL Server 2019+).
CREATE OR ALTER FUNCTION clin.fn_FechaHora (@Fecha DATE, @Hora TIME(0))
RETURNS DATETIME2(0)
AS
BEGIN
    RETURN DATEADD(SECOND, DATEDIFF(SECOND, CAST('00:00:00' AS TIME(0)), @Hora), CAST(@Fecha AS DATETIME2(0)));
END
GO

/* ============================================================================
   10. PROCEDIMIENTOS INTERNOS (no expuestos a la aplicación)
   ============================================================================ */

-- Bitácora de negocio. Nunca recibe passwords, hashes ni tokens.
CREATE OR ALTER PROCEDURE audit.usp_Registrar
    @UsuarioId     INT,
    @Accion        VARCHAR(50),
    @Entidad       VARCHAR(50)    = NULL,
    @EntidadId     BIGINT         = NULL,
    @Exitoso       BIT,
    @Detalle       NVARCHAR(1000) = NULL,
    @Ip            VARCHAR(45)    = NULL,
    @UserAgent     NVARCHAR(300)  = NULL,
    @CorrelationId VARCHAR(64)    = NULL
AS
BEGIN
    SET NOCOUNT ON;
    INSERT INTO audit.BitacoraSistema (UsuarioId, Accion, Entidad, EntidadId, Exitoso, Detalle, Ip, UserAgent, CorrelationId)
    VALUES
    (
        CASE WHEN EXISTS (SELECT 1 FROM seg.Usuarios WHERE UsuarioId = @UsuarioId) THEN @UsuarioId END,
        @Accion, @Entidad, @EntidadId, @Exitoso, LEFT(@Detalle, 1000), LEFT(@Ip, 45), LEFT(@UserAgent, 300), LEFT(@CorrelationId, 64)
    );
END
GO

-- Fila estándar de resultado de comandos: Exito | Codigo | Mensaje | EntidadId
CREATE OR ALTER PROCEDURE seg.usp_Resultado
    @Exito     BIT,
    @Codigo    VARCHAR(50),
    @Mensaje   NVARCHAR(400),
    @EntidadId BIGINT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SELECT @Exito AS Exito, @Codigo AS Codigo, @Mensaje AS Mensaje, @EntidadId AS EntidadId;
END
GO

-- Rechazo de negocio: registra en bitácora (fuera de transacción) y devuelve la fila estándar.
CREATE OR ALTER PROCEDURE seg.usp_Rechazo
    @ActorUsuarioId INT,
    @Accion         VARCHAR(50),
    @Entidad        VARCHAR(50),
    @EntidadId      BIGINT,
    @Codigo         VARCHAR(50),
    @Mensaje        NVARCHAR(400),
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @Detalle NVARCHAR(1000) = CONCAT(@Codigo, N': ', @Mensaje);
    EXEC audit.usp_Registrar @ActorUsuarioId, @Accion, @Entidad, @EntidadId, 0, @Detalle, @Ip, @UserAgent, @CorrelationId;
    EXEC seg.usp_Resultado 0, @Codigo, @Mensaje, NULL;
END
GO

-- Traduce el error capturado en un CATCH a un código de negocio seguro.
-- Debe invocarse dentro del bloque CATCH (ERROR_*() conservan el contexto).
CREATE OR ALTER PROCEDURE seg.usp_ErrorCapturado
    @ActorUsuarioId INT,
    @Accion         VARCHAR(50),
    @Entidad        VARCHAR(50)   = NULL,
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @Num INT = ERROR_NUMBER(),
            @Msg NVARCHAR(2048) = ERROR_MESSAGE(),
            @Proc NVARCHAR(128) = ERROR_PROCEDURE(),
            @Linea INT = ERROR_LINE(),
            @Codigo VARCHAR(50),
            @Mensaje NVARCHAR(400);


    SELECT @Codigo = CASE
            WHEN @Num = 1205 OR @Num = 1222 OR @Num = 50409                   THEN 'CONCURRENCIA'
            WHEN @Num IN (2601, 2627) AND @Msg LIKE '%UX_Reservas_HorarioOcupado%' THEN 'SLOT_OCUPADO'
            WHEN @Num IN (2601, 2627) AND @Msg LIKE '%UX_Reservas_IdempotencyKey%' THEN 'IDEMPOTENCIA_DUPLICADA'
            WHEN @Num IN (2601, 2627) AND @Msg LIKE '%UQ_Pacientes_Documento%'     THEN 'DOCUMENTO_DUPLICADO'
            WHEN @Num IN (2601, 2627) AND @Msg LIKE '%UQ_Usuarios_NombreUsuario%'  THEN 'USUARIO_DUPLICADO'
            WHEN @Num IN (2601, 2627) AND @Msg LIKE '%UX_Usuarios_Email%'          THEN 'EMAIL_DUPLICADO'
            WHEN @Num IN (2601, 2627) AND @Msg LIKE '%UQ_Medicos_CMP%'             THEN 'CMP_DUPLICADO'
            WHEN @Num IN (2601, 2627)                                             THEN 'DUPLICADO'
            WHEN @Num = 547                                                       THEN 'RESTRICCION_DATOS'
            ELSE 'ERROR_INTERNO' END;

    SET @Mensaje = CASE @Codigo
            WHEN 'CONCURRENCIA'           THEN N'Otra operación está usando este recurso. Intenta nuevamente en unos segundos.'
            WHEN 'SLOT_OCUPADO'           THEN N'El horario acaba de ser reservado por otra persona.'
            WHEN 'IDEMPOTENCIA_DUPLICADA' THEN N'La solicitud ya fue procesada.'
            WHEN 'DOCUMENTO_DUPLICADO'    THEN N'Ya existe un paciente con ese documento.'
            WHEN 'USUARIO_DUPLICADO'      THEN N'El nombre de usuario ya está en uso.'
            WHEN 'EMAIL_DUPLICADO'        THEN N'El correo ya está registrado.'
            WHEN 'CMP_DUPLICADO'          THEN N'Ya existe un médico con ese CMP.'
            WHEN 'DUPLICADO'              THEN N'El registro ya existe.'
            WHEN 'RESTRICCION_DATOS'      THEN N'Los datos no cumplen las reglas de integridad.'
            ELSE N'Ocurrió un error inesperado. Intenta nuevamente o contacta al administrador.' END;

    DECLARE @Detalle NVARCHAR(1000) = LEFT(CONCAT(@Codigo, N' | SQL ', @Num, N' en ', ISNULL(@Proc, N'-'), N':', @Linea, N' | ', @Msg), 1000);
    EXEC audit.usp_Registrar @ActorUsuarioId, @Accion, @Entidad, NULL, 0, @Detalle, @Ip, @UserAgent, @CorrelationId;
    EXEC seg.usp_Resultado 0, @Codigo, @Mensaje, NULL;
END
GO

-- Obtiene un applock de transacción; lanza 50409 si no se consigue en @TimeoutMs.
CREATE OR ALTER PROCEDURE seg.usp_ObtenerLock
    @Recurso   NVARCHAR(255),
    @Modo      VARCHAR(10) = 'Exclusive',
    @TimeoutMs INT = 10000
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @r INT;
    EXEC @r = sys.sp_getapplock @Resource = @Recurso, @LockMode = @Modo, @LockOwner = 'Transaction', @LockTimeout = @TimeoutMs;
    IF @r < 0
        THROW 50409, N'No se pudo obtener el bloqueo del recurso (concurrencia).', 1;
END
GO

CREATE OR ALTER FUNCTION seg.fn_TieneRol (@UsuarioId INT, @RolCodigo VARCHAR(30))
RETURNS BIT
AS
BEGIN
    RETURN CASE WHEN EXISTS
    (
        SELECT 1 FROM seg.UsuarioRol ur JOIN seg.Roles r ON r.RolId = ur.RolId
        WHERE ur.UsuarioId = @UsuarioId AND r.Codigo = @RolCodigo AND r.Activo = 1
    ) THEN 1 ELSE 0 END;
END
GO

-- Verificación de permiso para SP de consulta: lanza 50403 (Laravel lo traduce a 403).
CREATE OR ALTER PROCEDURE seg.usp_ExigirPermiso
    @UsuarioId INT,
    @Permiso   VARCHAR(60)
AS
BEGIN
    SET NOCOUNT ON;
    IF seg.fn_TienePermiso(@UsuarioId, @Permiso) = 0
        THROW 50403, N'SIN_PERMISO', 1;
END
GO

/* ============================================================================
   11. api — SEGURIDAD / AUTENTICACIÓN
   ============================================================================ */

-- Devuelve el hash para que Laravel lo verifique (Hash::check / PBKDF2 legacy). Nunca se registra.
CREATE OR ALTER PROCEDURE api.usp_AuthObtenerUsuario
    @Login VARCHAR(150)
AS
BEGIN
    SET NOCOUNT ON;
    SELECT TOP (1)
        u.UsuarioId,
        u.NombreUsuario,
        u.Email,
        u.Nombres,
        u.Apellidos,
        u.PasswordHash,
        u.PasswordAlgoritmo,
        u.Activo,
        CAST(CASE WHEN u.BloqueadoHastaUtc > SYSUTCDATETIME() THEN 1 ELSE 0 END AS BIT) AS Bloqueado,
        CASE WHEN u.BloqueadoHastaUtc > SYSUTCDATETIME() THEN DATEDIFF(SECOND, SYSUTCDATETIME(), u.BloqueadoHastaUtc) ELSE 0 END AS SegundosBloqueo,
        m.MedicoId,
        p.PacienteId
    FROM seg.Usuarios u
    LEFT JOIN clin.Medicos m   ON m.UsuarioId = u.UsuarioId
    LEFT JOIN clin.Pacientes p ON p.UsuarioId = u.UsuarioId
    WHERE u.NombreUsuario = @Login OR u.Email = @Login
    ORDER BY CASE WHEN u.NombreUsuario = @Login THEN 0 ELSE 1 END;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AuthRegistrarFallo
    @UsuarioId     INT          = NULL,
    @Login         VARCHAR(150),
    @Motivo        VARCHAR(20),          -- CREDENCIALES | BLOQUEADO | INACTIVO
    @Ip            VARCHAR(45)   = NULL,
    @UserAgent     NVARCHAR(300) = NULL,
    @CorrelationId VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @Max INT = seg.fn_ConfigEntero('MAX_INTENTOS_LOGIN', 5),
            @Min INT = seg.fn_ConfigEntero('MINUTOS_BLOQUEO_LOGIN', 15),
            @Intentos TINYINT = 0,
            @Bloqueo BIT = 0,
            @Detalle NVARCHAR(1000);

    IF @UsuarioId IS NOT NULL AND @Motivo = 'CREDENCIALES'
    BEGIN
        UPDATE seg.Usuarios
        SET @Intentos = IntentosFallidos = CASE WHEN IntentosFallidos >= 250 THEN 250 ELSE IntentosFallidos + 1 END
        WHERE UsuarioId = @UsuarioId;

        IF @Intentos >= @Max
        BEGIN
            UPDATE seg.Usuarios
            SET BloqueadoHastaUtc = DATEADD(MINUTE, @Min, SYSUTCDATETIME()), IntentosFallidos = 0
            WHERE UsuarioId = @UsuarioId;
            SET @Bloqueo = 1;
        END
    END

    SET @Detalle = CONCAT(N'Motivo=', @Motivo, N'; login=', LEFT(@Login, 60), CASE WHEN @Bloqueo = 1 THEN N'; cuenta bloqueada temporalmente' ELSE N'' END);
    EXEC audit.usp_Registrar @UsuarioId, 'LOGIN_FAIL', 'Usuario', @UsuarioId, 0, @Detalle, @Ip, @UserAgent, @CorrelationId;

    IF @Bloqueo = 1 OR @Motivo = 'BLOQUEADO'
        EXEC seg.usp_Resultado 0, 'CUENTA_BLOQUEADA', N'La cuenta está bloqueada temporalmente por intentos fallidos. Intenta más tarde.', NULL;
    ELSE
        EXEC seg.usp_Resultado 0, 'CREDENCIALES_INVALIDAS', N'Usuario o contraseña incorrectos.', NULL;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AuthRegistrarExito
    @UsuarioId     INT,
    @Ip            VARCHAR(45)   = NULL,
    @UserAgent     NVARCHAR(300) = NULL,
    @CorrelationId VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    IF NOT EXISTS (SELECT 1 FROM seg.Usuarios WHERE UsuarioId = @UsuarioId AND Activo = 1
                   AND (BloqueadoHastaUtc IS NULL OR BloqueadoHastaUtc <= SYSUTCDATETIME()))
    BEGIN
        EXEC seg.usp_Resultado 0, 'CUENTA_NO_DISPONIBLE', N'La cuenta no está disponible.', NULL;
        RETURN;
    END

    UPDATE seg.Usuarios
    SET IntentosFallidos = 0, BloqueadoHastaUtc = NULL, UltimoAccesoUtc = SYSUTCDATETIME()
    WHERE UsuarioId = @UsuarioId;

    EXEC audit.usp_Registrar @UsuarioId, 'LOGIN_OK', 'Usuario', @UsuarioId, 1, NULL, @Ip, @UserAgent, @CorrelationId;
    EXEC seg.usp_Resultado 1, 'LOGIN_OK', N'Inicio de sesión correcto.', @UsuarioId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AuthRegistrarLogout
    @UsuarioId     INT,
    @Ip            VARCHAR(45)   = NULL,
    @UserAgent     NVARCHAR(300) = NULL,
    @CorrelationId VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    EXEC audit.usp_Registrar @UsuarioId, 'LOGOUT', 'Usuario', @UsuarioId, 1, NULL, @Ip, @UserAgent, @CorrelationId;
    EXEC seg.usp_Resultado 1, 'LOGOUT', N'Sesión cerrada.', @UsuarioId;
END
GO

-- Un único result set: Tipo (ROL | PERMISO) y Codigo.
CREATE OR ALTER PROCEDURE api.usp_AuthObtenerPermisosUsuario
    @UsuarioId INT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT CAST('ROL' AS VARCHAR(10)) AS Tipo, r.Codigo
    FROM seg.UsuarioRol ur
    JOIN seg.Roles r    ON r.RolId = ur.RolId AND r.Activo = 1
    JOIN seg.Usuarios u ON u.UsuarioId = ur.UsuarioId AND u.Activo = 1
    WHERE ur.UsuarioId = @UsuarioId
    UNION ALL
    SELECT 'PERMISO', Codigo FROM seg.fn_PermisosUsuario(@UsuarioId);
END
GO

-- Rehash (PBKDF2 legacy -> bcrypt) o cambio de contraseña. Solo recibe el HASH, nunca la contraseña.
CREATE OR ALTER PROCEDURE api.usp_UsuarioActualizarPasswordHash
    @ActorUsuarioId    INT,
    @UsuarioId         INT,
    @PasswordHash      NVARCHAR(512),
    @PasswordAlgoritmo VARCHAR(20),
    @Ip                VARCHAR(45)   = NULL,
    @UserAgent         NVARCHAR(300) = NULL,
    @CorrelationId     VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF @ActorUsuarioId <> @UsuarioId AND seg.fn_TienePermiso(@ActorUsuarioId, 'usuarios.editar') = 0
    BEGIN
        EXEC seg.usp_Rechazo @ActorUsuarioId, 'PASSWORD_ACTUALIZADO', 'Usuario', @UsuarioId, 'SIN_PERMISO', N'No tienes permiso.', @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END
    IF @PasswordAlgoritmo NOT IN ('BCRYPT', 'ARGON2ID') OR LEN(@PasswordHash) < 50
    BEGIN
        EXEC seg.usp_Rechazo @ActorUsuarioId, 'PASSWORD_ACTUALIZADO', 'Usuario', @UsuarioId, 'HASH_INVALIDO', N'Formato de hash no admitido.', @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END
    IF seg.fn_TieneRol(@UsuarioId, 'SUPERADMIN') = 1 AND @ActorUsuarioId <> @UsuarioId AND seg.fn_TieneRol(@ActorUsuarioId, 'SUPERADMIN') = 0
    BEGIN
        EXEC seg.usp_Rechazo @ActorUsuarioId, 'PASSWORD_ACTUALIZADO', 'Usuario', @UsuarioId, 'SIN_PERMISO', N'Solo un superadministrador puede modificar a otro superadministrador.', @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END

    UPDATE seg.Usuarios
    SET PasswordHash = @PasswordHash, PasswordAlgoritmo = @PasswordAlgoritmo,
        PasswordActualizadoUtc = SYSUTCDATETIME(), FechaModificacionUtc = SYSUTCDATETIME()
    WHERE UsuarioId = @UsuarioId;

    IF @@ROWCOUNT = 0
    BEGIN
        EXEC seg.usp_Rechazo @ActorUsuarioId, 'PASSWORD_ACTUALIZADO', 'Usuario', @UsuarioId, 'NO_ENCONTRADO', N'El usuario no existe.', @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END

    DECLARE @Det NVARCHAR(200) = CONCAT(N'Algoritmo=', @PasswordAlgoritmo);
    EXEC audit.usp_Registrar @ActorUsuarioId, 'PASSWORD_ACTUALIZADO', 'Usuario', @UsuarioId, 1, @Det, @Ip, @UserAgent, @CorrelationId;
    EXEC seg.usp_Resultado 1, 'PASSWORD_ACTUALIZADO', N'Contraseña actualizada.', @UsuarioId;
END
GO

-- Arranque: crea el primer SUPERADMIN solo si aún no existe ninguno activo (idempotente).
CREATE OR ALTER PROCEDURE api.usp_SistemaInicializarSuperadmin
    @NombreUsuario     VARCHAR(50),
    @Email             VARCHAR(150),
    @Nombres           NVARCHAR(80),
    @Apellidos         NVARCHAR(100),
    @PasswordHash      NVARCHAR(512),
    @PasswordAlgoritmo VARCHAR(20),
    @CorrelationId     VARCHAR(64) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @UsuarioId INT;

    BEGIN TRY
        BEGIN TRAN;
        EXEC seg.usp_ObtenerLock N'Sistema:Bootstrap';

        SELECT TOP (1) @UsuarioId = u.UsuarioId
        FROM seg.Usuarios u JOIN seg.UsuarioRol ur ON ur.UsuarioId = u.UsuarioId
        WHERE ur.RolId = 1 AND u.Activo = 1;

        IF @UsuarioId IS NOT NULL
        BEGIN
            COMMIT;
            EXEC seg.usp_Resultado 0, 'YA_INICIALIZADO', N'Ya existe un superadministrador activo.', @UsuarioId;
            RETURN;
        END

        INSERT INTO seg.Usuarios (NombreUsuario, Email, Nombres, Apellidos, PasswordHash, PasswordAlgoritmo)
        VALUES (@NombreUsuario, @Email, @Nombres, @Apellidos, @PasswordHash, @PasswordAlgoritmo);
        SET @UsuarioId = SCOPE_IDENTITY();
        INSERT INTO seg.UsuarioRol (UsuarioId, RolId) VALUES (@UsuarioId, 1);

        EXEC audit.usp_Registrar @UsuarioId, 'USUARIO_CREADO', 'Usuario', @UsuarioId, 1, N'Superadministrador inicial', NULL, NULL, @CorrelationId;
        EXEC audit.usp_Registrar @UsuarioId, 'ROL_ASIGNADO', 'Usuario', @UsuarioId, 1, N'Rol=SUPERADMIN', NULL, NULL, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, 'USUARIO_CREADO', N'Superadministrador creado.', @UsuarioId;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado NULL, 'USUARIO_CREADO', 'Usuario', NULL, NULL, @CorrelationId;
    END CATCH
END
GO

/* ============================================================================
   12. api — ADMINISTRACIÓN DE USUARIOS / RBAC / CONFIGURACIÓN
   ============================================================================ */
CREATE OR ALTER PROCEDURE api.usp_AdminUsuariosBuscar
    @ActorUsuarioId INT,
    @Texto          NVARCHAR(100) = NULL,
    @RolCodigo      VARCHAR(30)   = NULL,
    @Activo         BIT           = NULL,
    @Pagina         INT           = 1,
    @TamanoPagina   INT           = 20
AS
BEGIN
    SET NOCOUNT ON;
    EXEC seg.usp_ExigirPermiso @ActorUsuarioId, 'usuarios.ver';

    SET @Pagina = CASE WHEN @Pagina < 1 THEN 1 ELSE @Pagina END;
    SET @TamanoPagina = CASE WHEN @TamanoPagina BETWEEN 1 AND 100 THEN @TamanoPagina ELSE 20 END;
    SET @Texto = NULLIF(LTRIM(RTRIM(@Texto)), N'');

    SELECT v.UsuarioId, v.NombreUsuario, v.Email, v.Nombres, v.Apellidos, v.Activo, v.Roles,
           v.PasswordAlgoritmo, v.BloqueadoHastaUtc, v.UltimoAccesoUtc, v.FechaCreacionUtc, v.VersionFila,
           v.MedicoId, v.PacienteId,
           COUNT(*) OVER () AS TotalFilas
    FROM api.vw_UsuariosRoles v
    WHERE (@Texto IS NULL OR v.NombreUsuario LIKE N'%' + @Texto + N'%' OR v.Nombres LIKE N'%' + @Texto + N'%'
           OR v.Apellidos LIKE N'%' + @Texto + N'%' OR v.Email LIKE N'%' + @Texto + N'%')
      AND (@Activo IS NULL OR v.Activo = @Activo)
      AND (@RolCodigo IS NULL OR EXISTS (SELECT 1 FROM seg.UsuarioRol ur JOIN seg.Roles r ON r.RolId = ur.RolId
                                         WHERE ur.UsuarioId = v.UsuarioId AND r.Codigo = @RolCodigo))
    ORDER BY v.Apellidos, v.Nombres, v.UsuarioId
    OFFSET (@Pagina - 1) * @TamanoPagina ROWS FETCH NEXT @TamanoPagina ROWS ONLY;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminUsuarioObtener
    @ActorUsuarioId INT,
    @UsuarioId      INT
AS
BEGIN
    SET NOCOUNT ON;
    IF @ActorUsuarioId <> @UsuarioId
        EXEC seg.usp_ExigirPermiso @ActorUsuarioId, 'usuarios.ver';
    SELECT UsuarioId, NombreUsuario, Email, Nombres, Apellidos, Activo, Roles, PasswordAlgoritmo,
           BloqueadoHastaUtc, UltimoAccesoUtc, FechaCreacionUtc, VersionFila, MedicoId, PacienteId
    FROM api.vw_UsuariosRoles WHERE UsuarioId = @UsuarioId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminUsuarioCrear
    @ActorUsuarioId    INT,
    @NombreUsuario     VARCHAR(50),
    @Email             VARCHAR(150),
    @Nombres           NVARCHAR(80),
    @Apellidos         NVARCHAR(100),
    @PasswordHash      NVARCHAR(512),
    @PasswordAlgoritmo VARCHAR(20),
    @RolCodigo         VARCHAR(30),
    @MedicoId          INT           = NULL,
    @PacienteId        INT           = NULL,
    @Ip                VARCHAR(45)   = NULL,
    @UserAgent         NVARCHAR(300) = NULL,
    @CorrelationId     VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = 'USUARIO_CREADO', @RolId TINYINT, @UsuarioId INT, @Det NVARCHAR(300);

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'usuarios.crear') = 0
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', NULL, 'SIN_PERMISO', N'No tienes permiso.', @Ip, @UserAgent, @CorrelationId; RETURN; END

    SELECT @RolId = RolId FROM seg.Roles WHERE Codigo = @RolCodigo AND Activo = 1;
    IF @RolId IS NULL
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', NULL, 'ROL_INVALIDO', N'El rol indicado no existe.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    IF @RolCodigo IN ('SUPERADMIN', 'ADMINISTRADOR') AND seg.fn_TieneRol(@ActorUsuarioId, 'SUPERADMIN') = 0
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', NULL, 'SIN_PERMISO', N'Solo un superadministrador puede crear administradores.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    IF @PasswordAlgoritmo NOT IN ('BCRYPT', 'ARGON2ID') OR LEN(@PasswordHash) < 50
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', NULL, 'HASH_INVALIDO', N'Formato de hash no admitido.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    IF @RolCodigo = 'MEDICO' AND @MedicoId IS NULL
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', NULL, 'MEDICO_REQUERIDO', N'Una cuenta de médico debe vincularse a un médico registrado.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    IF @RolCodigo = 'PACIENTE' AND @PacienteId IS NULL
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', NULL, 'PACIENTE_REQUERIDO', N'Una cuenta de paciente debe vincularse a un paciente registrado.', @Ip, @UserAgent, @CorrelationId; RETURN; END

    BEGIN TRY
        BEGIN TRAN;

        IF @MedicoId IS NOT NULL AND NOT EXISTS (SELECT 1 FROM clin.Medicos WITH (UPDLOCK) WHERE MedicoId = @MedicoId AND UsuarioId IS NULL)
        BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', NULL, 'MEDICO_YA_VINCULADO', N'El médico no existe o ya tiene una cuenta.', @Ip, @UserAgent, @CorrelationId; RETURN; END
        IF @PacienteId IS NOT NULL AND NOT EXISTS (SELECT 1 FROM clin.Pacientes WITH (UPDLOCK) WHERE PacienteId = @PacienteId AND UsuarioId IS NULL)
        BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', NULL, 'PACIENTE_YA_VINCULADO', N'El paciente no existe o ya tiene una cuenta.', @Ip, @UserAgent, @CorrelationId; RETURN; END

        INSERT INTO seg.Usuarios (NombreUsuario, Email, Nombres, Apellidos, PasswordHash, PasswordAlgoritmo)
        VALUES (LOWER(LTRIM(RTRIM(@NombreUsuario))), NULLIF(LOWER(LTRIM(RTRIM(@Email))), ''), LTRIM(RTRIM(@Nombres)), LTRIM(RTRIM(@Apellidos)), @PasswordHash, @PasswordAlgoritmo);
        SET @UsuarioId = SCOPE_IDENTITY();

        INSERT INTO seg.UsuarioRol (UsuarioId, RolId, AsignadoPorUsuarioId) VALUES (@UsuarioId, @RolId, @ActorUsuarioId);
        IF @MedicoId IS NOT NULL   UPDATE clin.Medicos   SET UsuarioId = @UsuarioId, FechaModificacionUtc = SYSUTCDATETIME() WHERE MedicoId = @MedicoId;
        IF @PacienteId IS NOT NULL UPDATE clin.Pacientes SET UsuarioId = @UsuarioId, FechaModificacionUtc = SYSUTCDATETIME() WHERE PacienteId = @PacienteId;

        SET @Det = CONCAT(N'Usuario=', @NombreUsuario);
        EXEC audit.usp_Registrar @ActorUsuarioId, 'USUARIO_CREADO', 'Usuario', @UsuarioId, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        SET @Det = CONCAT(N'Rol=', @RolCodigo);
        EXEC audit.usp_Registrar @ActorUsuarioId, 'ROL_ASIGNADO', 'Usuario', @UsuarioId, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, 'USUARIO_CREADO', N'Usuario creado correctamente.', @UsuarioId;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, 'Usuario', @Ip, @UserAgent, @CorrelationId;
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminUsuarioActualizar
    @ActorUsuarioId INT,
    @UsuarioId      INT,
    @Email          VARCHAR(150),
    @Nombres        NVARCHAR(80),
    @Apellidos      NVARCHAR(100),
    @Activo         BIT,
    @VersionFila    VARCHAR(18),
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = 'USUARIO_ACTUALIZADO', @Version BINARY(8) = TRY_CONVERT(BINARY(8), @VersionFila, 1), @Actual BINARY(8), @ActivoActual BIT;

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'usuarios.editar') = 0
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'SIN_PERMISO', N'No tienes permiso.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    IF @UsuarioId = @ActorUsuarioId AND @Activo = 0
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'OPERACION_NO_PERMITIDA', N'No puedes desactivar tu propia cuenta.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    IF seg.fn_TieneRol(@UsuarioId, 'SUPERADMIN') = 1 AND seg.fn_TieneRol(@ActorUsuarioId, 'SUPERADMIN') = 0
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'SIN_PERMISO', N'Solo un superadministrador puede modificar a otro superadministrador.', @Ip, @UserAgent, @CorrelationId; RETURN; END

    BEGIN TRY
        BEGIN TRAN;
        SELECT @Actual = VersionFila, @ActivoActual = Activo FROM seg.Usuarios WITH (UPDLOCK, HOLDLOCK) WHERE UsuarioId = @UsuarioId;
        IF @Actual IS NULL
        BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'NO_ENCONTRADO', N'El usuario no existe.', @Ip, @UserAgent, @CorrelationId; RETURN; END
        IF @Version IS NULL OR @Version <> @Actual
        BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'CONFLICTO_EDICION', N'El registro fue modificado por otro usuario. Recarga la página.', @Ip, @UserAgent, @CorrelationId; RETURN; END

        UPDATE seg.Usuarios
        SET Email = NULLIF(LOWER(LTRIM(RTRIM(@Email))), ''), Nombres = LTRIM(RTRIM(@Nombres)), Apellidos = LTRIM(RTRIM(@Apellidos)),
            Activo = @Activo, FechaModificacionUtc = SYSUTCDATETIME()
        WHERE UsuarioId = @UsuarioId;

        DECLARE @Det NVARCHAR(200) = CASE WHEN @ActivoActual <> @Activo THEN CONCAT(N'Activo=', @Activo) END;
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Usuario actualizado.', @UsuarioId;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, 'Usuario', @Ip, @UserAgent, @CorrelationId;
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminUsuarioAsignarRol
    @ActorUsuarioId INT,
    @UsuarioId      INT,
    @RolCodigo      VARCHAR(30),
    @Asignar        BIT,
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = CASE WHEN @Asignar = 1 THEN 'ROL_ASIGNADO' ELSE 'ROL_REVOCADO' END, @RolId TINYINT, @Det NVARCHAR(200);

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'usuarios.roles') = 0
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'SIN_PERMISO', N'No tienes permiso.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    SELECT @RolId = RolId FROM seg.Roles WHERE Codigo = @RolCodigo;
    IF @RolId IS NULL OR NOT EXISTS (SELECT 1 FROM seg.Usuarios WHERE UsuarioId = @UsuarioId)
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'NO_ENCONTRADO', N'Usuario o rol inexistente.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    IF (@RolCodigo IN ('SUPERADMIN', 'ADMINISTRADOR') OR seg.fn_TieneRol(@UsuarioId, 'SUPERADMIN') = 1) AND seg.fn_TieneRol(@ActorUsuarioId, 'SUPERADMIN') = 0
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'SIN_PERMISO', N'Solo un superadministrador puede gestionar roles administrativos.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    IF @Asignar = 0 AND @UsuarioId = @ActorUsuarioId AND @RolCodigo IN ('SUPERADMIN', 'ADMINISTRADOR')
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'OPERACION_NO_PERMITIDA', N'No puedes revocarte tu propio rol administrativo.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    IF @Asignar = 1 AND @RolCodigo = 'MEDICO' AND NOT EXISTS (SELECT 1 FROM clin.Medicos WHERE UsuarioId = @UsuarioId)
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'MEDICO_REQUERIDO', N'Vincula primero la cuenta a un médico.', @Ip, @UserAgent, @CorrelationId; RETURN; END
    IF @Asignar = 1 AND @RolCodigo = 'PACIENTE' AND NOT EXISTS (SELECT 1 FROM clin.Pacientes WHERE UsuarioId = @UsuarioId)
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'PACIENTE_REQUERIDO', N'Vincula primero la cuenta a un paciente.', @Ip, @UserAgent, @CorrelationId; RETURN; END

    BEGIN TRY
        BEGIN TRAN;
        EXEC seg.usp_ObtenerLock N'Seguridad:Roles';
        IF @Asignar = 1
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM seg.UsuarioRol WHERE UsuarioId = @UsuarioId AND RolId = @RolId)
                INSERT INTO seg.UsuarioRol (UsuarioId, RolId, AsignadoPorUsuarioId) VALUES (@UsuarioId, @RolId, @ActorUsuarioId);
        END
        ELSE
        BEGIN
            IF @RolCodigo = 'SUPERADMIN' AND
               (SELECT COUNT(*) FROM seg.UsuarioRol ur JOIN seg.Usuarios u ON u.UsuarioId = ur.UsuarioId
                WHERE ur.RolId = 1 AND u.Activo = 1 AND u.UsuarioId <> @UsuarioId) = 0
            BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'OPERACION_NO_PERMITIDA', N'Debe existir al menos un superadministrador activo.', @Ip, @UserAgent, @CorrelationId; RETURN; END
            DELETE FROM seg.UsuarioRol WHERE UsuarioId = @UsuarioId AND RolId = @RolId;
        END

        SET @Det = CONCAT(N'Rol=', @RolCodigo);
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Roles actualizados.', @UsuarioId;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, 'Usuario', @Ip, @UserAgent, @CorrelationId;
    END CATCH
END
GO

-- Vincula una cuenta existente con un médico y/o paciente (flujo admin y clinic:seed-demo).
CREATE OR ALTER PROCEDURE api.usp_AdminUsuarioVincularPerfil
    @ActorUsuarioId INT,
    @UsuarioId      INT,
    @MedicoId       INT           = NULL,
    @PacienteId     INT           = NULL,
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = 'USUARIO_VINCULADO', @Det NVARCHAR(200);

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'usuarios.editar') = 0
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'SIN_PERMISO', N'No tienes permiso.', @Ip, @UserAgent, @CorrelationId; RETURN; END

    BEGIN TRY
        BEGIN TRAN;
        IF NOT EXISTS (SELECT 1 FROM seg.Usuarios WHERE UsuarioId = @UsuarioId)
        BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 'NO_ENCONTRADO', N'El usuario no existe.', @Ip, @UserAgent, @CorrelationId; RETURN; END
        IF @MedicoId IS NOT NULL
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM clin.Medicos WITH (UPDLOCK) WHERE MedicoId = @MedicoId AND (UsuarioId IS NULL OR UsuarioId = @UsuarioId))
            BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Medico', @MedicoId, 'MEDICO_YA_VINCULADO', N'El médico no existe o ya tiene otra cuenta.', @Ip, @UserAgent, @CorrelationId; RETURN; END
            IF EXISTS (SELECT 1 FROM clin.Medicos WHERE UsuarioId = @UsuarioId AND MedicoId <> @MedicoId)
            BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Medico', @MedicoId, 'USUARIO_YA_VINCULADO', N'La cuenta ya está vinculada a otro médico.', @Ip, @UserAgent, @CorrelationId; RETURN; END
            UPDATE clin.Medicos SET UsuarioId = @UsuarioId, FechaModificacionUtc = SYSUTCDATETIME() WHERE MedicoId = @MedicoId AND UsuarioId IS NULL;
        END
        IF @PacienteId IS NOT NULL
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM clin.Pacientes WITH (UPDLOCK) WHERE PacienteId = @PacienteId AND (UsuarioId IS NULL OR UsuarioId = @UsuarioId))
            BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Paciente', @PacienteId, 'PACIENTE_YA_VINCULADO', N'El paciente no existe o ya tiene otra cuenta.', @Ip, @UserAgent, @CorrelationId; RETURN; END
            IF EXISTS (SELECT 1 FROM clin.Pacientes WHERE UsuarioId = @UsuarioId AND PacienteId <> @PacienteId)
            BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Paciente', @PacienteId, 'USUARIO_YA_VINCULADO', N'La cuenta ya está vinculada a otro paciente.', @Ip, @UserAgent, @CorrelationId; RETURN; END
            UPDATE clin.Pacientes SET UsuarioId = @UsuarioId, FechaModificacionUtc = SYSUTCDATETIME() WHERE PacienteId = @PacienteId AND UsuarioId IS NULL;
        END
        SET @Det = CONCAT(N'MedicoId=', @MedicoId, N'; PacienteId=', @PacienteId);
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, 'Usuario', @UsuarioId, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Cuenta vinculada.', @UsuarioId;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, 'Usuario', @Ip, @UserAgent, @CorrelationId;
    END CATCH
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminConfiguracionActualizar
    @ActorUsuarioId INT,
    @Clave          VARCHAR(60),
    @Valor          NVARCHAR(200),
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = 'CONFIGURACION_ACTUALIZADA', @Tipo VARCHAR(10), @Min INT, @Max INT, @Anterior NVARCHAR(200), @Num INT, @Det NVARCHAR(400);

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'configuracion.gestionar') = 0
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Configuracion', NULL, 'SIN_PERMISO', N'No tienes permiso.', @Ip, @UserAgent, @CorrelationId; RETURN; END

    SELECT @Tipo = TipoDato, @Min = ValorMinimo, @Max = ValorMaximo, @Anterior = Valor FROM seg.ConfiguracionSistema WHERE Clave = @Clave;
    IF @Tipo IS NULL
    BEGIN IF @@TRANCOUNT > 0 ROLLBACK; EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Configuracion', NULL, 'NO_ENCONTRADO', N'Parámetro inexistente.', @Ip, @UserAgent, @CorrelationId; RETURN; END

    SET @Num = TRY_CONVERT(INT, @Valor);
    IF (@Tipo = 'INT' AND (@Num IS NULL OR (@Min IS NOT NULL AND @Num < @Min) OR (@Max IS NOT NULL AND @Num > @Max)))
       OR (@Tipo = 'BIT' AND @Valor NOT IN (N'0', N'1'))
    BEGIN
        SET @Det = CONCAT(N'Valor fuera de rango permitido (', @Min, N' - ', @Max, N').');
        EXEC seg.usp_Rechazo @ActorUsuarioId, @A, 'Configuracion', NULL, 'VALOR_INVALIDO', @Det, @Ip, @UserAgent, @CorrelationId; RETURN;
    END

    UPDATE seg.ConfiguracionSistema
    SET Valor = CASE WHEN @Tipo = 'INT' THEN CONVERT(NVARCHAR(200), @Num) ELSE @Valor END,
        FechaModificacionUtc = SYSUTCDATETIME(), ModificadoPorUsuarioId = @ActorUsuarioId
    WHERE Clave = @Clave;

    SET @Det = CONCAT(@Clave, N': ', @Anterior, N' -> ', @Valor);
    EXEC audit.usp_Registrar @ActorUsuarioId, @A, 'Configuracion', NULL, 1, @Det, @Ip, @UserAgent, @CorrelationId;
    EXEC seg.usp_Resultado 1, @A, N'Configuración actualizada.', NULL;
END
GO

/* ============================================================================
   13. api — PACIENTES
   Patrón de comandos: validaciones -> TRY/TRAN -> bitácora -> COMMIT -> fila estándar.
   Los rechazos saltan a la etiqueta Rechazo (ROLLBACK + bitácora + fila estándar).
   ============================================================================ */

-- Validación de datos de paciente reutilizable (sin efectos). Devuelve código y mensaje por OUTPUT.
CREATE OR ALTER PROCEDURE clin.usp_PacienteValidarDatos
    @TipoDocumento   VARCHAR(10),
    @NumeroDocumento VARCHAR(20),
    @Nombres         NVARCHAR(80),
    @Apellidos       NVARCHAR(100),
    @FechaNacimiento DATE,
    @Sexo            CHAR(1),
    @Email           VARCHAR(150),
    @Codigo          VARCHAR(50)   OUTPUT,
    @Mensaje         NVARCHAR(400) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT @Codigo = NULL, @Mensaje = NULL;
    DECLARE @Hoy DATE = CAST(clin.fn_AhoraLocal() AS DATE);

    IF @TipoDocumento NOT IN ('DNI', 'CE', 'PASAPORTE')
        SELECT @Codigo = 'DOCUMENTO_INVALIDO', @Mensaje = N'Tipo de documento no válido.';
    ELSE IF @TipoDocumento = 'DNI' AND (LEN(@NumeroDocumento) <> 8 OR @NumeroDocumento LIKE '%[^0-9]%')
        SELECT @Codigo = 'DOCUMENTO_INVALIDO', @Mensaje = N'El DNI debe tener 8 dígitos.';
    ELSE IF @TipoDocumento IN ('CE', 'PASAPORTE') AND (LEN(@NumeroDocumento) NOT BETWEEN 6 AND 12 OR @NumeroDocumento LIKE '%[^0-9A-Z]%')
        SELECT @Codigo = 'DOCUMENTO_INVALIDO', @Mensaje = N'El documento debe tener entre 6 y 12 caracteres alfanuméricos.';
    ELSE IF LEN(LTRIM(ISNULL(@Nombres, N''))) < 2 OR LEN(LTRIM(ISNULL(@Apellidos, N''))) < 2
        SELECT @Codigo = 'DATOS_INVALIDOS', @Mensaje = N'Nombres y apellidos son obligatorios.';
    ELSE IF @FechaNacimiento IS NULL OR @FechaNacimiento > @Hoy OR @FechaNacimiento < '1900-01-01'
        SELECT @Codigo = 'FECHA_NACIMIENTO_INVALIDA', @Mensaje = N'La fecha de nacimiento no es válida.';
    ELSE IF @Sexo IS NOT NULL AND @Sexo NOT IN ('F', 'M', 'X')
        SELECT @Codigo = 'DATOS_INVALIDOS', @Mensaje = N'Sexo no válido.';
    ELSE IF NULLIF(@Email, '') IS NOT NULL AND @Email NOT LIKE '%_@_%._%'
        SELECT @Codigo = 'EMAIL_INVALIDO', @Mensaje = N'El correo no tiene un formato válido.';
END
GO

CREATE OR ALTER PROCEDURE api.usp_PacienteObtener
    @ActorUsuarioId INT,
    @PacienteId     INT = NULL     -- NULL = paciente vinculado al actor
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @Propio INT = (SELECT PacienteId FROM clin.Pacientes WHERE UsuarioId = @ActorUsuarioId);
    SET @PacienteId = ISNULL(@PacienteId, @Propio);

    IF @PacienteId IS NULL OR (ISNULL(@Propio, 0) <> @PacienteId AND seg.fn_TienePermiso(@ActorUsuarioId, 'pacientes.ver') = 0)
        THROW 50403, N'SIN_PERMISO', 1;

    SELECT PacienteId, UsuarioId, TipoDocumento, NumeroDocumento, Nombres, Apellidos, NombreCompleto, FechaNacimiento,
           Sexo, Telefono, Email, Direccion, ContactoEmergencia, Activo, FechaCreacionUtc, VersionFila, NombreUsuario,
           ReservasConfirmadas
    FROM api.vw_PacientesResumen
    WHERE PacienteId = @PacienteId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_PacienteBuscar
    @ActorUsuarioId INT,
    @Texto          NVARCHAR(100) = NULL,
    @Activo         BIT           = NULL,
    @Pagina         INT           = 1,
    @TamanoPagina   INT           = 20
AS
BEGIN
    SET NOCOUNT ON;
    EXEC seg.usp_ExigirPermiso @ActorUsuarioId, 'pacientes.ver';

    SET @Pagina = CASE WHEN @Pagina < 1 THEN 1 ELSE @Pagina END;
    SET @TamanoPagina = CASE WHEN @TamanoPagina BETWEEN 1 AND 100 THEN @TamanoPagina ELSE 20 END;
    SET @Texto = NULLIF(LTRIM(RTRIM(@Texto)), N'');

    SELECT PacienteId, UsuarioId, TipoDocumento, NumeroDocumento, Nombres, Apellidos, NombreCompleto, FechaNacimiento,
           Telefono, Email, Activo, VersionFila, NombreUsuario, ReservasConfirmadas,
           COUNT(*) OVER () AS TotalFilas
    FROM api.vw_PacientesResumen
    WHERE (@Texto IS NULL OR NumeroDocumento LIKE @Texto + N'%' OR Apellidos LIKE N'%' + @Texto + N'%'
           OR Nombres LIKE N'%' + @Texto + N'%' OR Email LIKE N'%' + @Texto + N'%')
      AND (@Activo IS NULL OR Activo = @Activo)
    ORDER BY Apellidos, Nombres, PacienteId
    OFFSET (@Pagina - 1) * @TamanoPagina ROWS FETCH NEXT @TamanoPagina ROWS ONLY;
END
GO

-- El paciente solo edita sus datos de contacto; identidad y documento los corrige recepción.
CREATE OR ALTER PROCEDURE api.usp_PacienteActualizarPerfil
    @ActorUsuarioId     INT,
    @Telefono           VARCHAR(20)   = NULL,
    @Email              VARCHAR(150)  = NULL,
    @Direccion          NVARCHAR(200) = NULL,
    @ContactoEmergencia NVARCHAR(150) = NULL,
    @VersionFila        VARCHAR(18),
    @Ip                 VARCHAR(45)   = NULL,
    @UserAgent          NVARCHAR(300) = NULL,
    @CorrelationId      VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = 'PACIENTE_PERFIL_ACTUALIZADO', @E VARCHAR(50) = 'Paciente', @Cod VARCHAR(50), @Msg NVARCHAR(400),
            @Id BIGINT, @Actual BINARY(8), @Version BINARY(8) = TRY_CONVERT(BINARY(8), @VersionFila, 1);

    IF NULLIF(@Email, '') IS NOT NULL AND @Email NOT LIKE '%_@_%._%'
    BEGIN SELECT @Cod = 'EMAIL_INVALIDO', @Msg = N'El correo no tiene un formato válido.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        SELECT @Id = PacienteId, @Actual = VersionFila FROM clin.Pacientes WITH (UPDLOCK, HOLDLOCK) WHERE UsuarioId = @ActorUsuarioId AND Activo = 1;
        IF @Id IS NULL
        BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'Tu cuenta no está vinculada a un paciente activo.'; GOTO Rechazo; END
        IF @Version IS NULL OR @Version <> @Actual
        BEGIN SELECT @Cod = 'CONFLICTO_EDICION', @Msg = N'El registro fue modificado por otro usuario. Recarga la página.'; GOTO Rechazo; END

        UPDATE clin.Pacientes
        SET Telefono = NULLIF(LTRIM(RTRIM(@Telefono)), ''), Email = NULLIF(LOWER(LTRIM(RTRIM(@Email))), ''),
            Direccion = NULLIF(LTRIM(RTRIM(@Direccion)), N''), ContactoEmergencia = NULLIF(LTRIM(RTRIM(@ContactoEmergencia)), N''),
            FechaModificacionUtc = SYSUTCDATETIME()
        WHERE PacienteId = @Id;

        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, NULL, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Perfil actualizado.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_RecepcionPacienteCrear
    @ActorUsuarioId     INT,
    @TipoDocumento      VARCHAR(10),
    @NumeroDocumento    VARCHAR(20),
    @Nombres            NVARCHAR(80),
    @Apellidos          NVARCHAR(100),
    @FechaNacimiento    DATE,
    @Sexo               CHAR(1)       = NULL,
    @Telefono           VARCHAR(20)   = NULL,
    @Email              VARCHAR(150)  = NULL,
    @Direccion          NVARCHAR(200) = NULL,
    @ContactoEmergencia NVARCHAR(150) = NULL,
    @Ip                 VARCHAR(45)   = NULL,
    @UserAgent          NVARCHAR(300) = NULL,
    @CorrelationId      VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = 'PACIENTE_CREADO', @E VARCHAR(50) = 'Paciente', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT, @Det NVARCHAR(200);

    SELECT @NumeroDocumento = UPPER(LTRIM(RTRIM(@NumeroDocumento))), @TipoDocumento = UPPER(LTRIM(RTRIM(@TipoDocumento))),
           @Sexo = NULLIF(UPPER(@Sexo), '');

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'pacientes.crear') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END

    EXEC clin.usp_PacienteValidarDatos @TipoDocumento, @NumeroDocumento, @Nombres, @Apellidos, @FechaNacimiento, @Sexo, @Email, @Cod OUTPUT, @Msg OUTPUT;
    IF @Cod IS NOT NULL GOTO Rechazo;

    BEGIN TRY
        BEGIN TRAN;
        IF EXISTS (SELECT 1 FROM clin.Pacientes WITH (UPDLOCK, HOLDLOCK) WHERE TipoDocumento = @TipoDocumento AND NumeroDocumento = @NumeroDocumento)
        BEGIN SELECT @Cod = 'DOCUMENTO_DUPLICADO', @Msg = N'Ya existe un paciente con ese documento.'; GOTO Rechazo; END

        INSERT INTO clin.Pacientes (TipoDocumento, NumeroDocumento, Nombres, Apellidos, FechaNacimiento, Sexo, Telefono, Email,
                                    Direccion, ContactoEmergencia, CreadoPorUsuarioId)
        VALUES (@TipoDocumento, @NumeroDocumento, LTRIM(RTRIM(@Nombres)), LTRIM(RTRIM(@Apellidos)), @FechaNacimiento, @Sexo,
                NULLIF(LTRIM(RTRIM(@Telefono)), ''), NULLIF(LOWER(LTRIM(RTRIM(@Email))), ''), NULLIF(LTRIM(RTRIM(@Direccion)), N''),
                NULLIF(LTRIM(RTRIM(@ContactoEmergencia)), N''), @ActorUsuarioId);
        SET @Id = SCOPE_IDENTITY();

        SET @Det = CONCAT(@TipoDocumento, N' ', @NumeroDocumento);
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Paciente registrado correctamente.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_RecepcionPacienteActualizar
    @ActorUsuarioId     INT,
    @PacienteId         INT,
    @TipoDocumento      VARCHAR(10),
    @NumeroDocumento    VARCHAR(20),
    @Nombres            NVARCHAR(80),
    @Apellidos          NVARCHAR(100),
    @FechaNacimiento    DATE,
    @Sexo               CHAR(1)       = NULL,
    @Telefono           VARCHAR(20)   = NULL,
    @Email              VARCHAR(150)  = NULL,
    @Direccion          NVARCHAR(200) = NULL,
    @ContactoEmergencia NVARCHAR(150) = NULL,
    @Activo             BIT           = 1,
    @VersionFila        VARCHAR(18),
    @Ip                 VARCHAR(45)   = NULL,
    @UserAgent          NVARCHAR(300) = NULL,
    @CorrelationId      VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = 'PACIENTE_ACTUALIZADO', @E VARCHAR(50) = 'Paciente', @Cod VARCHAR(50), @Msg NVARCHAR(400),
            @Id BIGINT = @PacienteId, @Actual BINARY(8), @ActivoActual BIT, @Version BINARY(8) = TRY_CONVERT(BINARY(8), @VersionFila, 1),
            @Det NVARCHAR(200);

    SELECT @NumeroDocumento = UPPER(LTRIM(RTRIM(@NumeroDocumento))), @TipoDocumento = UPPER(LTRIM(RTRIM(@TipoDocumento))),
           @Sexo = NULLIF(UPPER(@Sexo), '');

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'pacientes.editar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END

    EXEC clin.usp_PacienteValidarDatos @TipoDocumento, @NumeroDocumento, @Nombres, @Apellidos, @FechaNacimiento, @Sexo, @Email, @Cod OUTPUT, @Msg OUTPUT;
    IF @Cod IS NOT NULL GOTO Rechazo;

    BEGIN TRY
        BEGIN TRAN;
        SELECT @Actual = VersionFila, @ActivoActual = Activo FROM clin.Pacientes WITH (UPDLOCK, HOLDLOCK) WHERE PacienteId = @PacienteId;
        IF @Actual IS NULL
        BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'El paciente no existe.'; GOTO Rechazo; END
        IF @Version IS NULL OR @Version <> @Actual
        BEGIN SELECT @Cod = 'CONFLICTO_EDICION', @Msg = N'El registro fue modificado por otro usuario. Recarga la página.'; GOTO Rechazo; END
        IF EXISTS (SELECT 1 FROM clin.Pacientes WITH (UPDLOCK, HOLDLOCK) WHERE TipoDocumento = @TipoDocumento AND NumeroDocumento = @NumeroDocumento AND PacienteId <> @PacienteId)
        BEGIN SELECT @Cod = 'DOCUMENTO_DUPLICADO', @Msg = N'Ya existe un paciente con ese documento.'; GOTO Rechazo; END
        IF @Activo = 0 AND @ActivoActual = 1 AND EXISTS
           (SELECT 1 FROM clin.Reservas WHERE PacienteId = @PacienteId AND EstadoReservaId = 1 AND FechaCita >= CAST(clin.fn_AhoraLocal() AS DATE))
        BEGIN SELECT @Cod = 'RESERVAS_FUTURAS', @Msg = N'El paciente tiene reservas confirmadas pendientes. Cancélalas antes de desactivarlo.'; GOTO Rechazo; END

        UPDATE clin.Pacientes
        SET TipoDocumento = @TipoDocumento, NumeroDocumento = @NumeroDocumento, Nombres = LTRIM(RTRIM(@Nombres)),
            Apellidos = LTRIM(RTRIM(@Apellidos)), FechaNacimiento = @FechaNacimiento, Sexo = @Sexo,
            Telefono = NULLIF(LTRIM(RTRIM(@Telefono)), ''), Email = NULLIF(LOWER(LTRIM(RTRIM(@Email))), ''),
            Direccion = NULLIF(LTRIM(RTRIM(@Direccion)), N''), ContactoEmergencia = NULLIF(LTRIM(RTRIM(@ContactoEmergencia)), N''),
            Activo = @Activo, FechaModificacionUtc = SYSUTCDATETIME()
        WHERE PacienteId = @PacienteId;

        SET @Det = CASE WHEN @ActivoActual <> @Activo THEN CONCAT(N'Activo=', @Activo) END;
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Paciente actualizado.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

/* ============================================================================
   14. api — MÉDICOS
   ============================================================================ */
CREATE OR ALTER PROCEDURE api.usp_MedicosBuscar
    @ActorUsuarioId INT,
    @Texto          NVARCHAR(100) = NULL,
    @EspecialidadId INT           = NULL,
    @SedeId         INT           = NULL,
    @Activo         BIT           = NULL,
    @Pagina         INT           = 1,
    @TamanoPagina   INT           = 20
AS
BEGIN
    SET NOCOUNT ON;
    EXEC seg.usp_ExigirPermiso @ActorUsuarioId, 'medicos.ver';

    -- Quien no administra médicos solo ve médicos activos.
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'medicos.editar') = 0 SET @Activo = 1;
    SET @Pagina = CASE WHEN @Pagina < 1 THEN 1 ELSE @Pagina END;
    SET @TamanoPagina = CASE WHEN @TamanoPagina BETWEEN 1 AND 100 THEN @TamanoPagina ELSE 20 END;
    SET @Texto = NULLIF(LTRIM(RTRIM(@Texto)), N'');

    DECLARE @Hoy DATE = CAST(clin.fn_AhoraLocal() AS DATE);

    SELECT v.MedicoId, v.UsuarioId, v.CMP, v.Nombres, v.Apellidos, v.NombreCompleto, v.Telefono, v.Email, v.Activo, v.VersionFila,
           v.EspecialidadPrincipalId, v.EspecialidadPrincipal, v.Especialidades, v.EspecialidadIds, v.Sedes, v.SedeIds,
           (SELECT MIN(a.Fecha) FROM clin.AgendasMedicas a WHERE a.MedicoId = v.MedicoId AND a.Activo = 1 AND a.Fecha >= @Hoy) AS ProximaAgenda,
           COUNT(*) OVER () AS TotalFilas
    FROM api.vw_MedicosCatalogo v
    WHERE (@Texto IS NULL OR v.Apellidos LIKE N'%' + @Texto + N'%' OR v.Nombres LIKE N'%' + @Texto + N'%' OR v.CMP LIKE @Texto + N'%')
      AND (@Activo IS NULL OR v.Activo = @Activo)
      AND (@EspecialidadId IS NULL OR EXISTS (SELECT 1 FROM clin.MedicoEspecialidad me WHERE me.MedicoId = v.MedicoId AND me.EspecialidadId = @EspecialidadId AND me.Activo = 1))
      AND (@SedeId IS NULL OR EXISTS (SELECT 1 FROM clin.MedicoSede ms WHERE ms.MedicoId = v.MedicoId AND ms.SedeId = @SedeId AND ms.Activo = 1))
    ORDER BY v.Apellidos, v.Nombres, v.MedicoId
    OFFSET (@Pagina - 1) * @TamanoPagina ROWS FETCH NEXT @TamanoPagina ROWS ONLY;
END
GO

-- Tres result sets: médico, especialidades asignadas, sedes asignadas.
CREATE OR ALTER PROCEDURE api.usp_MedicoObtener
    @ActorUsuarioId INT,
    @MedicoId       INT = NULL     -- NULL = médico vinculado al actor
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @Propio INT = (SELECT MedicoId FROM clin.Medicos WHERE UsuarioId = @ActorUsuarioId);
    SET @MedicoId = ISNULL(@MedicoId, @Propio);

    IF @MedicoId IS NULL OR (ISNULL(@Propio, 0) <> @MedicoId AND seg.fn_TienePermiso(@ActorUsuarioId, 'medicos.ver') = 0)
        THROW 50403, N'SIN_PERMISO', 1;
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'medicos.editar') = 0 AND ISNULL(@Propio, 0) <> @MedicoId
       AND NOT EXISTS (SELECT 1 FROM clin.Medicos WHERE MedicoId = @MedicoId AND Activo = 1)
        SET @MedicoId = -1;

    SELECT MedicoId, UsuarioId, CMP, Nombres, Apellidos, NombreCompleto, Telefono, Email, Activo, VersionFila,
           EspecialidadPrincipalId, EspecialidadPrincipal, Especialidades, EspecialidadIds, Sedes, SedeIds
    FROM api.vw_MedicosCatalogo WHERE MedicoId = @MedicoId;

    SELECT me.EspecialidadId, e.Nombre, me.EsPrincipal, e.Activo AS EspecialidadActiva
    FROM clin.MedicoEspecialidad me JOIN clin.Especialidades e ON e.EspecialidadId = me.EspecialidadId
    WHERE me.MedicoId = @MedicoId AND me.Activo = 1
    ORDER BY me.EsPrincipal DESC, e.Nombre;

    SELECT ms.SedeId, s.Nombre, s.Direccion, s.Activo AS SedeActiva
    FROM clin.MedicoSede ms JOIN clin.Sedes s ON s.SedeId = ms.SedeId
    WHERE ms.MedicoId = @MedicoId AND ms.Activo = 1
    ORDER BY s.Nombre;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminMedicoCrear
    @ActorUsuarioId INT,
    @CMP            VARCHAR(20),
    @Nombres        NVARCHAR(80),
    @Apellidos      NVARCHAR(100),
    @Telefono       VARCHAR(20)   = NULL,
    @Email          VARCHAR(150)  = NULL,
    @EspecialidadId INT,
    @SedeId         INT,
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = 'MEDICO_CREADO', @E VARCHAR(50) = 'Medico', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT, @Det NVARCHAR(200);
    SET @CMP = UPPER(LTRIM(RTRIM(@CMP)));

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'medicos.crear') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END
    IF LEN(@CMP) < 3 OR @CMP LIKE '%[^0-9A-Z-]%'
    BEGIN SELECT @Cod = 'CMP_INVALIDO', @Msg = N'El CMP debe contener solo dígitos, letras o guiones.'; GOTO Rechazo; END
    IF LEN(LTRIM(ISNULL(@Nombres, N''))) < 2 OR LEN(LTRIM(ISNULL(@Apellidos, N''))) < 2
    BEGIN SELECT @Cod = 'DATOS_INVALIDOS', @Msg = N'Nombres y apellidos son obligatorios.'; GOTO Rechazo; END
    IF NULLIF(@Email, '') IS NOT NULL AND @Email NOT LIKE '%_@_%._%'
    BEGIN SELECT @Cod = 'EMAIL_INVALIDO', @Msg = N'El correo no tiene un formato válido.'; GOTO Rechazo; END
    IF NOT EXISTS (SELECT 1 FROM clin.Especialidades WHERE EspecialidadId = @EspecialidadId AND Activo = 1)
    BEGIN SELECT @Cod = 'ESPECIALIDAD_INACTIVA', @Msg = N'La especialidad no existe o está inactiva.'; GOTO Rechazo; END
    IF NOT EXISTS (SELECT 1 FROM clin.Sedes WHERE SedeId = @SedeId AND Activo = 1)
    BEGIN SELECT @Cod = 'SEDE_INACTIVA', @Msg = N'La sede no existe o está inactiva.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        IF EXISTS (SELECT 1 FROM clin.Medicos WITH (UPDLOCK, HOLDLOCK) WHERE CMP = @CMP)
        BEGIN SELECT @Cod = 'CMP_DUPLICADO', @Msg = N'Ya existe un médico con ese CMP.'; GOTO Rechazo; END

        INSERT INTO clin.Medicos (CMP, Nombres, Apellidos, Telefono, Email)
        VALUES (@CMP, LTRIM(RTRIM(@Nombres)), LTRIM(RTRIM(@Apellidos)), NULLIF(LTRIM(RTRIM(@Telefono)), ''), NULLIF(LOWER(LTRIM(RTRIM(@Email))), ''));
        SET @Id = SCOPE_IDENTITY();
        INSERT INTO clin.MedicoEspecialidad (MedicoId, EspecialidadId, EsPrincipal) VALUES (@Id, @EspecialidadId, 1);
        INSERT INTO clin.MedicoSede (MedicoId, SedeId) VALUES (@Id, @SedeId);

        SET @Det = CONCAT(N'CMP ', @CMP);
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Médico registrado correctamente.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminMedicoActualizar
    @ActorUsuarioId INT,
    @MedicoId       INT,
    @CMP            VARCHAR(20),
    @Nombres        NVARCHAR(80),
    @Apellidos      NVARCHAR(100),
    @Telefono       VARCHAR(20)   = NULL,
    @Email          VARCHAR(150)  = NULL,
    @VersionFila    VARCHAR(18),
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = 'MEDICO_ACTUALIZADO', @E VARCHAR(50) = 'Medico', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @MedicoId,
            @Actual BINARY(8), @Version BINARY(8) = TRY_CONVERT(BINARY(8), @VersionFila, 1);
    SET @CMP = UPPER(LTRIM(RTRIM(@CMP)));

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'medicos.editar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END
    IF LEN(@CMP) < 3 OR @CMP LIKE '%[^0-9A-Z-]%'
    BEGIN SELECT @Cod = 'CMP_INVALIDO', @Msg = N'El CMP debe contener solo dígitos, letras o guiones.'; GOTO Rechazo; END
    IF LEN(LTRIM(ISNULL(@Nombres, N''))) < 2 OR LEN(LTRIM(ISNULL(@Apellidos, N''))) < 2
    BEGIN SELECT @Cod = 'DATOS_INVALIDOS', @Msg = N'Nombres y apellidos son obligatorios.'; GOTO Rechazo; END
    IF NULLIF(@Email, '') IS NOT NULL AND @Email NOT LIKE '%_@_%._%'
    BEGIN SELECT @Cod = 'EMAIL_INVALIDO', @Msg = N'El correo no tiene un formato válido.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        SELECT @Actual = VersionFila FROM clin.Medicos WITH (UPDLOCK, HOLDLOCK) WHERE MedicoId = @MedicoId;
        IF @Actual IS NULL
        BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'El médico no existe.'; GOTO Rechazo; END
        IF @Version IS NULL OR @Version <> @Actual
        BEGIN SELECT @Cod = 'CONFLICTO_EDICION', @Msg = N'El registro fue modificado por otro usuario. Recarga la página.'; GOTO Rechazo; END
        IF EXISTS (SELECT 1 FROM clin.Medicos WITH (UPDLOCK, HOLDLOCK) WHERE CMP = @CMP AND MedicoId <> @MedicoId)
        BEGIN SELECT @Cod = 'CMP_DUPLICADO', @Msg = N'Ya existe un médico con ese CMP.'; GOTO Rechazo; END

        UPDATE clin.Medicos
        SET CMP = @CMP, Nombres = LTRIM(RTRIM(@Nombres)), Apellidos = LTRIM(RTRIM(@Apellidos)),
            Telefono = NULLIF(LTRIM(RTRIM(@Telefono)), ''), Email = NULLIF(LOWER(LTRIM(RTRIM(@Email))), ''),
            FechaModificacionUtc = SYSUTCDATETIME()
        WHERE MedicoId = @MedicoId;

        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, NULL, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Médico actualizado.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

-- @Asignar=1 agrega (o reactiva) la especialidad; @EsPrincipal=1 la marca como principal.
-- @Asignar=0 la retira (baja lógica: las agendas históricas conservan su FK).
CREATE OR ALTER PROCEDURE api.usp_AdminMedicoAsignarEspecialidad
    @ActorUsuarioId INT,
    @MedicoId       INT,
    @EspecialidadId INT,
    @Asignar        BIT = 1,
    @EsPrincipal    BIT = 0,
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = CASE WHEN @Asignar = 1 THEN 'MEDICO_ESPECIALIDAD_ASIGNADA' ELSE 'MEDICO_ESPECIALIDAD_RETIRADA' END,
            @E VARCHAR(50) = 'Medico', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @MedicoId, @Det NVARCHAR(200),
            @EraPrincipal BIT, @Hoy DATE = CAST(clin.fn_AhoraLocal() AS DATE);

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'medicos.editar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        EXEC seg.usp_ObtenerLock @Recurso = N'Medico:Asignaciones';
        IF NOT EXISTS (SELECT 1 FROM clin.Medicos WHERE MedicoId = @MedicoId)
        BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'El médico no existe.'; GOTO Rechazo; END

        IF @Asignar = 1
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM clin.Especialidades WHERE EspecialidadId = @EspecialidadId AND Activo = 1)
            BEGIN SELECT @Cod = 'ESPECIALIDAD_INACTIVA', @Msg = N'La especialidad no existe o está inactiva.'; GOTO Rechazo; END

            IF @EsPrincipal = 1
                UPDATE clin.MedicoEspecialidad SET EsPrincipal = 0 WHERE MedicoId = @MedicoId AND EsPrincipal = 1 AND EspecialidadId <> @EspecialidadId;

            IF EXISTS (SELECT 1 FROM clin.MedicoEspecialidad WHERE MedicoId = @MedicoId AND EspecialidadId = @EspecialidadId)
                UPDATE clin.MedicoEspecialidad
                SET Activo = 1, EsPrincipal = CASE WHEN @EsPrincipal = 1 THEN 1 ELSE EsPrincipal END
                WHERE MedicoId = @MedicoId AND EspecialidadId = @EspecialidadId;
            ELSE
                INSERT INTO clin.MedicoEspecialidad (MedicoId, EspecialidadId, EsPrincipal) VALUES (@MedicoId, @EspecialidadId, @EsPrincipal);

            -- Siempre debe existir una principal activa.
            IF NOT EXISTS (SELECT 1 FROM clin.MedicoEspecialidad WHERE MedicoId = @MedicoId AND EsPrincipal = 1 AND Activo = 1)
                UPDATE clin.MedicoEspecialidad SET EsPrincipal = 1 WHERE MedicoId = @MedicoId AND EspecialidadId = @EspecialidadId;
        END
        ELSE
        BEGIN
            SELECT @EraPrincipal = EsPrincipal FROM clin.MedicoEspecialidad WHERE MedicoId = @MedicoId AND EspecialidadId = @EspecialidadId AND Activo = 1;
            IF @EraPrincipal IS NULL
            BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'El médico no tiene esa especialidad.'; GOTO Rechazo; END
            IF (SELECT COUNT(*) FROM clin.MedicoEspecialidad WHERE MedicoId = @MedicoId AND Activo = 1) = 1
            BEGIN SELECT @Cod = 'ULTIMA_ESPECIALIDAD', @Msg = N'El médico debe conservar al menos una especialidad.'; GOTO Rechazo; END
            IF EXISTS (SELECT 1 FROM clin.AgendasMedicas WHERE MedicoId = @MedicoId AND EspecialidadId = @EspecialidadId AND Activo = 1 AND Fecha >= @Hoy)
            BEGIN SELECT @Cod = 'AGENDAS_FUTURAS', @Msg = N'Existen agendas activas futuras con esta especialidad. Desactívalas primero.'; GOTO Rechazo; END

            UPDATE clin.MedicoEspecialidad SET Activo = 0, EsPrincipal = 0 WHERE MedicoId = @MedicoId AND EspecialidadId = @EspecialidadId;
            IF @EraPrincipal = 1
                UPDATE TOP (1) clin.MedicoEspecialidad SET EsPrincipal = 1 WHERE MedicoId = @MedicoId AND Activo = 1;
        END

        SET @Det = CONCAT(N'EspecialidadId=', @EspecialidadId, CASE WHEN @Asignar = 1 AND @EsPrincipal = 1 THEN N' (principal)' ELSE N'' END);
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Especialidades actualizadas.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminMedicoAsignarSede
    @ActorUsuarioId INT,
    @MedicoId       INT,
    @SedeId         INT,
    @Asignar        BIT = 1,
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = CASE WHEN @Asignar = 1 THEN 'MEDICO_SEDE_ASIGNADA' ELSE 'MEDICO_SEDE_RETIRADA' END,
            @E VARCHAR(50) = 'Medico', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @MedicoId, @Det NVARCHAR(200),
            @Hoy DATE = CAST(clin.fn_AhoraLocal() AS DATE);

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'medicos.editar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        EXEC seg.usp_ObtenerLock @Recurso = N'Medico:Asignaciones';
        IF NOT EXISTS (SELECT 1 FROM clin.Medicos WHERE MedicoId = @MedicoId)
        BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'El médico no existe.'; GOTO Rechazo; END

        IF @Asignar = 1
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM clin.Sedes WHERE SedeId = @SedeId AND Activo = 1)
            BEGIN SELECT @Cod = 'SEDE_INACTIVA', @Msg = N'La sede no existe o está inactiva.'; GOTO Rechazo; END
            IF EXISTS (SELECT 1 FROM clin.MedicoSede WHERE MedicoId = @MedicoId AND SedeId = @SedeId)
                UPDATE clin.MedicoSede SET Activo = 1 WHERE MedicoId = @MedicoId AND SedeId = @SedeId;
            ELSE
                INSERT INTO clin.MedicoSede (MedicoId, SedeId) VALUES (@MedicoId, @SedeId);
        END
        ELSE
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM clin.MedicoSede WHERE MedicoId = @MedicoId AND SedeId = @SedeId AND Activo = 1)
            BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'El médico no atiende en esa sede.'; GOTO Rechazo; END
            IF (SELECT COUNT(*) FROM clin.MedicoSede WHERE MedicoId = @MedicoId AND Activo = 1) = 1
            BEGIN SELECT @Cod = 'ULTIMA_SEDE', @Msg = N'El médico debe conservar al menos una sede.'; GOTO Rechazo; END
            IF EXISTS (SELECT 1 FROM clin.AgendasMedicas WHERE MedicoId = @MedicoId AND SedeId = @SedeId AND Activo = 1 AND Fecha >= @Hoy)
            BEGIN SELECT @Cod = 'AGENDAS_FUTURAS', @Msg = N'Existen agendas activas futuras en esta sede. Desactívalas primero.'; GOTO Rechazo; END
            UPDATE clin.MedicoSede SET Activo = 0 WHERE MedicoId = @MedicoId AND SedeId = @SedeId;
        END

        SET @Det = CONCAT(N'SedeId=', @SedeId);
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Sedes actualizadas.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminMedicoCambiarEstado
    @ActorUsuarioId INT,
    @MedicoId       INT,
    @Activo         BIT,
    @VersionFila    VARCHAR(18),
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = CASE WHEN @Activo = 1 THEN 'MEDICO_ACTIVADO' ELSE 'MEDICO_DESACTIVADO' END, @E VARCHAR(50) = 'Medico',
            @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @MedicoId, @Actual BINARY(8),
            @Version BINARY(8) = TRY_CONVERT(BINARY(8), @VersionFila, 1), @Ahora DATETIME2(0) = clin.fn_AhoraLocal();

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'medicos.editar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        SELECT @Actual = VersionFila FROM clin.Medicos WITH (UPDLOCK, HOLDLOCK) WHERE MedicoId = @MedicoId;
        IF @Actual IS NULL
        BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'El médico no existe.'; GOTO Rechazo; END
        IF @Version IS NULL OR @Version <> @Actual
        BEGIN SELECT @Cod = 'CONFLICTO_EDICION', @Msg = N'El registro fue modificado por otro usuario. Recarga la página.'; GOTO Rechazo; END
        IF @Activo = 0 AND EXISTS (SELECT 1 FROM clin.Reservas WHERE MedicoId = @MedicoId AND EstadoReservaId = 1
                                   AND DATEADD(SECOND, DATEDIFF(SECOND, 0, CAST(HoraInicio AS DATETIME)), CAST(FechaCita AS DATETIME2(0))) >= @Ahora)
        BEGIN SELECT @Cod = 'RESERVAS_FUTURAS', @Msg = N'El médico tiene reservas confirmadas pendientes. Reprográmalas o cancélalas primero.'; GOTO Rechazo; END

        UPDATE clin.Medicos SET Activo = @Activo, FechaModificacionUtc = SYSUTCDATETIME() WHERE MedicoId = @MedicoId;

        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, NULL, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Estado del médico actualizado.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

/* ============================================================================
   15. api — CATÁLOGOS (especialidades, sedes, consultorios). @Id NULL = crear.
   ============================================================================ */
CREATE OR ALTER PROCEDURE api.usp_AdminEspecialidadGuardar
    @ActorUsuarioId INT,
    @EspecialidadId INT           = NULL,
    @Nombre         NVARCHAR(100),
    @Descripcion    NVARCHAR(250) = NULL,
    @Activo         BIT           = 1,
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = CASE WHEN @EspecialidadId IS NULL THEN 'ESPECIALIDAD_CREADA' ELSE 'ESPECIALIDAD_ACTUALIZADA' END,
            @E VARCHAR(50) = 'Especialidad', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @EspecialidadId;
    SET @Nombre = LTRIM(RTRIM(@Nombre));

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'especialidades.gestionar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END
    IF LEN(ISNULL(@Nombre, N'')) < 3
    BEGIN SELECT @Cod = 'DATOS_INVALIDOS', @Msg = N'El nombre debe tener al menos 3 caracteres.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        IF EXISTS (SELECT 1 FROM clin.Especialidades WITH (UPDLOCK, HOLDLOCK) WHERE Nombre = @Nombre AND EspecialidadId <> ISNULL(@EspecialidadId, 0))
        BEGIN SELECT @Cod = 'NOMBRE_DUPLICADO', @Msg = N'Ya existe una especialidad con ese nombre.'; GOTO Rechazo; END

        IF @EspecialidadId IS NULL
        BEGIN
            INSERT INTO clin.Especialidades (Nombre, Descripcion, Activo) VALUES (@Nombre, NULLIF(LTRIM(RTRIM(@Descripcion)), N''), @Activo);
            SET @Id = SCOPE_IDENTITY();
        END
        ELSE
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM clin.Especialidades WITH (UPDLOCK) WHERE EspecialidadId = @EspecialidadId)
            BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'La especialidad no existe.'; GOTO Rechazo; END
            IF @Activo = 0 AND EXISTS (SELECT 1 FROM clin.AgendasMedicas WHERE EspecialidadId = @EspecialidadId AND Activo = 1 AND Fecha >= CAST(clin.fn_AhoraLocal() AS DATE))
            BEGIN SELECT @Cod = 'AGENDAS_FUTURAS', @Msg = N'Existen agendas activas futuras con esta especialidad.'; GOTO Rechazo; END
            UPDATE clin.Especialidades SET Nombre = @Nombre, Descripcion = NULLIF(LTRIM(RTRIM(@Descripcion)), N''), Activo = @Activo
            WHERE EspecialidadId = @EspecialidadId;
        END

        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Nombre, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Especialidad guardada.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminSedeGuardar
    @ActorUsuarioId INT,
    @SedeId         INT           = NULL,
    @Codigo         VARCHAR(20),
    @Nombre         NVARCHAR(100),
    @Direccion      NVARCHAR(200) = NULL,
    @Activo         BIT           = 1,
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = CASE WHEN @SedeId IS NULL THEN 'SEDE_CREADA' ELSE 'SEDE_ACTUALIZADA' END,
            @E VARCHAR(50) = 'Sede', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @SedeId;
    SELECT @Nombre = LTRIM(RTRIM(@Nombre)), @Codigo = UPPER(LTRIM(RTRIM(@Codigo)));

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'sedes.gestionar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END
    IF LEN(ISNULL(@Nombre, N'')) < 3 OR LEN(ISNULL(@Codigo, '')) < 2 OR @Codigo LIKE '%[^0-9A-Z_-]%'
    BEGIN SELECT @Cod = 'DATOS_INVALIDOS', @Msg = N'Código (A-Z, 0-9, guion) y nombre son obligatorios.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        IF EXISTS (SELECT 1 FROM clin.Sedes WITH (UPDLOCK, HOLDLOCK) WHERE (Nombre = @Nombre OR Codigo = @Codigo) AND SedeId <> ISNULL(@SedeId, 0))
        BEGIN SELECT @Cod = 'NOMBRE_DUPLICADO', @Msg = N'Ya existe una sede con ese código o nombre.'; GOTO Rechazo; END

        IF @SedeId IS NULL
        BEGIN
            INSERT INTO clin.Sedes (Codigo, Nombre, Direccion, Activo) VALUES (@Codigo, @Nombre, NULLIF(LTRIM(RTRIM(@Direccion)), N''), @Activo);
            SET @Id = SCOPE_IDENTITY();
        END
        ELSE
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM clin.Sedes WITH (UPDLOCK) WHERE SedeId = @SedeId)
            BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'La sede no existe.'; GOTO Rechazo; END
            IF @Activo = 0 AND EXISTS (SELECT 1 FROM clin.AgendasMedicas WHERE SedeId = @SedeId AND Activo = 1 AND Fecha >= CAST(clin.fn_AhoraLocal() AS DATE))
            BEGIN SELECT @Cod = 'AGENDAS_FUTURAS', @Msg = N'Existen agendas activas futuras en esta sede.'; GOTO Rechazo; END
            UPDATE clin.Sedes SET Codigo = @Codigo, Nombre = @Nombre, Direccion = NULLIF(LTRIM(RTRIM(@Direccion)), N''), Activo = @Activo
            WHERE SedeId = @SedeId;
        END

        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Nombre, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Sede guardada.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminConsultorioGuardar
    @ActorUsuarioId INT,
    @ConsultorioId  INT           = NULL,
    @SedeId         INT,
    @Codigo         VARCHAR(20),
    @Nombre         NVARCHAR(100),
    @Piso           TINYINT       = NULL,
    @Activo         BIT           = 1,
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @A VARCHAR(50) = CASE WHEN @ConsultorioId IS NULL THEN 'CONSULTORIO_CREADO' ELSE 'CONSULTORIO_ACTUALIZADO' END,
            @E VARCHAR(50) = 'Consultorio', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @ConsultorioId, @SedeActual INT;
    SELECT @Nombre = LTRIM(RTRIM(@Nombre)), @Codigo = UPPER(LTRIM(RTRIM(@Codigo)));

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'consultorios.gestionar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END
    IF LEN(ISNULL(@Nombre, N'')) < 2 OR LEN(ISNULL(@Codigo, '')) < 1 OR @Codigo LIKE '%[^0-9A-Z_-]%'
    BEGIN SELECT @Cod = 'DATOS_INVALIDOS', @Msg = N'Código (A-Z, 0-9, guion) y nombre son obligatorios.'; GOTO Rechazo; END
    IF NOT EXISTS (SELECT 1 FROM clin.Sedes WHERE SedeId = @SedeId AND Activo = 1)
    BEGIN SELECT @Cod = 'SEDE_INACTIVA', @Msg = N'La sede no existe o está inactiva.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        IF EXISTS (SELECT 1 FROM clin.Consultorios WITH (UPDLOCK, HOLDLOCK) WHERE SedeId = @SedeId AND Codigo = @Codigo AND ConsultorioId <> ISNULL(@ConsultorioId, 0))
        BEGIN SELECT @Cod = 'CODIGO_DUPLICADO', @Msg = N'Ya existe un consultorio con ese código en la sede.'; GOTO Rechazo; END

        IF @ConsultorioId IS NULL
        BEGIN
            INSERT INTO clin.Consultorios (SedeId, Codigo, Nombre, Piso, Activo) VALUES (@SedeId, @Codigo, @Nombre, @Piso, @Activo);
            SET @Id = SCOPE_IDENTITY();
        END
        ELSE
        BEGIN
            SELECT @SedeActual = SedeId FROM clin.Consultorios WITH (UPDLOCK) WHERE ConsultorioId = @ConsultorioId;
            IF @SedeActual IS NULL
            BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'El consultorio no existe.'; GOTO Rechazo; END
            IF @SedeActual <> @SedeId
            BEGIN SELECT @Cod = 'OPERACION_NO_PERMITIDA', @Msg = N'Un consultorio no puede cambiar de sede. Crea uno nuevo.'; GOTO Rechazo; END
            IF @Activo = 0 AND EXISTS (SELECT 1 FROM clin.AgendasMedicas WHERE ConsultorioId = @ConsultorioId AND Activo = 1 AND Fecha >= CAST(clin.fn_AhoraLocal() AS DATE))
            BEGIN SELECT @Cod = 'AGENDAS_FUTURAS', @Msg = N'Existen agendas activas futuras en este consultorio.'; GOTO Rechazo; END
            UPDATE clin.Consultorios SET Codigo = @Codigo, Nombre = @Nombre, Piso = @Piso, Activo = @Activo WHERE ConsultorioId = @ConsultorioId;
        END

        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Nombre, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Consultorio guardado.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

/* ============================================================================
   16. AGENDA — FUNCIONES E INTERNOS
   Orden de locks (evita deadlocks):
     Agenda:Medico:{id}:{fecha}  -> Agenda:Consultorio:{id}:{fecha}
     -> Reserva:Horario:{id} (ascendente) -> Reserva:Paciente:{id}:{fecha} (ascendente)
   Reservas toman Agenda:Medico en modo Shared; agenda/bloqueos en Exclusive.
   ============================================================================ */

-- Slots reservables: todo activo, sin bloqueo, sin reserva que ocupe, dentro de la ventana permitida.
CREATE OR ALTER FUNCTION clin.fn_HorariosReservables (@Desde DATETIME2(0), @FechaMax DATE)
RETURNS TABLE
AS
RETURN
(
    SELECT
        h.HorarioMedicoId, a.AgendaId, a.MedicoId, a.EspecialidadId, a.SedeId, a.ConsultorioId, a.TipoAtencionId,
        a.Fecha, h.HoraInicio, h.HoraFin
    FROM clin.HorariosMedicos h
    JOIN clin.AgendasMedicas a     ON a.AgendaId = h.AgendaId AND a.Activo = 1
    JOIN clin.Medicos m            ON m.MedicoId = a.MedicoId AND m.Activo = 1
    JOIN clin.Especialidades e     ON e.EspecialidadId = a.EspecialidadId AND e.Activo = 1
    JOIN clin.Sedes s              ON s.SedeId = a.SedeId AND s.Activo = 1
    JOIN clin.Consultorios c       ON c.ConsultorioId = a.ConsultorioId AND c.Activo = 1
    JOIN clin.MedicoEspecialidad me ON me.MedicoId = a.MedicoId AND me.EspecialidadId = a.EspecialidadId AND me.Activo = 1
    JOIN clin.MedicoSede ms        ON ms.MedicoId = a.MedicoId AND ms.SedeId = a.SedeId AND ms.Activo = 1
    WHERE h.Activo = 1
      AND h.Bloqueado = 0
      AND a.Fecha >= CAST(@Desde AS DATE)
      AND a.Fecha <= @FechaMax
      AND clin.fn_FechaHora(a.Fecha, h.HoraInicio) >= @Desde
      AND NOT EXISTS (SELECT 1 FROM clin.Reservas r WHERE r.HorarioMedicoId = h.HorarioMedicoId AND r.OcupaHorario = 1)
);
GO

-- Crea UNA agenda con sus slots. Debe ejecutarse dentro de la transacción del llamador.
-- @Omitida = 1 cuando ya existe una agenda idéntica activa (idempotencia de generación por rango).
CREATE OR ALTER PROCEDURE clin.usp_AgendaCrearInterno
    @ActorUsuarioId  INT,
    @MedicoId        INT,
    @EspecialidadId  INT,
    @SedeId          INT,
    @ConsultorioId   INT,
    @TipoAtencionId  TINYINT,
    @Fecha           DATE,
    @HoraInicio      TIME(0),
    @HoraFin         TIME(0),
    @DuracionMinutos SMALLINT,
    @PlantillaId     INT = NULL,
    @AgendaId        BIGINT        OUTPUT,
    @Omitida         BIT           OUTPUT,
    @Codigo          VARCHAR(50)   OUTPUT,
    @Mensaje         NVARCHAR(400) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT @AgendaId = NULL, @Omitida = 0, @Codigo = NULL, @Mensaje = NULL;
    DECLARE @Recurso NVARCHAR(255), @Ahora DATETIME2(0) = clin.fn_AhoraLocal(), @FechaTxt NVARCHAR(10) = CONVERT(NVARCHAR(10), @Fecha, 103);

    SET @Recurso = CONCAT(N'Agenda:Medico:', @MedicoId, N':', CONVERT(CHAR(8), @Fecha, 112));
    EXEC seg.usp_ObtenerLock @Recurso = @Recurso, @Modo = 'Exclusive';
    SET @Recurso = CONCAT(N'Agenda:Consultorio:', @ConsultorioId, N':', CONVERT(CHAR(8), @Fecha, 112));
    EXEC seg.usp_ObtenerLock @Recurso = @Recurso, @Modo = 'Exclusive';

    -- Agenda idéntica ya existente: se omite (no es error).
    SELECT @AgendaId = AgendaId FROM clin.AgendasMedicas
    WHERE MedicoId = @MedicoId AND Fecha = @Fecha AND HoraInicio = @HoraInicio AND HoraFin = @HoraFin
      AND ConsultorioId = @ConsultorioId AND EspecialidadId = @EspecialidadId AND DuracionMinutos = @DuracionMinutos AND Activo = 1;
    IF @AgendaId IS NOT NULL
    BEGIN
        SELECT @Omitida = 1, @Codigo = 'AGENDA_EXISTENTE', @Mensaje = CONCAT(N'Ya existe una agenda idéntica el ', @FechaTxt, N'.');
        RETURN;
    END

    IF clin.fn_FechaHora(@Fecha, @HoraInicio) <= @Ahora
        SELECT @Codigo = 'FECHA_PASADA', @Mensaje = CONCAT(N'No se pueden crear agendas en el pasado (', @FechaTxt, N').');
    ELSE IF EXISTS (SELECT 1 FROM clin.AgendasMedicas WHERE MedicoId = @MedicoId AND Fecha = @Fecha AND Activo = 1
                    AND HoraInicio < @HoraFin AND HoraFin > @HoraInicio)
        SELECT @Codigo = 'MEDICO_AGENDA_SOLAPADA', @Mensaje = CONCAT(N'El médico ya tiene una agenda que se cruza el ', @FechaTxt, N'.');
    ELSE IF EXISTS (SELECT 1 FROM clin.AgendasMedicas WHERE ConsultorioId = @ConsultorioId AND Fecha = @Fecha AND Activo = 1
                    AND HoraInicio < @HoraFin AND HoraFin > @HoraInicio)
        SELECT @Codigo = 'CONSULTORIO_SOLAPADO', @Mensaje = CONCAT(N'El consultorio está ocupado por otra agenda el ', @FechaTxt, N'.');
    IF @Codigo IS NOT NULL RETURN;

    INSERT INTO clin.AgendasMedicas (MedicoId, EspecialidadId, SedeId, ConsultorioId, TipoAtencionId, PlantillaAgendaId, Fecha,
                                     HoraInicio, HoraFin, DuracionMinutos, CreadoPorUsuarioId)
    VALUES (@MedicoId, @EspecialidadId, @SedeId, @ConsultorioId, @TipoAtencionId, @PlantillaId, @Fecha,
            @HoraInicio, @HoraFin, @DuracionMinutos, @ActorUsuarioId);
    SET @AgendaId = SCOPE_IDENTITY();

    ;WITH n AS
    (
        SELECT TOP (DATEDIFF(MINUTE, @HoraInicio, @HoraFin) / @DuracionMinutos)
               ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) - 1 AS i
        FROM sys.all_objects
    )
    INSERT INTO clin.HorariosMedicos (AgendaId, HoraInicio, HoraFin)
    SELECT @AgendaId,
           DATEADD(MINUTE, i * @DuracionMinutos, @HoraInicio),
           DATEADD(MINUTE, (i + 1) * @DuracionMinutos, @HoraInicio)
    FROM n;

    -- Aplica bloqueos activos del médico que se cruzan con los nuevos slots.
    UPDATE h
    SET Bloqueado = 1, BloqueoId = b.BloqueoId
    FROM clin.HorariosMedicos h
    CROSS APPLY
    (
        SELECT TOP (1) b.BloqueoId FROM clin.BloqueosMedico b
        WHERE b.MedicoId = @MedicoId AND b.Fecha = @Fecha AND b.Activo = 1
          AND b.HoraInicio < h.HoraFin AND b.HoraFin > h.HoraInicio
        ORDER BY b.BloqueoId
    ) b
    WHERE h.AgendaId = @AgendaId;
END
GO

-- Validaciones de maestros comunes a crear/generar agenda (sin efectos).
CREATE OR ALTER PROCEDURE clin.usp_AgendaValidarMaestros
    @MedicoId        INT,
    @EspecialidadId  INT,
    @SedeId          INT,
    @ConsultorioId   INT,
    @TipoAtencionId  TINYINT,
    @HoraInicio      TIME(0),
    @HoraFin         TIME(0),
    @DuracionMinutos SMALLINT,
    @Codigo          VARCHAR(50)   OUTPUT,
    @Mensaje         NVARCHAR(400) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT @Codigo = NULL, @Mensaje = NULL;

    IF NOT EXISTS (SELECT 1 FROM clin.Medicos WHERE MedicoId = @MedicoId AND Activo = 1)
        SELECT @Codigo = 'MEDICO_INACTIVO', @Mensaje = N'El médico no existe o está inactivo.';
    ELSE IF NOT EXISTS (SELECT 1 FROM clin.MedicoEspecialidad me JOIN clin.Especialidades e ON e.EspecialidadId = me.EspecialidadId
                        WHERE me.MedicoId = @MedicoId AND me.EspecialidadId = @EspecialidadId AND me.Activo = 1 AND e.Activo = 1)
        SELECT @Codigo = 'ESPECIALIDAD_NO_ASIGNADA', @Mensaje = N'El médico no tiene asignada esa especialidad activa.';
    ELSE IF NOT EXISTS (SELECT 1 FROM clin.MedicoSede ms JOIN clin.Sedes s ON s.SedeId = ms.SedeId
                        WHERE ms.MedicoId = @MedicoId AND ms.SedeId = @SedeId AND ms.Activo = 1 AND s.Activo = 1)
        SELECT @Codigo = 'SEDE_NO_ASIGNADA', @Mensaje = N'El médico no atiende en esa sede o la sede está inactiva.';
    ELSE IF NOT EXISTS (SELECT 1 FROM clin.Consultorios WHERE ConsultorioId = @ConsultorioId AND SedeId = @SedeId AND Activo = 1)
        SELECT @Codigo = 'CONSULTORIO_INVALIDO', @Mensaje = N'El consultorio no pertenece a la sede o está inactivo.';
    ELSE IF NOT EXISTS (SELECT 1 FROM clin.TiposAtencion WHERE TipoAtencionId = @TipoAtencionId)
        SELECT @Codigo = 'TIPO_ATENCION_INVALIDO', @Mensaje = N'Tipo de atención no válido.';
    ELSE IF @HoraInicio IS NULL OR @HoraFin IS NULL OR @HoraFin <= @HoraInicio
        SELECT @Codigo = 'HORARIO_INVALIDO', @Mensaje = N'La hora de fin debe ser posterior a la de inicio.';
    ELSE IF @DuracionMinutos NOT BETWEEN 10 AND 120
        SELECT @Codigo = 'DURACION_INVALIDA', @Mensaje = N'La duración de cada cita debe estar entre 10 y 120 minutos.';
    ELSE IF DATEDIFF(MINUTE, @HoraInicio, @HoraFin) < @DuracionMinutos
        SELECT @Codigo = 'DURACION_INVALIDA', @Mensaje = N'El rango horario no alcanza para una cita.';
END
GO

/* ============================================================================
   17. api — AGENDA (administración)
   ============================================================================ */
CREATE OR ALTER PROCEDURE api.usp_AdminAgendaCrear
    @ActorUsuarioId  INT,
    @MedicoId        INT,
    @EspecialidadId  INT,
    @SedeId          INT,
    @ConsultorioId   INT,
    @TipoAtencionId  TINYINT,
    @Fecha           DATE,
    @HoraInicio      TIME(0),
    @HoraFin         TIME(0),
    @DuracionMinutos SMALLINT,
    @Ip              VARCHAR(45)   = NULL,
    @UserAgent       NVARCHAR(300) = NULL,
    @CorrelationId   VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
    DECLARE @A VARCHAR(50) = 'AGENDA_CREADA', @E VARCHAR(50) = 'Agenda', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT,
            @Omitida BIT, @Slots INT, @Det NVARCHAR(300);

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.gestionar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END
    EXEC clin.usp_AgendaValidarMaestros @MedicoId, @EspecialidadId, @SedeId, @ConsultorioId, @TipoAtencionId, @HoraInicio, @HoraFin, @DuracionMinutos, @Cod OUTPUT, @Msg OUTPUT;
    IF @Cod IS NOT NULL GOTO Rechazo;
    IF @Fecha > DATEADD(DAY, 365, CAST(clin.fn_AhoraLocal() AS DATE))
    BEGIN SELECT @Cod = 'FUERA_DE_RANGO', @Msg = N'No se pueden crear agendas con más de un año de anticipación.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        EXEC clin.usp_AgendaCrearInterno @ActorUsuarioId, @MedicoId, @EspecialidadId, @SedeId, @ConsultorioId, @TipoAtencionId, @Fecha,
             @HoraInicio, @HoraFin, @DuracionMinutos, NULL, @Id OUTPUT, @Omitida OUTPUT, @Cod OUTPUT, @Msg OUTPUT;
        IF @Omitida = 1
        BEGIN SELECT @Cod = 'AGENDA_EXISTENTE'; GOTO Rechazo; END
        IF @Cod IS NOT NULL GOTO Rechazo;

        SELECT @Slots = COUNT(*) FROM clin.HorariosMedicos WHERE AgendaId = @Id;
        SET @Det = CONCAT(N'MedicoId=', @MedicoId, N'; Fecha=', CONVERT(NVARCHAR(10), @Fecha, 23), N' ', CONVERT(NVARCHAR(5), @HoraInicio, 108),
                          N'-', CONVERT(NVARCHAR(5), @HoraFin, 108), N'; slots=', @Slots);
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        SET @Msg = CONCAT(N'Agenda creada con ', @Slots, N' horarios.');
        EXEC seg.usp_Resultado 1, @A, @Msg, @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

-- Genera agendas para los días de semana indicados (ISO 1=lunes..7=domingo) en un rango <= 92 días.
-- Todo o nada ante cruces; las agendas idénticas existentes se cuentan como omitidas (idempotente).
CREATE OR ALTER PROCEDURE api.usp_AdminAgendaGenerarRango
    @ActorUsuarioId  INT,
    @MedicoId        INT,
    @EspecialidadId  INT,
    @SedeId          INT,
    @ConsultorioId   INT,
    @TipoAtencionId  TINYINT,
    @FechaDesde      DATE,
    @FechaHasta      DATE,
    @DiasSemana      VARCHAR(20),
    @HoraInicio      TIME(0),
    @HoraFin         TIME(0),
    @DuracionMinutos SMALLINT,
    @Ip              VARCHAR(45)   = NULL,
    @UserAgent       NVARCHAR(300) = NULL,
    @CorrelationId   VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
    DECLARE @A VARCHAR(50) = 'AGENDA_GENERADA_RANGO', @E VARCHAR(50) = 'PlantillaAgenda', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT,
            @Hoy DATE = CAST(clin.fn_AhoraLocal() AS DATE), @Dia DATE, @Iso INT, @AgendaId BIGINT, @Omitida BIT,
            @Generadas INT = 0, @Omitidas INT = 0, @Det NVARCHAR(400);

    SET @DiasSemana = REPLACE(@DiasSemana, ' ', '');

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.gestionar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END
    EXEC clin.usp_AgendaValidarMaestros @MedicoId, @EspecialidadId, @SedeId, @ConsultorioId, @TipoAtencionId, @HoraInicio, @HoraFin, @DuracionMinutos, @Cod OUTPUT, @Msg OUTPUT;
    IF @Cod IS NOT NULL GOTO Rechazo;
    IF @FechaDesde IS NULL OR @FechaHasta IS NULL OR @FechaHasta < @FechaDesde
    BEGIN SELECT @Cod = 'RANGO_INVALIDO', @Msg = N'El rango de fechas no es válido.'; GOTO Rechazo; END
    IF @FechaDesde < @Hoy
    BEGIN SELECT @Cod = 'FECHA_PASADA', @Msg = N'El rango no puede empezar en el pasado.'; GOTO Rechazo; END
    IF DATEDIFF(DAY, @FechaDesde, @FechaHasta) > 92
    BEGIN SELECT @Cod = 'RANGO_INVALIDO', @Msg = N'El rango máximo es de 92 días.'; GOTO Rechazo; END
    IF @FechaHasta > DATEADD(DAY, 365, @Hoy)
    BEGIN SELECT @Cod = 'FUERA_DE_RANGO', @Msg = N'No se pueden crear agendas con más de un año de anticipación.'; GOTO Rechazo; END
    IF LEN(ISNULL(@DiasSemana, '')) = 0 OR @DiasSemana LIKE '%[^1-7,]%'
    BEGIN SELECT @Cod = 'DIAS_INVALIDOS', @Msg = N'Selecciona al menos un día de la semana.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        INSERT INTO clin.PlantillasAgenda (MedicoId, EspecialidadId, SedeId, ConsultorioId, TipoAtencionId, DiasSemana, HoraInicio, HoraFin,
                                           DuracionMinutos, FechaDesde, FechaHasta, CreadoPorUsuarioId)
        VALUES (@MedicoId, @EspecialidadId, @SedeId, @ConsultorioId, @TipoAtencionId, @DiasSemana, @HoraInicio, @HoraFin,
                @DuracionMinutos, @FechaDesde, @FechaHasta, @ActorUsuarioId);
        SET @Id = SCOPE_IDENTITY();

        SET @Dia = @FechaDesde;
        WHILE @Dia <= @FechaHasta
        BEGIN
            SET @Iso = (DATEPART(WEEKDAY, @Dia) + @@DATEFIRST - 2) % 7 + 1;
            IF CHARINDEX(CONVERT(CHAR(1), @Iso), @DiasSemana) > 0
               AND clin.fn_FechaHora(@Dia, @HoraInicio) > clin.fn_AhoraLocal()
            BEGIN
                EXEC clin.usp_AgendaCrearInterno @ActorUsuarioId, @MedicoId, @EspecialidadId, @SedeId, @ConsultorioId, @TipoAtencionId, @Dia,
                     @HoraInicio, @HoraFin, @DuracionMinutos, @Id, @AgendaId OUTPUT, @Omitida OUTPUT, @Cod OUTPUT, @Msg OUTPUT;
                IF @Omitida = 1 SET @Omitidas += 1;
                ELSE IF @Cod IS NOT NULL GOTO Rechazo;
                ELSE SET @Generadas += 1;
                SET @Cod = NULL;
            END
            SET @Dia = DATEADD(DAY, 1, @Dia);
        END

        IF @Generadas + @Omitidas = 0
        BEGIN SELECT @Cod = 'SIN_FECHAS', @Msg = N'Ningún día del rango coincide con los días seleccionados.'; GOTO Rechazo; END

        UPDATE clin.PlantillasAgenda SET AgendasGeneradas = @Generadas, AgendasOmitidas = @Omitidas WHERE PlantillaAgendaId = @Id;

        SET @Det = CONCAT(N'MedicoId=', @MedicoId, N'; ', CONVERT(NVARCHAR(10), @FechaDesde, 23), N'..', CONVERT(NVARCHAR(10), @FechaHasta, 23),
                          N'; días=', @DiasSemana, N'; generadas=', @Generadas, N'; omitidas=', @Omitidas);
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        SET @Msg = CONCAT(N'Generadas ', @Generadas, N', omitidas ', @Omitidas, N'.');
        EXEC seg.usp_Resultado 1, @A, @Msg, @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, NULL, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminAgendaListar
    @ActorUsuarioId INT,
    @MedicoId       INT  = NULL,
    @EspecialidadId INT  = NULL,
    @SedeId         INT  = NULL,
    @FechaDesde     DATE = NULL,
    @FechaHasta     DATE = NULL,
    @Activo         BIT  = NULL,
    @Pagina         INT  = 1,
    @TamanoPagina   INT  = 20
AS
BEGIN
    SET NOCOUNT ON;
    EXEC seg.usp_ExigirPermiso @ActorUsuarioId, 'agenda.ver';

    -- Un médico sin gestión de agenda solo ve la suya.
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.gestionar') = 0 AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar') = 0
        SET @MedicoId = ISNULL((SELECT MedicoId FROM clin.Medicos WHERE UsuarioId = @ActorUsuarioId), -1);

    SET @Pagina = CASE WHEN @Pagina < 1 THEN 1 ELSE @Pagina END;
    SET @TamanoPagina = CASE WHEN @TamanoPagina BETWEEN 1 AND 100 THEN @TamanoPagina ELSE 20 END;
    SET @FechaDesde = ISNULL(@FechaDesde, CAST(clin.fn_AhoraLocal() AS DATE));
    SET @FechaHasta = ISNULL(@FechaHasta, DATEADD(DAY, 30, @FechaDesde));

    SELECT AgendaId, MedicoId, MedicoNombre, CMP, EspecialidadId, Especialidad, SedeId, Sede, ConsultorioId, Consultorio,
           TipoAtencionId, TipoAtencion, PlantillaAgendaId, Fecha, HoraInicio, HoraFin, DuracionMinutos, Activo, VersionFila,
           TotalSlots, SlotsOcupados, SlotsBloqueados, SlotsLibres,
           COUNT(*) OVER () AS TotalFilas
    FROM api.vw_AgendasDetalle
    WHERE Fecha BETWEEN @FechaDesde AND @FechaHasta
      AND (@MedicoId IS NULL OR MedicoId = @MedicoId)
      AND (@EspecialidadId IS NULL OR EspecialidadId = @EspecialidadId)
      AND (@SedeId IS NULL OR SedeId = @SedeId)
      AND (@Activo IS NULL OR Activo = @Activo)
    ORDER BY Fecha, HoraInicio, MedicoNombre, AgendaId
    OFFSET (@Pagina - 1) * @TamanoPagina ROWS FETCH NEXT @TamanoPagina ROWS ONLY;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminAgendaDesactivar
    @ActorUsuarioId INT,
    @AgendaId       BIGINT,
    @VersionFila    VARCHAR(18),
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
    DECLARE @A VARCHAR(50) = 'AGENDA_DESACTIVADA', @E VARCHAR(50) = 'Agenda', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @AgendaId,
            @MedicoId INT, @Fecha DATE, @Activo BIT, @Actual BINARY(8), @Version BINARY(8) = TRY_CONVERT(BINARY(8), @VersionFila, 1),
            @Recurso NVARCHAR(255);

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.gestionar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END

    SELECT @MedicoId = MedicoId, @Fecha = Fecha FROM clin.AgendasMedicas WHERE AgendaId = @AgendaId;
    IF @MedicoId IS NULL
    BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'La agenda no existe.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        SET @Recurso = CONCAT(N'Agenda:Medico:', @MedicoId, N':', CONVERT(CHAR(8), @Fecha, 112));
        EXEC seg.usp_ObtenerLock @Recurso = @Recurso, @Modo = 'Exclusive';

        SELECT @Actual = VersionFila, @Activo = Activo FROM clin.AgendasMedicas WITH (UPDLOCK) WHERE AgendaId = @AgendaId;
        IF @Version IS NULL OR @Version <> @Actual
        BEGIN SELECT @Cod = 'CONFLICTO_EDICION', @Msg = N'La agenda fue modificada por otro usuario. Recarga la página.'; GOTO Rechazo; END
        IF @Activo = 0
        BEGIN SELECT @Cod = 'AGENDA_INACTIVA', @Msg = N'La agenda ya está desactivada.'; GOTO Rechazo; END
        IF EXISTS (SELECT 1 FROM clin.Reservas r JOIN clin.HorariosMedicos h ON h.HorarioMedicoId = r.HorarioMedicoId
                   WHERE h.AgendaId = @AgendaId AND r.EstadoReservaId = 1)
        BEGIN SELECT @Cod = 'RESERVAS_EN_AGENDA', @Msg = N'La agenda tiene reservas confirmadas. Reprográmalas o cancélalas primero.'; GOTO Rechazo; END

        UPDATE clin.AgendasMedicas SET Activo = 0, FechaModificacionUtc = SYSUTCDATETIME() WHERE AgendaId = @AgendaId;
        UPDATE clin.HorariosMedicos SET Activo = 0 WHERE AgendaId = @AgendaId;

        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, NULL, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Agenda desactivada.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

-- Bloquea un intervalo del médico (o un único slot si se envía @HorarioMedicoId).
-- Rechaza si existen reservas confirmadas en el intervalo: primero deben reprogramarse o cancelarse.
CREATE OR ALTER PROCEDURE api.usp_AdminHorarioBloquear
    @ActorUsuarioId  INT,
    @MedicoId        INT          = NULL,
    @Fecha           DATE         = NULL,
    @HoraInicio      TIME(0)      = NULL,
    @HoraFin         TIME(0)      = NULL,
    @TipoBloqueo     VARCHAR(20)  = 'AUSENCIA',
    @Motivo          NVARCHAR(200),
    @HorarioMedicoId BIGINT       = NULL,
    @Ip              VARCHAR(45)   = NULL,
    @UserAgent       NVARCHAR(300) = NULL,
    @CorrelationId   VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
    DECLARE @A VARCHAR(50) = 'HORARIO_BLOQUEADO', @E VARCHAR(50) = 'Bloqueo', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT,
            @Recurso NVARCHAR(255), @Slots INT, @Det NVARCHAR(400);
    SET @Motivo = LTRIM(RTRIM(@Motivo));

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.bloquear') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END

    IF @HorarioMedicoId IS NOT NULL
    BEGIN
        SELECT @MedicoId = a.MedicoId, @Fecha = a.Fecha, @HoraInicio = h.HoraInicio, @HoraFin = h.HoraFin, @TipoBloqueo = 'SLOT'
        FROM clin.HorariosMedicos h JOIN clin.AgendasMedicas a ON a.AgendaId = h.AgendaId
        WHERE h.HorarioMedicoId = @HorarioMedicoId AND h.Activo = 1 AND a.Activo = 1;
        IF @@ROWCOUNT = 0
        BEGIN SELECT @Cod = 'SLOT_NO_EXISTE', @Msg = N'El horario no existe o está inactivo.'; GOTO Rechazo; END
    END

    IF @TipoBloqueo NOT IN ('SLOT', 'AUSENCIA', 'REUNION', 'CAPACITACION', 'LICENCIA', 'MANTENIMIENTO')
    BEGIN SELECT @Cod = 'TIPO_BLOQUEO_INVALIDO', @Msg = N'Tipo de bloqueo no válido.'; GOTO Rechazo; END
    IF LEN(ISNULL(@Motivo, N'')) < 3
    BEGIN SELECT @Cod = 'MOTIVO_REQUERIDO', @Msg = N'Indica el motivo del bloqueo (mínimo 3 caracteres).'; GOTO Rechazo; END
    IF @MedicoId IS NULL OR @Fecha IS NULL OR @HoraInicio IS NULL OR @HoraFin IS NULL OR @HoraFin <= @HoraInicio
    BEGIN SELECT @Cod = 'HORARIO_INVALIDO', @Msg = N'El intervalo a bloquear no es válido.'; GOTO Rechazo; END
    IF clin.fn_FechaHora(@Fecha, @HoraFin) <= clin.fn_AhoraLocal()
    BEGIN SELECT @Cod = 'FECHA_PASADA', @Msg = N'No se puede bloquear un intervalo pasado.'; GOTO Rechazo; END
    IF NOT EXISTS (SELECT 1 FROM clin.Medicos WHERE MedicoId = @MedicoId)
    BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'El médico no existe.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        SET @Recurso = CONCAT(N'Agenda:Medico:', @MedicoId, N':', CONVERT(CHAR(8), @Fecha, 112));
        EXEC seg.usp_ObtenerLock @Recurso = @Recurso, @Modo = 'Exclusive';

        IF EXISTS (SELECT 1 FROM clin.Reservas WHERE MedicoId = @MedicoId AND FechaCita = @Fecha AND EstadoReservaId = 1
                   AND HoraInicio < @HoraFin AND HoraFin > @HoraInicio)
        BEGIN SELECT @Cod = 'RESERVAS_EN_INTERVALO', @Msg = N'Existen reservas confirmadas en el intervalo. Reprográmalas o cancélalas antes de bloquear.'; GOTO Rechazo; END

        INSERT INTO clin.BloqueosMedico (MedicoId, Fecha, HoraInicio, HoraFin, TipoBloqueo, Motivo, CreadoPorUsuarioId)
        VALUES (@MedicoId, @Fecha, @HoraInicio, @HoraFin, @TipoBloqueo, @Motivo, @ActorUsuarioId);
        SET @Id = SCOPE_IDENTITY();

        UPDATE h SET Bloqueado = 1, BloqueoId = @Id
        FROM clin.HorariosMedicos h JOIN clin.AgendasMedicas a ON a.AgendaId = h.AgendaId
        WHERE a.MedicoId = @MedicoId AND a.Fecha = @Fecha AND a.Activo = 1 AND h.Activo = 1 AND h.Bloqueado = 0
          AND h.HoraInicio < @HoraFin AND h.HoraFin > @HoraInicio;
        SET @Slots = @@ROWCOUNT;

        SET @Det = CONCAT(@TipoBloqueo, N'; MedicoId=', @MedicoId, N'; ', CONVERT(NVARCHAR(10), @Fecha, 23), N' ',
                          CONVERT(NVARCHAR(5), @HoraInicio, 108), N'-', CONVERT(NVARCHAR(5), @HoraFin, 108), N'; slots=', @Slots, N'; ', @Motivo);
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        SET @Msg = CONCAT(N'Bloqueo registrado. Horarios afectados: ', @Slots, N'.');
        EXEC seg.usp_Resultado 1, @A, @Msg, @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminHorarioDesbloquear
    @ActorUsuarioId INT,
    @BloqueoId      BIGINT,
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
    DECLARE @A VARCHAR(50) = 'HORARIO_DESBLOQUEADO', @E VARCHAR(50) = 'Bloqueo', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @BloqueoId,
            @MedicoId INT, @Fecha DATE, @Activo BIT, @Recurso NVARCHAR(255), @Slots INT;

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.bloquear') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END
    SELECT @MedicoId = MedicoId, @Fecha = Fecha FROM clin.BloqueosMedico WHERE BloqueoId = @BloqueoId;
    IF @MedicoId IS NULL
    BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'El bloqueo no existe.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        SET @Recurso = CONCAT(N'Agenda:Medico:', @MedicoId, N':', CONVERT(CHAR(8), @Fecha, 112));
        EXEC seg.usp_ObtenerLock @Recurso = @Recurso, @Modo = 'Exclusive';

        SELECT @Activo = Activo FROM clin.BloqueosMedico WITH (UPDLOCK) WHERE BloqueoId = @BloqueoId;
        IF @Activo = 0
        BEGIN SELECT @Cod = 'BLOQUEO_INACTIVO', @Msg = N'El bloqueo ya fue levantado.'; GOTO Rechazo; END

        UPDATE clin.BloqueosMedico SET Activo = 0, LevantadoPorUsuarioId = @ActorUsuarioId, FechaLevantamientoUtc = SYSUTCDATETIME()
        WHERE BloqueoId = @BloqueoId;

        UPDATE clin.HorariosMedicos SET Bloqueado = 0, BloqueoId = NULL WHERE BloqueoId = @BloqueoId;
        SET @Slots = @@ROWCOUNT;

        -- Si otro bloqueo activo cubre alguno de esos slots, se reaplica.
        UPDATE h SET Bloqueado = 1, BloqueoId = b.BloqueoId
        FROM clin.HorariosMedicos h
        JOIN clin.AgendasMedicas a ON a.AgendaId = h.AgendaId AND a.MedicoId = @MedicoId AND a.Fecha = @Fecha
        CROSS APPLY
        (
            SELECT TOP (1) b.BloqueoId FROM clin.BloqueosMedico b
            WHERE b.MedicoId = @MedicoId AND b.Fecha = @Fecha AND b.Activo = 1
              AND b.HoraInicio < h.HoraFin AND b.HoraFin > h.HoraInicio
            ORDER BY b.BloqueoId
        ) b
        WHERE h.Bloqueado = 0;

        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, NULL, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        SET @Msg = CONCAT(N'Bloqueo levantado. Horarios liberados: ', @Slots, N'.');
        EXEC seg.usp_Resultado 1, @A, @Msg, @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminBloqueosListar
    @ActorUsuarioId INT,
    @MedicoId       INT  = NULL,
    @FechaDesde     DATE = NULL,
    @FechaHasta     DATE = NULL,
    @SoloActivos    BIT  = 1,
    @Pagina         INT  = 1,
    @TamanoPagina   INT  = 20
AS
BEGIN
    SET NOCOUNT ON;
    EXEC seg.usp_ExigirPermiso @ActorUsuarioId, 'agenda.ver';
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.gestionar') = 0 AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar') = 0
        SET @MedicoId = ISNULL((SELECT MedicoId FROM clin.Medicos WHERE UsuarioId = @ActorUsuarioId), -1);

    SET @Pagina = CASE WHEN @Pagina < 1 THEN 1 ELSE @Pagina END;
    SET @TamanoPagina = CASE WHEN @TamanoPagina BETWEEN 1 AND 100 THEN @TamanoPagina ELSE 20 END;
    SET @FechaDesde = ISNULL(@FechaDesde, CAST(clin.fn_AhoraLocal() AS DATE));
    SET @FechaHasta = ISNULL(@FechaHasta, DATEADD(DAY, 60, @FechaDesde));

    SELECT b.BloqueoId, b.MedicoId, m.Nombres + N' ' + m.Apellidos AS MedicoNombre, b.Fecha, b.HoraInicio, b.HoraFin,
           b.TipoBloqueo, b.Motivo, b.Activo, uc.NombreUsuario AS CreadoPor, b.FechaCreacionUtc,
           ul.NombreUsuario AS LevantadoPor, b.FechaLevantamientoUtc,
           (SELECT COUNT(*) FROM clin.HorariosMedicos h WHERE h.BloqueoId = b.BloqueoId) AS SlotsAfectados,
           COUNT(*) OVER () AS TotalFilas
    FROM clin.BloqueosMedico b
    JOIN clin.Medicos m ON m.MedicoId = b.MedicoId
    JOIN seg.Usuarios uc ON uc.UsuarioId = b.CreadoPorUsuarioId
    LEFT JOIN seg.Usuarios ul ON ul.UsuarioId = b.LevantadoPorUsuarioId
    WHERE b.Fecha BETWEEN @FechaDesde AND @FechaHasta
      AND (@MedicoId IS NULL OR b.MedicoId = @MedicoId)
      AND (@SoloActivos = 0 OR b.Activo = 1)
    ORDER BY b.Fecha, b.HoraInicio, b.BloqueoId
    OFFSET (@Pagina - 1) * @TamanoPagina ROWS FETCH NEXT @TamanoPagina ROWS ONLY;
END
GO

/* ============================================================================
   18. api — DISPONIBILIDAD (consulta para reservar)
   ============================================================================ */
CREATE OR ALTER PROCEDURE api.usp_AgendaFechasDisponibles
    @ActorUsuarioId INT,
    @EspecialidadId INT,
    @MedicoId       INT  = NULL,
    @SedeId         INT  = NULL,
    @FechaDesde     DATE = NULL,
    @FechaHasta     DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.crear') = 0 AND seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.ver') = 0
        THROW 50403, N'SIN_PERMISO', 1;

    DECLARE @Ahora DATETIME2(0) = clin.fn_AhoraLocal();
    DECLARE @Desde DATETIME2(0) = DATEADD(MINUTE, seg.fn_ConfigEntero('MINUTOS_MINIMOS_ANTICIPACION', 30), @Ahora);
    DECLARE @Max DATE = DATEADD(DAY, seg.fn_ConfigEntero('MAX_DIAS_RESERVA_FUTURA', 60), CAST(@Ahora AS DATE));

    IF @FechaDesde IS NOT NULL AND @FechaDesde > CAST(@Desde AS DATE) SET @Desde = CAST(@FechaDesde AS DATETIME2(0));
    IF @FechaHasta IS NOT NULL AND @FechaHasta < @Max SET @Max = @FechaHasta;

    SELECT r.Fecha, COUNT(*) AS SlotsDisponibles, COUNT(DISTINCT r.MedicoId) AS Medicos, MIN(r.HoraInicio) AS PrimeraHora
    FROM clin.fn_HorariosReservables(@Desde, @Max) r
    WHERE r.EspecialidadId = @EspecialidadId
      AND (@MedicoId IS NULL OR r.MedicoId = @MedicoId)
      AND (@SedeId IS NULL OR r.SedeId = @SedeId)
    GROUP BY r.Fecha
    ORDER BY r.Fecha;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AgendaHorariosDisponibles
    @ActorUsuarioId INT,
    @EspecialidadId INT,
    @Fecha          DATE,
    @MedicoId       INT = NULL,
    @SedeId         INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.crear') = 0 AND seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.ver') = 0
        THROW 50403, N'SIN_PERMISO', 1;

    DECLARE @Ahora DATETIME2(0) = clin.fn_AhoraLocal();
    DECLARE @Desde DATETIME2(0) = DATEADD(MINUTE, seg.fn_ConfigEntero('MINUTOS_MINIMOS_ANTICIPACION', 30), @Ahora);
    DECLARE @Max DATE = DATEADD(DAY, seg.fn_ConfigEntero('MAX_DIAS_RESERVA_FUTURA', 60), CAST(@Ahora AS DATE));
    IF @Fecha > CAST(@Desde AS DATE) SET @Desde = CAST(@Fecha AS DATETIME2(0));
    IF @Fecha < @Max SET @Max = @Fecha;

    SELECT r.HorarioMedicoId, r.AgendaId, r.MedicoId, m.Nombres + N' ' + m.Apellidos AS MedicoNombre, m.CMP,
           r.EspecialidadId, e.Nombre AS Especialidad, r.SedeId, s.Nombre AS Sede, r.ConsultorioId, c.Nombre AS Consultorio,
           r.TipoAtencionId, t.Codigo AS TipoAtencion, t.Nombre AS TipoAtencionNombre, r.Fecha, r.HoraInicio, r.HoraFin
    FROM clin.fn_HorariosReservables(@Desde, @Max) r
    JOIN clin.Medicos m        ON m.MedicoId = r.MedicoId
    JOIN clin.Especialidades e ON e.EspecialidadId = r.EspecialidadId
    JOIN clin.Sedes s          ON s.SedeId = r.SedeId
    JOIN clin.Consultorios c   ON c.ConsultorioId = r.ConsultorioId
    JOIN clin.TiposAtencion t  ON t.TipoAtencionId = r.TipoAtencionId
    WHERE r.Fecha = @Fecha
      AND r.EspecialidadId = @EspecialidadId
      AND (@MedicoId IS NULL OR r.MedicoId = @MedicoId)
      AND (@SedeId IS NULL OR r.SedeId = @SedeId)
    ORDER BY r.HoraInicio, MedicoNombre;
END
GO

-- Estado visual de todos los horarios del día (wizard de reserva): DISPONIBLE | BLOQUEADO | NO_DISPONIBLE.
-- No expone datos de pacientes ni horarios pasados. La reserva vuelve a validar todo en usp_ReservaCrear.
CREATE OR ALTER PROCEDURE api.usp_AgendaHorariosEstado
    @ActorUsuarioId INT,
    @EspecialidadId INT,
    @Fecha          DATE,
    @MedicoId       INT = NULL,
    @SedeId         INT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.crear') = 0 AND seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.ver') = 0
        THROW 50403, N'SIN_PERMISO', 1;

    DECLARE @Ahora DATETIME2(0) = clin.fn_AhoraLocal();
    DECLARE @Desde DATETIME2(0) = DATEADD(MINUTE, seg.fn_ConfigEntero('MINUTOS_MINIMOS_ANTICIPACION', 30), @Ahora);
    DECLARE @Max DATE = DATEADD(DAY, seg.fn_ConfigEntero('MAX_DIAS_RESERVA_FUTURA', 60), CAST(@Ahora AS DATE));

    SELECT h.HorarioMedicoId, a.AgendaId, a.MedicoId, m.Nombres + N' ' + m.Apellidos AS MedicoNombre, m.CMP,
           a.EspecialidadId, e.Nombre AS Especialidad, a.SedeId, s.Nombre AS Sede, a.ConsultorioId, c.Nombre AS Consultorio,
           a.TipoAtencionId, t.Codigo AS TipoAtencion, t.Nombre AS TipoAtencionNombre, a.Fecha, h.HoraInicio, h.HoraFin,
           CASE WHEN h.Bloqueado = 1 THEN 'BLOQUEADO'
                WHEN r.HorarioMedicoId IS NOT NULL THEN 'DISPONIBLE'
                ELSE 'NO_DISPONIBLE' END AS Estado
    FROM clin.HorariosMedicos h
    JOIN clin.AgendasMedicas a ON a.AgendaId = h.AgendaId AND a.Activo = 1
    JOIN clin.Medicos m        ON m.MedicoId = a.MedicoId AND m.Activo = 1
    JOIN clin.Especialidades e ON e.EspecialidadId = a.EspecialidadId
    JOIN clin.Sedes s          ON s.SedeId = a.SedeId
    JOIN clin.Consultorios c   ON c.ConsultorioId = a.ConsultorioId
    JOIN clin.TiposAtencion t  ON t.TipoAtencionId = a.TipoAtencionId
    LEFT JOIN clin.fn_HorariosReservables(@Desde, @Max) r ON r.HorarioMedicoId = h.HorarioMedicoId
    WHERE h.Activo = 1
      AND a.Fecha = @Fecha
      AND a.EspecialidadId = @EspecialidadId
      AND (@MedicoId IS NULL OR a.MedicoId = @MedicoId)
      AND (@SedeId IS NULL OR a.SedeId = @SedeId)
      AND clin.fn_FechaHora(a.Fecha, h.HoraInicio) > @Ahora
    ORDER BY h.HoraInicio, MedicoNombre;
END
GO

-- Vista operativa de un día del médico: todos los slots con su estado y la reserva que lo ocupa.
CREATE OR ALTER PROCEDURE api.usp_AgendaObtenerDia
    @ActorUsuarioId INT,
    @MedicoId       INT = NULL,
    @Fecha          DATE
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @Propio INT = (SELECT MedicoId FROM clin.Medicos WHERE UsuarioId = @ActorUsuarioId);
    SET @MedicoId = ISNULL(@MedicoId, @Propio);
    IF @MedicoId IS NULL
        THROW 50403, N'SIN_PERMISO', 1;
    IF ISNULL(@Propio, 0) <> @MedicoId
       AND seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.gestionar') = 0
       AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar') = 0
        THROW 50403, N'SIN_PERMISO', 1;
    IF ISNULL(@Propio, 0) = @MedicoId AND seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.ver') = 0
        THROW 50403, N'SIN_PERMISO', 1;

    SELECT h.HorarioMedicoId, a.AgendaId, a.Fecha, h.HoraInicio, h.HoraFin, a.EspecialidadId, e.Nombre AS Especialidad,
           a.SedeId, s.Nombre AS Sede, c.Nombre AS Consultorio, t.Codigo AS TipoAtencion,
           h.Bloqueado, b.TipoBloqueo, b.Motivo AS MotivoBloqueo, h.BloqueoId,
           r.ReservaId, r.CodigoReserva, r.EstadoReservaId, er.Codigo AS EstadoCodigo, er.Nombre AS EstadoNombre,
           p.PacienteId, p.Apellidos + N', ' + p.Nombres AS PacienteNombre, p.TipoDocumento + N' ' + p.NumeroDocumento AS PacienteDocumento,
           r.Observacion, CONVERT(VARCHAR(18), CAST(r.VersionFila AS BINARY(8)), 1) AS ReservaVersionFila,
           CASE WHEN r.ReservaId IS NOT NULL THEN er.Codigo WHEN h.Bloqueado = 1 THEN 'BLOQUEADO' ELSE 'LIBRE' END AS EstadoSlot
    FROM clin.AgendasMedicas a
    JOIN clin.HorariosMedicos h ON h.AgendaId = a.AgendaId AND h.Activo = 1
    JOIN clin.Especialidades e  ON e.EspecialidadId = a.EspecialidadId
    JOIN clin.Sedes s           ON s.SedeId = a.SedeId
    JOIN clin.Consultorios c    ON c.ConsultorioId = a.ConsultorioId
    JOIN clin.TiposAtencion t   ON t.TipoAtencionId = a.TipoAtencionId
    LEFT JOIN clin.BloqueosMedico b ON b.BloqueoId = h.BloqueoId
    LEFT JOIN clin.Reservas r   ON r.HorarioMedicoId = h.HorarioMedicoId AND r.OcupaHorario = 1
    LEFT JOIN clin.EstadosReserva er ON er.EstadoReservaId = r.EstadoReservaId
    LEFT JOIN clin.Pacientes p  ON p.PacienteId = r.PacienteId
    WHERE a.MedicoId = @MedicoId AND a.Fecha = @Fecha AND a.Activo = 1
    ORDER BY h.HoraInicio;
END
GO

CREATE OR ALTER PROCEDURE api.usp_MedicoAgendaPropia
    @ActorUsuarioId INT,
    @FechaDesde     DATE = NULL,
    @FechaHasta     DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @MedicoId INT = (SELECT MedicoId FROM clin.Medicos WHERE UsuarioId = @ActorUsuarioId);
    IF @MedicoId IS NULL OR seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.ver') = 0
        THROW 50403, N'SIN_PERMISO', 1;

    SET @FechaDesde = ISNULL(@FechaDesde, CAST(clin.fn_AhoraLocal() AS DATE));
    SET @FechaHasta = ISNULL(@FechaHasta, DATEADD(DAY, 14, @FechaDesde));
    IF DATEDIFF(DAY, @FechaDesde, @FechaHasta) > 92 SET @FechaHasta = DATEADD(DAY, 92, @FechaDesde);

    SELECT AgendaId, Fecha, HoraInicio, HoraFin, DuracionMinutos, EspecialidadId, Especialidad, SedeId, Sede, Consultorio, TipoAtencion,
           TotalSlots, SlotsOcupados, SlotsBloqueados, SlotsLibres
    FROM api.vw_AgendasDetalle
    WHERE MedicoId = @MedicoId AND Activo = 1 AND Fecha BETWEEN @FechaDesde AND @FechaHasta
    ORDER BY Fecha, HoraInicio;
END
GO

/* ============================================================================
   19. RESERVAS — INTERNOS
   ============================================================================ */

-- Toma, en el orden global, los locks necesarios para operar sobre uno o dos slots y un paciente.
-- Los applocks con owner Transaction son reentrantes: re-solicitar uno ya tomado no bloquea.
CREATE OR ALTER PROCEDURE clin.usp_ReservaTomarLocks
    @HorarioA   BIGINT,
    @HorarioB   BIGINT = NULL,
    @PacienteId INT
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @L TABLE (Cat TINYINT, K1 BIGINT, K2 CHAR(8), Recurso NVARCHAR(255), Modo VARCHAR(10), PRIMARY KEY (Cat, K1, K2));
    DECLARE @S TABLE (HorarioMedicoId BIGINT PRIMARY KEY, MedicoId INT, ConsultorioId INT, Fecha DATE);

    INSERT INTO @S (HorarioMedicoId, MedicoId, ConsultorioId, Fecha)
    SELECT h.HorarioMedicoId, a.MedicoId, a.ConsultorioId, a.Fecha
    FROM clin.HorariosMedicos h JOIN clin.AgendasMedicas a ON a.AgendaId = h.AgendaId
    WHERE h.HorarioMedicoId IN (@HorarioA, ISNULL(@HorarioB, @HorarioA));

    INSERT INTO @L SELECT DISTINCT 1, MedicoId, CONVERT(CHAR(8), Fecha, 112),
           CONCAT(N'Agenda:Medico:', MedicoId, N':', CONVERT(CHAR(8), Fecha, 112)), 'Shared' FROM @S;
    INSERT INTO @L SELECT DISTINCT 2, ConsultorioId, CONVERT(CHAR(8), Fecha, 112),
           CONCAT(N'Agenda:Consultorio:', ConsultorioId, N':', CONVERT(CHAR(8), Fecha, 112)), 'Shared' FROM @S;
    INSERT INTO @L SELECT 3, HorarioMedicoId, '', CONCAT(N'Reserva:Horario:', HorarioMedicoId), 'Exclusive' FROM @S;
    INSERT INTO @L SELECT DISTINCT 4, @PacienteId, CONVERT(CHAR(8), Fecha, 112),
           CONCAT(N'Reserva:Paciente:', @PacienteId, N':', CONVERT(CHAR(8), Fecha, 112)), 'Exclusive' FROM @S;

    DECLARE @Recurso NVARCHAR(255), @Modo VARCHAR(10);
    DECLARE c CURSOR LOCAL FAST_FORWARD FOR SELECT Recurso, Modo FROM @L ORDER BY Cat, K1, K2;
    OPEN c;
    FETCH NEXT FROM c INTO @Recurso, @Modo;
    WHILE @@FETCH_STATUS = 0
    BEGIN
        EXEC seg.usp_ObtenerLock @Recurso = @Recurso, @Modo = @Modo;
        FETCH NEXT FROM c INTO @Recurso, @Modo;
    END
    CLOSE c;
    DEALLOCATE c;
END
GO

-- Valida todas las reglas e inserta la reserva. Se ejecuta DENTRO de la transacción del llamador
-- y DESPUÉS de clin.usp_ReservaTomarLocks. No escribe bitácora ni devuelve result set.
-- @Codigo = 'RESERVA_EXISTENTE' indica reintento idempotente exitoso (@ReservaId = la existente).
CREATE OR ALTER PROCEDURE clin.usp_ReservaValidarEInsertar
    @ActorUsuarioId   INT,
    @PacienteId       INT,
    @HorarioMedicoId  BIGINT,
    @Observacion      NVARCHAR(250),
    @IdempotencyKey   UNIQUEIDENTIFIER,
    @EsPersonal       BIT,
    @ReservaOrigenId  BIGINT = NULL,
    @ExcluirReservaId BIGINT = NULL,
    @MotivoHistorial  NVARCHAR(250) = NULL,
    @CorrelationId    VARCHAR(64) = NULL,
    @ReservaId        BIGINT        OUTPUT,
    @Codigo           VARCHAR(50)   OUTPUT,
    @Mensaje          NVARCHAR(400) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SELECT @ReservaId = NULL, @Codigo = NULL, @Mensaje = NULL;

    DECLARE @Ahora DATETIME2(0) = clin.fn_AhoraLocal(), @Inicio DATETIME2(0),
            @MedicoId INT, @EspecialidadId INT, @SedeId INT, @ConsultorioId INT, @TipoAtencionId TINYINT,
            @Fecha DATE, @HoraInicio TIME(0), @HoraFin TIME(0), @SlotActivo BIT, @AgendaActiva BIT, @Bloqueado BIT,
            @PacKey INT, @SlotKey BIGINT, @OrigenKey BIGINT, @Max INT;

    -- Idempotencia (re-chequeo bajo locks).
    SELECT @ReservaId = ReservaId, @PacKey = PacienteId, @SlotKey = HorarioMedicoId, @OrigenKey = ReservaOrigenId
    FROM clin.Reservas WHERE IdempotencyKey = @IdempotencyKey;
    IF @ReservaId IS NOT NULL
    BEGIN
        IF @PacKey = @PacienteId AND @SlotKey = @HorarioMedicoId AND ISNULL(@OrigenKey, 0) = ISNULL(@ReservaOrigenId, 0)
            SELECT @Codigo = 'RESERVA_EXISTENTE', @Mensaje = N'La reserva ya había sido registrada.';
        ELSE
            SELECT @ReservaId = NULL, @Codigo = 'IDEMPOTENCIA_CONFLICTO', @Mensaje = N'La clave de idempotencia ya fue usada con otros datos.';
        RETURN;
    END

    SELECT @MedicoId = a.MedicoId, @EspecialidadId = a.EspecialidadId, @SedeId = a.SedeId, @ConsultorioId = a.ConsultorioId,
           @TipoAtencionId = a.TipoAtencionId, @Fecha = a.Fecha, @HoraInicio = h.HoraInicio, @HoraFin = h.HoraFin,
           @SlotActivo = h.Activo, @AgendaActiva = a.Activo, @Bloqueado = h.Bloqueado
    FROM clin.HorariosMedicos h JOIN clin.AgendasMedicas a ON a.AgendaId = h.AgendaId
    WHERE h.HorarioMedicoId = @HorarioMedicoId;

    SET @Inicio = clin.fn_FechaHora(@Fecha, @HoraInicio);
    SET @Max = seg.fn_ConfigEntero('MAX_RESERVAS_ACTIVAS_PACIENTE', 5);

    IF @MedicoId IS NULL
        SELECT @Codigo = 'SLOT_NO_EXISTE', @Mensaje = N'El horario seleccionado no existe.';
    ELSE IF @SlotActivo = 0 OR @AgendaActiva = 0
         OR NOT EXISTS (SELECT 1 FROM clin.Especialidades WHERE EspecialidadId = @EspecialidadId AND Activo = 1)
         OR NOT EXISTS (SELECT 1 FROM clin.Sedes WHERE SedeId = @SedeId AND Activo = 1)
         OR NOT EXISTS (SELECT 1 FROM clin.Consultorios WHERE ConsultorioId = @ConsultorioId AND Activo = 1)
         OR NOT EXISTS (SELECT 1 FROM clin.MedicoEspecialidad WHERE MedicoId = @MedicoId AND EspecialidadId = @EspecialidadId AND Activo = 1)
         OR NOT EXISTS (SELECT 1 FROM clin.MedicoSede WHERE MedicoId = @MedicoId AND SedeId = @SedeId AND Activo = 1)
        SELECT @Codigo = 'AGENDA_INACTIVA', @Mensaje = N'La agenda de ese horario ya no está disponible.';
    ELSE IF @Bloqueado = 1
        SELECT @Codigo = 'SLOT_BLOQUEADO', @Mensaje = N'El horario está bloqueado.';
    ELSE IF NOT EXISTS (SELECT 1 FROM clin.Medicos WHERE MedicoId = @MedicoId AND Activo = 1)
        SELECT @Codigo = 'MEDICO_INACTIVO', @Mensaje = N'El médico no está disponible.';
    ELSE IF NOT EXISTS (SELECT 1 FROM clin.Pacientes WHERE PacienteId = @PacienteId AND Activo = 1)
        SELECT @Codigo = 'PACIENTE_INACTIVO', @Mensaje = N'El paciente no existe o está inactivo.';
    ELSE IF @Inicio <= @Ahora
        SELECT @Codigo = 'FECHA_PASADA', @Mensaje = N'El horario ya pasó.';
    ELSE IF @EsPersonal = 0 AND @Inicio < DATEADD(MINUTE, seg.fn_ConfigEntero('MINUTOS_MINIMOS_ANTICIPACION', 30), @Ahora)
        SELECT @Codigo = 'ANTICIPACION_INSUFICIENTE',
               @Mensaje = CONCAT(N'Debes reservar con al menos ', seg.fn_ConfigEntero('MINUTOS_MINIMOS_ANTICIPACION', 30), N' minutos de anticipación.');
    ELSE IF @Fecha > DATEADD(DAY, seg.fn_ConfigEntero('MAX_DIAS_RESERVA_FUTURA', 60), CAST(@Ahora AS DATE))
        SELECT @Codigo = 'FUERA_DE_RANGO',
               @Mensaje = CONCAT(N'Solo se puede reservar hasta ', seg.fn_ConfigEntero('MAX_DIAS_RESERVA_FUTURA', 60), N' días hacia adelante.');
    ELSE IF EXISTS (SELECT 1 FROM clin.Reservas WHERE HorarioMedicoId = @HorarioMedicoId AND OcupaHorario = 1)
        SELECT @Codigo = 'SLOT_OCUPADO', @Mensaje = N'El horario acaba de ser reservado por otra persona. Elige otro.';
    ELSE IF EXISTS (SELECT 1 FROM clin.Reservas WHERE PacienteId = @PacienteId AND FechaCita = @Fecha AND EstadoReservaId = 1
                    AND HoraInicio < @HoraFin AND HoraFin > @HoraInicio AND ReservaId <> ISNULL(@ExcluirReservaId, 0))
        SELECT @Codigo = 'PACIENTE_CITA_SOLAPADA', @Mensaje = N'El paciente ya tiene una cita que se cruza con ese horario.';
    ELSE IF EXISTS (SELECT 1 FROM clin.Reservas WHERE MedicoId = @MedicoId AND FechaCita = @Fecha AND EstadoReservaId = 1
                    AND HoraInicio < @HoraFin AND HoraFin > @HoraInicio AND ReservaId <> ISNULL(@ExcluirReservaId, 0))
        SELECT @Codigo = 'MEDICO_CRUCE', @Mensaje = N'El médico ya tiene una cita en ese horario.';
    ELSE IF EXISTS (SELECT 1 FROM clin.Reservas WHERE ConsultorioId = @ConsultorioId AND FechaCita = @Fecha AND EstadoReservaId = 1
                    AND TipoAtencionId = 1 AND @TipoAtencionId = 1
                    AND HoraInicio < @HoraFin AND HoraFin > @HoraInicio AND ReservaId <> ISNULL(@ExcluirReservaId, 0))
        SELECT @Codigo = 'CONSULTORIO_CRUCE', @Mensaje = N'El consultorio ya está ocupado en ese horario.';
    ELSE IF (SELECT COUNT(*) FROM clin.Reservas
             WHERE PacienteId = @PacienteId AND EstadoReservaId = 1 AND FechaCita >= CAST(@Ahora AS DATE)
               AND ReservaId <> ISNULL(@ExcluirReservaId, 0)) >= @Max
        SELECT @Codigo = 'LIMITE_RESERVAS', @Mensaje = CONCAT(N'Se alcanzó el máximo de ', @Max, N' reservas activas por paciente.');
    IF @Codigo IS NOT NULL RETURN;

    INSERT INTO clin.Reservas (HorarioMedicoId, PacienteId, MedicoId, EspecialidadId, SedeId, ConsultorioId, TipoAtencionId,
                               FechaCita, HoraInicio, HoraFin, EstadoReservaId, OcupaHorario, Observacion, IdempotencyKey,
                               ReservaOrigenId, CreadoPorUsuarioId)
    VALUES (@HorarioMedicoId, @PacienteId, @MedicoId, @EspecialidadId, @SedeId, @ConsultorioId, @TipoAtencionId,
            @Fecha, @HoraInicio, @HoraFin, 1, 1, NULLIF(LTRIM(RTRIM(@Observacion)), N''), @IdempotencyKey,
            @ReservaOrigenId, @ActorUsuarioId);
    SET @ReservaId = SCOPE_IDENTITY();

    INSERT INTO clin.ReservaHistorial (ReservaId, EstadoAnteriorId, EstadoNuevoId, UsuarioActorId, Motivo, CorrelationId)
    VALUES (@ReservaId, NULL, 1, @ActorUsuarioId, ISNULL(@MotivoHistorial, N'Reserva creada'), @CorrelationId);
END
GO

-- ¿Puede el actor ver la reserva? (propia como paciente, propia como médico, o gestión/auditoría).
CREATE OR ALTER FUNCTION clin.fn_PuedeVerReserva (@UsuarioId INT, @ReservaId BIGINT)
RETURNS BIT
AS
BEGIN
    IF seg.fn_TienePermiso(@UsuarioId, 'reservas.gestionar') = 1 RETURN 1;
    IF EXISTS (SELECT 1 FROM clin.Reservas r JOIN clin.Pacientes p ON p.PacienteId = r.PacienteId
               WHERE r.ReservaId = @ReservaId AND p.UsuarioId = @UsuarioId)
       AND seg.fn_TienePermiso(@UsuarioId, 'reservas.propias') = 1 RETURN 1;
    IF EXISTS (SELECT 1 FROM clin.Reservas r JOIN clin.Medicos m ON m.MedicoId = r.MedicoId
               WHERE r.ReservaId = @ReservaId AND m.UsuarioId = @UsuarioId)
       AND seg.fn_TienePermiso(@UsuarioId, 'agenda.ver') = 1 RETURN 1;
    RETURN 0;
END
GO

/* ============================================================================
   20. api — RESERVAS (comandos)
   ============================================================================ */
CREATE OR ALTER PROCEDURE api.usp_ReservaCrear
    @ActorUsuarioId  INT,
    @PacienteId      INT = NULL,
    @HorarioMedicoId BIGINT,
    @Observacion     NVARCHAR(250) = NULL,
    @IdempotencyKey  UNIQUEIDENTIFIER,
    @Ip              VARCHAR(45)   = NULL,
    @UserAgent       NVARCHAR(300) = NULL,
    @CorrelationId   VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
    DECLARE @A VARCHAR(50) = 'RESERVA_CREADA', @E VARCHAR(50) = 'Reserva', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT,
            @EsPersonal BIT = 0, @Propio INT, @PacKey INT, @SlotKey BIGINT, @Det NVARCHAR(400);

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.crear') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar') = 1
    BEGIN
        SET @EsPersonal = 1;
        IF @PacienteId IS NULL
        BEGIN SELECT @Cod = 'PACIENTE_REQUERIDO', @Msg = N'Selecciona el paciente.'; GOTO Rechazo; END
    END
    ELSE
    BEGIN
        SELECT @Propio = PacienteId FROM clin.Pacientes WHERE UsuarioId = @ActorUsuarioId;
        IF @Propio IS NULL OR seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.propias') = 0
           OR (@PacienteId IS NOT NULL AND @PacienteId <> @Propio)
        BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'Solo puedes reservar citas para ti.'; GOTO Rechazo; END
        SET @PacienteId = @Propio;
    END

    IF @IdempotencyKey IS NULL
    BEGIN SELECT @Cod = 'IDEMPOTENCIA_REQUERIDA', @Msg = N'Falta la clave de idempotencia.'; GOTO Rechazo; END

    -- Idempotencia rápida (sin transacción): un reintento devuelve la reserva ya creada.
    SELECT @Id = ReservaId, @PacKey = PacienteId, @SlotKey = HorarioMedicoId FROM clin.Reservas WHERE IdempotencyKey = @IdempotencyKey;
    IF @Id IS NOT NULL
    BEGIN
        IF @PacKey = @PacienteId AND @SlotKey = @HorarioMedicoId
        BEGIN EXEC seg.usp_Resultado 1, 'RESERVA_EXISTENTE', N'La reserva ya había sido registrada.', @Id; RETURN; END
        SELECT @Id = NULL, @Cod = 'IDEMPOTENCIA_CONFLICTO', @Msg = N'La clave de idempotencia ya fue usada con otros datos.';
        GOTO Rechazo;
    END

    IF NOT EXISTS (SELECT 1 FROM clin.HorariosMedicos WHERE HorarioMedicoId = @HorarioMedicoId)
    BEGIN SELECT @Cod = 'SLOT_NO_EXISTE', @Msg = N'El horario seleccionado no existe.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        EXEC clin.usp_ReservaTomarLocks @HorarioMedicoId, NULL, @PacienteId;
        EXEC clin.usp_ReservaValidarEInsertar @ActorUsuarioId, @PacienteId, @HorarioMedicoId, @Observacion, @IdempotencyKey, @EsPersonal,
             NULL, NULL, NULL, @CorrelationId, @Id OUTPUT, @Cod OUTPUT, @Msg OUTPUT;
        IF @Cod = 'RESERVA_EXISTENTE'
        BEGIN COMMIT; EXEC seg.usp_Resultado 1, @Cod, @Msg, @Id; RETURN; END
        IF @Cod IS NOT NULL GOTO Rechazo;

        SELECT @Det = CONCAT(CodigoReserva, N'; PacienteId=', PacienteId, N'; MedicoId=', MedicoId, N'; ',
                             CONVERT(NVARCHAR(10), FechaCita, 23), N' ', CONVERT(NVARCHAR(5), HoraInicio, 108))
        FROM clin.Reservas WHERE ReservaId = @Id;
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        SELECT @Msg = CONCAT(N'Reserva ', CodigoReserva, N' confirmada.') FROM clin.Reservas WHERE ReservaId = @Id;
        EXEC seg.usp_Resultado 1, @A, @Msg, @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

-- Recepción/administración reservando para un paciente registrado.
CREATE OR ALTER PROCEDURE api.usp_RecepcionReservaCrear
    @ActorUsuarioId  INT,
    @PacienteId      INT,
    @HorarioMedicoId BIGINT,
    @Observacion     NVARCHAR(250) = NULL,
    @IdempotencyKey  UNIQUEIDENTIFIER,
    @Ip              VARCHAR(45)   = NULL,
    @UserAgent       NVARCHAR(300) = NULL,
    @CorrelationId   VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar') = 0 OR seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.crear') = 0
    BEGIN
        EXEC seg.usp_Rechazo @ActorUsuarioId, 'RESERVA_CREADA', 'Reserva', NULL, 'SIN_PERMISO', N'No tienes permiso.', @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END
    EXEC api.usp_ReservaCrear @ActorUsuarioId, @PacienteId, @HorarioMedicoId, @Observacion, @IdempotencyKey, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_ReservaCancelar
    @ActorUsuarioId      INT,
    @ReservaId           BIGINT,
    @MotivoCancelacionId TINYINT,
    @Observacion         NVARCHAR(250) = NULL,
    @VersionFila         VARCHAR(18),
    @Ip                  VARCHAR(45)   = NULL,
    @UserAgent           NVARCHAR(300) = NULL,
    @CorrelationId       VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
    DECLARE @A VARCHAR(50) = 'RESERVA_CANCELADA', @E VARCHAR(50) = 'Reserva', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @ReservaId,
            @EsPersonal BIT = 0, @PacienteId INT, @HorarioId BIGINT, @Estado TINYINT, @Inicio DATETIME2(0), @Actual BINARY(8),
            @Version BINARY(8) = TRY_CONVERT(BINARY(8), @VersionFila, 1), @SoloPersonal BIT, @Horas INT, @Det NVARCHAR(400);
    SET @Observacion = NULLIF(LTRIM(RTRIM(@Observacion)), N'');

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.cancelar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END
    SET @EsPersonal = seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar');

    SELECT @PacienteId = r.PacienteId, @HorarioId = r.HorarioMedicoId
    FROM clin.Reservas r JOIN clin.Pacientes p ON p.PacienteId = r.PacienteId
    WHERE r.ReservaId = @ReservaId
      AND (@EsPersonal = 1 OR (p.UsuarioId = @ActorUsuarioId AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.propias') = 1));
    IF @PacienteId IS NULL
    BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'La reserva no existe.'; GOTO Rechazo; END

    SELECT @SoloPersonal = SoloPersonal FROM clin.MotivosCancelacion WHERE MotivoCancelacionId = @MotivoCancelacionId AND Activo = 1;
    IF @SoloPersonal IS NULL OR (@SoloPersonal = 1 AND @EsPersonal = 0)
    BEGIN SELECT @Cod = 'MOTIVO_INVALIDO', @Msg = N'Selecciona un motivo de cancelación válido.'; GOTO Rechazo; END
    IF @MotivoCancelacionId = 6 AND @Observacion IS NULL
    BEGIN SELECT @Cod = 'OBSERVACION_REQUERIDA', @Msg = N'Describe el motivo de la cancelación.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        EXEC clin.usp_ReservaTomarLocks @HorarioId, NULL, @PacienteId;

        SELECT @Estado = EstadoReservaId, @Actual = VersionFila, @Inicio = clin.fn_FechaHora(FechaCita, HoraInicio)
        FROM clin.Reservas WITH (UPDLOCK) WHERE ReservaId = @ReservaId;

        IF @Version IS NULL OR @Version <> @Actual
        BEGIN SELECT @Cod = 'CONFLICTO_EDICION', @Msg = N'La reserva fue modificada por otro usuario. Recarga la página.'; GOTO Rechazo; END
        IF NOT EXISTS (SELECT 1 FROM clin.TransicionesEstadoReserva WHERE EstadoOrigenId = @Estado AND EstadoDestinoId = 2)
        BEGIN SELECT @Cod = 'ESTADO_INVALIDO', @Msg = N'Solo se pueden cancelar reservas confirmadas.'; GOTO Rechazo; END
        IF @Inicio <= clin.fn_AhoraLocal()
        BEGIN SELECT @Cod = 'CITA_PASADA', @Msg = N'La cita ya inició o pasó; no se puede cancelar.'; GOTO Rechazo; END
        SET @Horas = seg.fn_ConfigEntero('HORAS_MINIMAS_CANCELACION', 2);
        IF @EsPersonal = 0 AND @Inicio < DATEADD(HOUR, @Horas, clin.fn_AhoraLocal())
        BEGIN SELECT @Cod = 'FUERA_DE_PLAZO', @Msg = CONCAT(N'Solo puedes cancelar hasta ', @Horas, N' horas antes de la cita. Comunícate con recepción.'); GOTO Rechazo; END

        UPDATE clin.Reservas
        SET EstadoReservaId = 2, OcupaHorario = 0, MotivoCancelacionId = @MotivoCancelacionId, ObservacionCancelacion = @Observacion,
            CanceladoPorUsuarioId = @ActorUsuarioId, FechaCancelacionUtc = SYSUTCDATETIME(), FechaModificacionUtc = SYSUTCDATETIME()
        WHERE ReservaId = @ReservaId;

        INSERT INTO clin.ReservaHistorial (ReservaId, EstadoAnteriorId, EstadoNuevoId, UsuarioActorId, Motivo, CorrelationId)
        SELECT @ReservaId, 1, 2, @ActorUsuarioId, LEFT(CONCAT(Nombre, CASE WHEN @Observacion IS NOT NULL THEN N': ' + @Observacion END), 250), @CorrelationId
        FROM clin.MotivosCancelacion WHERE MotivoCancelacionId = @MotivoCancelacionId;

        SELECT @Det = CONCAT(CodigoReserva, N'; motivo=', @MotivoCancelacionId) FROM clin.Reservas WHERE ReservaId = @ReservaId;
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        EXEC seg.usp_Resultado 1, @A, N'Reserva cancelada. El horario quedó libre.', @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

-- Reprogramación atómica: crea la nueva reserva (misma especialidad, otro slot) y marca la original como REPROGRAMADA.
CREATE OR ALTER PROCEDURE api.usp_ReservaReprogramar
    @ActorUsuarioId       INT,
    @ReservaId            BIGINT,
    @NuevoHorarioMedicoId BIGINT,
    @Motivo               NVARCHAR(250) = NULL,
    @VersionFila          VARCHAR(18),
    @IdempotencyKey       UNIQUEIDENTIFIER,
    @Ip                   VARCHAR(45)   = NULL,
    @UserAgent            NVARCHAR(300) = NULL,
    @CorrelationId        VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
    DECLARE @A VARCHAR(50) = 'RESERVA_REPROGRAMADA', @E VARCHAR(50) = 'Reserva', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT,
            @EsPersonal BIT = 0, @PacienteId INT, @HorarioId BIGINT, @EspecialidadId INT, @Estado TINYINT, @Inicio DATETIME2(0),
            @Actual BINARY(8), @Version BINARY(8) = TRY_CONVERT(BINARY(8), @VersionFila, 1), @Horas INT, @NuevaEsp INT,
            @Observacion NVARCHAR(250), @CodigoOrigen VARCHAR(12), @KeyOrigen BIGINT, @KeySlot BIGINT, @Det NVARCHAR(400), @HistMotivo NVARCHAR(250);
    SET @Motivo = NULLIF(LTRIM(RTRIM(@Motivo)), N'');

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.reprogramar') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END
    SET @EsPersonal = seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar');

    SELECT @PacienteId = r.PacienteId, @HorarioId = r.HorarioMedicoId, @EspecialidadId = r.EspecialidadId,
           @Observacion = r.Observacion, @CodigoOrigen = r.CodigoReserva
    FROM clin.Reservas r JOIN clin.Pacientes p ON p.PacienteId = r.PacienteId
    WHERE r.ReservaId = @ReservaId
      AND (@EsPersonal = 1 OR (p.UsuarioId = @ActorUsuarioId AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.propias') = 1));
    IF @PacienteId IS NULL
    BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'La reserva no existe.'; GOTO Rechazo; END
    IF @IdempotencyKey IS NULL
    BEGIN SELECT @Cod = 'IDEMPOTENCIA_REQUERIDA', @Msg = N'Falta la clave de idempotencia.'; GOTO Rechazo; END

    SELECT @Id = ReservaId, @KeyOrigen = ReservaOrigenId, @KeySlot = HorarioMedicoId FROM clin.Reservas WHERE IdempotencyKey = @IdempotencyKey;
    IF @Id IS NOT NULL
    BEGIN
        IF @KeyOrigen = @ReservaId AND @KeySlot = @NuevoHorarioMedicoId
        BEGIN EXEC seg.usp_Resultado 1, 'RESERVA_EXISTENTE', N'La reprogramación ya había sido registrada.', @Id; RETURN; END
        SELECT @Id = NULL, @Cod = 'IDEMPOTENCIA_CONFLICTO', @Msg = N'La clave de idempotencia ya fue usada con otros datos.';
        GOTO Rechazo;
    END

    SELECT @NuevaEsp = a.EspecialidadId FROM clin.HorariosMedicos h JOIN clin.AgendasMedicas a ON a.AgendaId = h.AgendaId
    WHERE h.HorarioMedicoId = @NuevoHorarioMedicoId;
    IF @NuevaEsp IS NULL
    BEGIN SELECT @Cod = 'SLOT_NO_EXISTE', @Msg = N'El nuevo horario no existe.'; GOTO Rechazo; END
    IF @NuevaEsp <> @EspecialidadId
    BEGIN SELECT @Cod = 'ESPECIALIDAD_DISTINTA', @Msg = N'Solo se puede reprogramar a un horario de la misma especialidad.'; GOTO Rechazo; END
    IF @NuevoHorarioMedicoId = @HorarioId
    BEGIN SELECT @Cod = 'MISMO_HORARIO', @Msg = N'Elige un horario distinto al actual.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        EXEC clin.usp_ReservaTomarLocks @HorarioId, @NuevoHorarioMedicoId, @PacienteId;

        SELECT @Estado = EstadoReservaId, @Actual = VersionFila, @Inicio = clin.fn_FechaHora(FechaCita, HoraInicio)
        FROM clin.Reservas WITH (UPDLOCK) WHERE ReservaId = @ReservaId;

        IF @Version IS NULL OR @Version <> @Actual
        BEGIN SELECT @Cod = 'CONFLICTO_EDICION', @Msg = N'La reserva fue modificada por otro usuario. Recarga la página.'; GOTO Rechazo; END
        IF NOT EXISTS (SELECT 1 FROM clin.TransicionesEstadoReserva WHERE EstadoOrigenId = @Estado AND EstadoDestinoId = 3)
        BEGIN SELECT @Cod = 'ESTADO_INVALIDO', @Msg = N'Solo se pueden reprogramar reservas confirmadas.'; GOTO Rechazo; END
        IF @Inicio <= clin.fn_AhoraLocal()
        BEGIN SELECT @Cod = 'CITA_PASADA', @Msg = N'La cita ya inició o pasó; no se puede reprogramar.'; GOTO Rechazo; END
        SET @Horas = seg.fn_ConfigEntero('HORAS_MINIMAS_CANCELACION', 2);
        IF @EsPersonal = 0 AND @Inicio < DATEADD(HOUR, @Horas, clin.fn_AhoraLocal())
        BEGIN SELECT @Cod = 'FUERA_DE_PLAZO', @Msg = CONCAT(N'Solo puedes reprogramar hasta ', @Horas, N' horas antes de la cita. Comunícate con recepción.'); GOTO Rechazo; END

        SET @HistMotivo = LEFT(CONCAT(N'Reprogramación de ', @CodigoOrigen, CASE WHEN @Motivo IS NOT NULL THEN N': ' + @Motivo END), 250);
        EXEC clin.usp_ReservaValidarEInsertar @ActorUsuarioId, @PacienteId, @NuevoHorarioMedicoId, @Observacion, @IdempotencyKey, @EsPersonal,
             @ReservaId, @ReservaId, @HistMotivo, @CorrelationId, @Id OUTPUT, @Cod OUTPUT, @Msg OUTPUT;
        IF @Cod = 'RESERVA_EXISTENTE'
        BEGIN COMMIT; EXEC seg.usp_Resultado 1, @Cod, N'La reprogramación ya había sido registrada.', @Id; RETURN; END
        IF @Cod IS NOT NULL GOTO Rechazo;

        UPDATE clin.Reservas
        SET EstadoReservaId = 3, OcupaHorario = 0, ReservaReemplazoId = @Id, FechaModificacionUtc = SYSUTCDATETIME()
        WHERE ReservaId = @ReservaId;

        INSERT INTO clin.ReservaHistorial (ReservaId, EstadoAnteriorId, EstadoNuevoId, UsuarioActorId, Motivo, CorrelationId)
        SELECT @ReservaId, 1, 3, @ActorUsuarioId,
               LEFT(CONCAT(N'Reprogramada a ', CodigoReserva, CASE WHEN @Motivo IS NOT NULL THEN N': ' + @Motivo END), 250), @CorrelationId
        FROM clin.Reservas WHERE ReservaId = @Id;

        SELECT @Det = CONCAT(@CodigoOrigen, N' -> ', CodigoReserva, N'; ', CONVERT(NVARCHAR(10), FechaCita, 23), N' ', CONVERT(NVARCHAR(5), HoraInicio, 108))
        FROM clin.Reservas WHERE ReservaId = @Id;
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        SELECT @Msg = CONCAT(N'Cita reprogramada. Nuevo código: ', CodigoReserva, N'.') FROM clin.Reservas WHERE ReservaId = @Id;
        EXEC seg.usp_Resultado 1, @A, @Msg, @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    SET @Id = ISNULL(@Id, @ReservaId);
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

-- Cierre de cita por el médico (ATENDIDA=4 / NO_ASISTIO=5). Devuelve la fila estándar.
CREATE OR ALTER PROCEDURE clin.usp_ReservaCerrar
    @ActorUsuarioId INT,
    @ReservaId      BIGINT,
    @EstadoNuevo    TINYINT,
    @Nota           NVARCHAR(250),
    @VersionFila    VARCHAR(18),
    @Accion         VARCHAR(50),
    @Ip             VARCHAR(45),
    @UserAgent      NVARCHAR(300),
    @CorrelationId  VARCHAR(64)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
    DECLARE @A VARCHAR(50) = @Accion, @E VARCHAR(50) = 'Reserva', @Cod VARCHAR(50), @Msg NVARCHAR(400), @Id BIGINT = @ReservaId,
            @PacienteId INT, @HorarioId BIGINT, @Estado TINYINT, @Inicio DATETIME2(0), @Actual BINARY(8),
            @Version BINARY(8) = TRY_CONVERT(BINARY(8), @VersionFila, 1), @Det NVARCHAR(400);
    SET @Nota = NULLIF(LTRIM(RTRIM(@Nota)), N'');

    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.estado') = 0
    BEGIN SELECT @Cod = 'SIN_PERMISO', @Msg = N'No tienes permiso.'; GOTO Rechazo; END

    SELECT @PacienteId = r.PacienteId, @HorarioId = r.HorarioMedicoId
    FROM clin.Reservas r JOIN clin.Medicos m ON m.MedicoId = r.MedicoId
    WHERE r.ReservaId = @ReservaId
      AND (m.UsuarioId = @ActorUsuarioId OR seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar') = 1);
    IF @PacienteId IS NULL
    BEGIN SELECT @Cod = 'NO_ENCONTRADO', @Msg = N'La reserva no existe o no pertenece a tu agenda.'; GOTO Rechazo; END

    BEGIN TRY
        BEGIN TRAN;
        EXEC clin.usp_ReservaTomarLocks @HorarioId, NULL, @PacienteId;

        SELECT @Estado = EstadoReservaId, @Actual = VersionFila, @Inicio = clin.fn_FechaHora(FechaCita, HoraInicio)
        FROM clin.Reservas WITH (UPDLOCK) WHERE ReservaId = @ReservaId;

        IF @Version IS NULL OR @Version <> @Actual
        BEGIN SELECT @Cod = 'CONFLICTO_EDICION', @Msg = N'La reserva fue modificada por otro usuario. Recarga la página.'; GOTO Rechazo; END
        IF NOT EXISTS (SELECT 1 FROM clin.TransicionesEstadoReserva WHERE EstadoOrigenId = @Estado AND EstadoDestinoId = @EstadoNuevo)
        BEGIN SELECT @Cod = 'ESTADO_INVALIDO', @Msg = N'Solo se pueden cerrar reservas confirmadas.'; GOTO Rechazo; END
        IF @Inicio > clin.fn_AhoraLocal()
        BEGIN SELECT @Cod = 'CITA_NO_INICIADA', @Msg = N'La cita aún no inicia.'; GOTO Rechazo; END

        UPDATE clin.Reservas
        SET EstadoReservaId = @EstadoNuevo, OcupaHorario = 1, CerradoPorUsuarioId = @ActorUsuarioId,
            FechaCierreUtc = SYSUTCDATETIME(), FechaModificacionUtc = SYSUTCDATETIME()
        WHERE ReservaId = @ReservaId;

        INSERT INTO clin.ReservaHistorial (ReservaId, EstadoAnteriorId, EstadoNuevoId, UsuarioActorId, Motivo, CorrelationId)
        VALUES (@ReservaId, @Estado, @EstadoNuevo, @ActorUsuarioId, @Nota, @CorrelationId);

        SELECT @Det = CodigoReserva FROM clin.Reservas WHERE ReservaId = @ReservaId;
        EXEC audit.usp_Registrar @ActorUsuarioId, @A, @E, @Id, 1, @Det, @Ip, @UserAgent, @CorrelationId;
        COMMIT;
        SET @Msg = CASE @EstadoNuevo WHEN 4 THEN N'Cita marcada como atendida.' ELSE N'Cita marcada como no asistió.' END;
        EXEC seg.usp_Resultado 1, @A, @Msg, @Id;
        RETURN;
    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0 ROLLBACK;
        EXEC seg.usp_ErrorCapturado @ActorUsuarioId, @A, @E, @Ip, @UserAgent, @CorrelationId;
        RETURN;
    END CATCH
Rechazo:
    IF @@TRANCOUNT > 0 ROLLBACK;
    EXEC seg.usp_Rechazo @ActorUsuarioId, @A, @E, @Id, @Cod, @Msg, @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_MedicoMarcarAtendida
    @ActorUsuarioId INT,
    @ReservaId      BIGINT,
    @Nota           NVARCHAR(250) = NULL,
    @VersionFila    VARCHAR(18),
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    EXEC clin.usp_ReservaCerrar @ActorUsuarioId, @ReservaId, 4, @Nota, @VersionFila, 'RESERVA_ATENDIDA', @Ip, @UserAgent, @CorrelationId;
END
GO

CREATE OR ALTER PROCEDURE api.usp_MedicoMarcarNoAsistio
    @ActorUsuarioId INT,
    @ReservaId      BIGINT,
    @Nota           NVARCHAR(250) = NULL,
    @VersionFila    VARCHAR(18),
    @Ip             VARCHAR(45)   = NULL,
    @UserAgent      NVARCHAR(300) = NULL,
    @CorrelationId  VARCHAR(64)   = NULL
AS
BEGIN
    SET NOCOUNT ON;
    EXEC clin.usp_ReservaCerrar @ActorUsuarioId, @ReservaId, 5, @Nota, @VersionFila, 'RESERVA_NO_ASISTIO', @Ip, @UserAgent, @CorrelationId;
END
GO

/* ============================================================================
   21. api — RESERVAS (consultas)
   ============================================================================ */
-- Detalle + banderas de acción para el actor. Result set vacío = no existe o no visible (404).
CREATE OR ALTER PROCEDURE api.usp_ReservaObtenerDetalle
    @ActorUsuarioId INT,
    @ReservaId      BIGINT
AS
BEGIN
    SET NOCOUNT ON;
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.propias') = 0 AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar') = 0
       AND seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.ver') = 0
        THROW 50403, N'SIN_PERMISO', 1;

    DECLARE @Ahora DATETIME2(0) = clin.fn_AhoraLocal(),
            @Personal BIT = seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar'),
            @Horas INT = seg.fn_ConfigEntero('HORAS_MINIMAS_CANCELACION', 2);

    SELECT v.*,
           CAST(CASE WHEN v.EstadoReservaId = 1 AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.cancelar') = 1
                      AND (v.PacienteUsuarioId = @ActorUsuarioId OR @Personal = 1)
                      AND clin.fn_FechaHora(v.FechaCita, v.HoraInicio) > CASE WHEN @Personal = 1 THEN @Ahora ELSE DATEADD(HOUR, @Horas, @Ahora) END
                     THEN 1 ELSE 0 END AS BIT) AS PuedeCancelar,
           CAST(CASE WHEN v.EstadoReservaId = 1 AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.reprogramar') = 1
                      AND (v.PacienteUsuarioId = @ActorUsuarioId OR @Personal = 1)
                      AND clin.fn_FechaHora(v.FechaCita, v.HoraInicio) > CASE WHEN @Personal = 1 THEN @Ahora ELSE DATEADD(HOUR, @Horas, @Ahora) END
                     THEN 1 ELSE 0 END AS BIT) AS PuedeReprogramar,
           CAST(CASE WHEN v.EstadoReservaId = 1 AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.estado') = 1
                      AND (v.MedicoUsuarioId = @ActorUsuarioId OR @Personal = 1)
                      AND clin.fn_FechaHora(v.FechaCita, v.HoraInicio) <= @Ahora
                     THEN 1 ELSE 0 END AS BIT) AS PuedeCerrar,
           @Horas AS HorasMinimasCancelacion
    FROM api.vw_ReservasDetalle v
    WHERE v.ReservaId = @ReservaId AND clin.fn_PuedeVerReserva(@ActorUsuarioId, @ReservaId) = 1;
END
GO

CREATE OR ALTER PROCEDURE api.usp_ReservaHistorial
    @ActorUsuarioId INT,
    @ReservaId      BIGINT
AS
BEGIN
    SET NOCOUNT ON;
    IF clin.fn_PuedeVerReserva(@ActorUsuarioId, @ReservaId) = 0
        THROW 50403, N'SIN_PERMISO', 1;

    SELECT h.ReservaHistorialId, h.ReservaId, h.EstadoAnteriorId, ea.Codigo AS EstadoAnteriorCodigo, ea.Nombre AS EstadoAnterior,
           h.EstadoNuevoId, en.Codigo AS EstadoNuevoCodigo, en.Nombre AS EstadoNuevo,
           u.NombreUsuario AS Actor, u.Nombres + N' ' + u.Apellidos AS ActorNombre, h.Motivo, h.FechaUtc
    FROM clin.ReservaHistorial h
    JOIN clin.EstadosReserva en ON en.EstadoReservaId = h.EstadoNuevoId
    LEFT JOIN clin.EstadosReserva ea ON ea.EstadoReservaId = h.EstadoAnteriorId
    JOIN seg.Usuarios u ON u.UsuarioId = h.UsuarioActorId
    WHERE h.ReservaId = @ReservaId
    ORDER BY h.FechaUtc, h.ReservaHistorialId;
END
GO

-- @Vista: PROXIMAS (confirmadas futuras) | HISTORIAL (resto) | TODAS
CREATE OR ALTER PROCEDURE api.usp_ReservaMisReservas
    @ActorUsuarioId  INT,
    @Vista           VARCHAR(10) = 'PROXIMAS',
    @EstadoReservaId TINYINT     = NULL,
    @Pagina          INT         = 1,
    @TamanoPagina    INT         = 10
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @PacienteId INT = (SELECT PacienteId FROM clin.Pacientes WHERE UsuarioId = @ActorUsuarioId);
    IF @PacienteId IS NULL OR seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.propias') = 0
        THROW 50403, N'SIN_PERMISO', 1;

    DECLARE @Ahora DATETIME2(0) = clin.fn_AhoraLocal(), @Horas INT = seg.fn_ConfigEntero('HORAS_MINIMAS_CANCELACION', 2);
    SET @Pagina = CASE WHEN @Pagina < 1 THEN 1 ELSE @Pagina END;
    SET @TamanoPagina = CASE WHEN @TamanoPagina BETWEEN 1 AND 50 THEN @TamanoPagina ELSE 10 END;

    SELECT v.ReservaId, v.CodigoReserva, v.MedicoNombre, v.Especialidad, v.EspecialidadId, v.Sede, v.SedeDireccion, v.Consultorio,
           v.TipoAtencion, v.FechaCita, v.HoraInicio, v.HoraFin, v.EstadoReservaId, v.EstadoCodigo, v.EstadoNombre,
           v.Observacion, v.MotivoCancelacion, v.CodigoReservaOrigen, v.CodigoReservaReemplazo, v.VersionFila,
           CAST(CASE WHEN v.EstadoReservaId = 1 AND clin.fn_FechaHora(v.FechaCita, v.HoraInicio) > DATEADD(HOUR, @Horas, @Ahora)
                     THEN 1 ELSE 0 END AS BIT) AS PuedeModificar,
           COUNT(*) OVER () AS TotalFilas
    FROM api.vw_ReservasDetalle v
    WHERE v.PacienteId = @PacienteId
      AND (@EstadoReservaId IS NULL OR v.EstadoReservaId = @EstadoReservaId)
      AND (   @Vista = 'TODAS'
           OR (@Vista = 'PROXIMAS' AND v.EstadoReservaId = 1 AND clin.fn_FechaHora(v.FechaCita, v.HoraFin) > @Ahora)
           OR (@Vista = 'HISTORIAL' AND NOT (v.EstadoReservaId = 1 AND clin.fn_FechaHora(v.FechaCita, v.HoraFin) > @Ahora)))
    ORDER BY
        CASE WHEN @Vista = 'PROXIMAS' THEN v.FechaCita END ASC,
        CASE WHEN @Vista = 'PROXIMAS' THEN v.HoraInicio END ASC,
        CASE WHEN @Vista <> 'PROXIMAS' THEN v.FechaCita END DESC,
        CASE WHEN @Vista <> 'PROXIMAS' THEN v.HoraInicio END DESC,
        v.ReservaId DESC
    OFFSET (@Pagina - 1) * @TamanoPagina ROWS FETCH NEXT @TamanoPagina ROWS ONLY;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AdminReservasBuscar
    @ActorUsuarioId  INT,
    @Texto           NVARCHAR(100) = NULL,
    @FechaDesde      DATE    = NULL,
    @FechaHasta      DATE    = NULL,
    @EstadoReservaId TINYINT = NULL,
    @MedicoId        INT     = NULL,
    @EspecialidadId  INT     = NULL,
    @SedeId          INT     = NULL,
    @PacienteId      INT     = NULL,
    @Pagina          INT     = 1,
    @TamanoPagina    INT     = 20
AS
BEGIN
    SET NOCOUNT ON;
    EXEC seg.usp_ExigirPermiso @ActorUsuarioId, 'reservas.gestionar';

    SET @Texto = NULLIF(LTRIM(RTRIM(@Texto)), N'');
    SET @Pagina = CASE WHEN @Pagina < 1 THEN 1 ELSE @Pagina END;
    SET @TamanoPagina = CASE WHEN @TamanoPagina BETWEEN 1 AND 100 THEN @TamanoPagina ELSE 20 END;

    DECLARE @Like NVARCHAR(110) = N'%' + REPLACE(REPLACE(REPLACE(@Texto, N'[', N'[[]'), N'%', N'[%]'), N'_', N'[_]') + N'%';

    SELECT v.ReservaId, v.CodigoReserva, v.PacienteId, v.PacienteNombre, v.PacienteDocumento, v.MedicoId, v.MedicoNombre,
           v.EspecialidadId, v.Especialidad, v.SedeId, v.Sede, v.Consultorio, v.TipoAtencion, v.FechaCita, v.HoraInicio, v.HoraFin,
           v.EstadoReservaId, v.EstadoCodigo, v.EstadoNombre, v.CreadoPor, v.FechaCreacionUtc, v.VersionFila,
           COUNT(*) OVER () AS TotalFilas
    FROM api.vw_ReservasDetalle v
    WHERE (@Texto IS NULL OR v.CodigoReserva LIKE @Like OR v.PacienteNombre LIKE @Like OR v.PacienteDocumento LIKE @Like OR v.MedicoNombre LIKE @Like)
      AND (@FechaDesde IS NULL OR v.FechaCita >= @FechaDesde)
      AND (@FechaHasta IS NULL OR v.FechaCita <= @FechaHasta)
      AND (@EstadoReservaId IS NULL OR v.EstadoReservaId = @EstadoReservaId)
      AND (@MedicoId IS NULL OR v.MedicoId = @MedicoId)
      AND (@EspecialidadId IS NULL OR v.EspecialidadId = @EspecialidadId)
      AND (@SedeId IS NULL OR v.SedeId = @SedeId)
      AND (@PacienteId IS NULL OR v.PacienteId = @PacienteId)
    ORDER BY v.FechaCita DESC, v.HoraInicio DESC, v.ReservaId DESC
    OFFSET (@Pagina - 1) * @TamanoPagina ROWS FETCH NEXT @TamanoPagina ROWS ONLY;
END
GO

CREATE OR ALTER PROCEDURE api.usp_MedicoReservasDia
    @ActorUsuarioId INT,
    @Fecha          DATE = NULL,
    @MedicoId       INT  = NULL
AS
BEGIN
    SET NOCOUNT ON;
    DECLARE @Propio INT = (SELECT MedicoId FROM clin.Medicos WHERE UsuarioId = @ActorUsuarioId),
            @Ahora DATETIME2(0) = clin.fn_AhoraLocal();
    SET @MedicoId = ISNULL(@MedicoId, @Propio);
    SET @Fecha = ISNULL(@Fecha, CAST(@Ahora AS DATE));
    IF @MedicoId IS NULL
        THROW 50403, N'SIN_PERMISO', 1;
    IF ISNULL(@Propio, 0) <> @MedicoId AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar') = 0
        THROW 50403, N'SIN_PERMISO', 1;
    IF ISNULL(@Propio, 0) = @MedicoId AND seg.fn_TienePermiso(@ActorUsuarioId, 'agenda.ver') = 0
        THROW 50403, N'SIN_PERMISO', 1;

    SELECT v.ReservaId, v.CodigoReserva, v.PacienteId, v.PacienteNombre, v.PacienteDocumento, v.Especialidad, v.Sede, v.Consultorio,
           v.TipoAtencion, v.FechaCita, v.HoraInicio, v.HoraFin, v.EstadoReservaId, v.EstadoCodigo, v.EstadoNombre, v.Observacion,
           v.VersionFila,
           CAST(CASE WHEN v.EstadoReservaId = 1 AND clin.fn_FechaHora(v.FechaCita, v.HoraInicio) <= @Ahora THEN 1 ELSE 0 END AS BIT) AS PuedeCerrar
    FROM api.vw_ReservasDetalle v
    WHERE v.MedicoId = @MedicoId AND v.FechaCita = @Fecha AND v.EstadoReservaId IN (1, 4, 5)
    ORDER BY v.HoraInicio;
END
GO

/* ============================================================================
   22. api — AUDITORÍA, DASHBOARD Y REPORTES (solo lectura)
   Las fechas de filtro son locales (America/Lima); la bitácora se guarda en UTC.
   ============================================================================ */
CREATE OR ALTER FUNCTION clin.fn_LocalAUtc (@Local DATETIME2(0))
RETURNS DATETIME2(0)
AS
BEGIN
    RETURN CONVERT(DATETIME2(0), @Local AT TIME ZONE 'SA Pacific Standard Time' AT TIME ZONE 'UTC');
END
GO

CREATE OR ALTER PROCEDURE api.usp_AuditoriaBuscar
    @ActorUsuarioId INT,
    @Texto          NVARCHAR(100) = NULL,
    @UsuarioId      INT           = NULL,
    @Accion         VARCHAR(50)   = NULL,
    @Exitoso        BIT           = NULL,
    @FechaDesde     DATE          = NULL,
    @FechaHasta     DATE          = NULL,
    @Pagina         INT           = 1,
    @TamanoPagina   INT           = 25,
    @Entidad        VARCHAR(50)   = NULL,
    @CorrelationIdFiltro VARCHAR(64) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    EXEC seg.usp_ExigirPermiso @ActorUsuarioId, 'auditoria.ver';

    SET @Entidad = NULLIF(LTRIM(RTRIM(@Entidad)), '');
    SET @CorrelationIdFiltro = NULLIF(LTRIM(RTRIM(@CorrelationIdFiltro)), '');

    SET @Texto = NULLIF(LTRIM(RTRIM(@Texto)), N'');
    SET @Accion = NULLIF(LTRIM(RTRIM(@Accion)), '');
    SET @Pagina = CASE WHEN @Pagina < 1 THEN 1 ELSE @Pagina END;
    SET @TamanoPagina = CASE WHEN @TamanoPagina BETWEEN 1 AND 100 THEN @TamanoPagina ELSE 25 END;
    SET @FechaHasta = ISNULL(@FechaHasta, CAST(clin.fn_AhoraLocal() AS DATE));
    SET @FechaDesde = ISNULL(@FechaDesde, DATEADD(DAY, -7, @FechaHasta));

    DECLARE @DesdeUtc DATETIME2(0) = clin.fn_LocalAUtc(CAST(@FechaDesde AS DATETIME2(0))),
            @HastaUtc DATETIME2(0) = clin.fn_LocalAUtc(CAST(DATEADD(DAY, 1, @FechaHasta) AS DATETIME2(0))),
            @Like NVARCHAR(110) = N'%' + REPLACE(REPLACE(REPLACE(@Texto, N'[', N'[[]'), N'%', N'[%]'), N'_', N'[_]') + N'%';

    SELECT BitacoraId, FechaUtc,
           CONVERT(DATETIME2(0), FechaUtc AT TIME ZONE 'UTC' AT TIME ZONE 'SA Pacific Standard Time') AS FechaLocal,
           UsuarioId, NombreUsuario, Accion, Entidad, EntidadId, Exitoso, Detalle, Ip, UserAgent, CorrelationId,
           COUNT(*) OVER () AS TotalFilas
    FROM api.vw_AuditoriaDetalle
    WHERE FechaUtc >= @DesdeUtc AND FechaUtc < @HastaUtc
      AND (@UsuarioId IS NULL OR UsuarioId = @UsuarioId)
      AND (@Accion IS NULL OR Accion = @Accion)
      AND (@Exitoso IS NULL OR Exitoso = @Exitoso)
      AND (@Entidad IS NULL OR Entidad = @Entidad)
      AND (@CorrelationIdFiltro IS NULL OR CorrelationId = @CorrelationIdFiltro)
      AND (@Texto IS NULL OR Detalle LIKE @Like OR NombreUsuario LIKE @Like OR CorrelationId LIKE @Like OR Accion LIKE @Like)
    ORDER BY FechaUtc DESC, BitacoraId DESC
    OFFSET (@Pagina - 1) * @TamanoPagina ROWS FETCH NEXT @TamanoPagina ROWS ONLY;
END
GO

CREATE OR ALTER PROCEDURE api.usp_AuditoriaAcciones
    @ActorUsuarioId INT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC seg.usp_ExigirPermiso @ActorUsuarioId, 'auditoria.ver';
    SELECT DISTINCT Accion FROM audit.BitacoraSistema ORDER BY Accion;
END
GO

-- KPIs del panel (una fila). La serie diaria está en api.usp_DashboardSerieDiaria (un result set por SP).
CREATE OR ALTER PROCEDURE api.usp_DashboardResumen
    @ActorUsuarioId INT
AS
BEGIN
    SET NOCOUNT ON;
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reportes.ver') = 0 AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar') = 0
        THROW 50403, N'SIN_PERMISO', 1;

    DECLARE @Ahora DATETIME2(0) = clin.fn_AhoraLocal();
    DECLARE @Hoy DATE = CAST(@Ahora AS DATE);
    DECLARE @InicioMesUtc DATETIME2(0) = clin.fn_LocalAUtc(CAST(DATEFROMPARTS(YEAR(@Hoy), MONTH(@Hoy), 1) AS DATETIME2(0)));

    SELECT
        (SELECT COUNT(*) FROM clin.Reservas WHERE FechaCita = @Hoy AND EstadoReservaId IN (1, 4, 5)) AS CitasHoy,
        (SELECT COUNT(*) FROM clin.Reservas WHERE FechaCita = @Hoy AND EstadoReservaId = 1) AS PendientesHoy,
        (SELECT COUNT(*) FROM clin.Reservas WHERE FechaCita = @Hoy AND EstadoReservaId = 4) AS AtendidasHoy,
        (SELECT COUNT(*) FROM clin.Reservas WHERE FechaCita = @Hoy AND EstadoReservaId = 5) AS NoAsistioHoy,
        (SELECT COUNT(*) FROM clin.Reservas WHERE FechaCita > @Hoy AND FechaCita <= DATEADD(DAY, 7, @Hoy) AND EstadoReservaId = 1) AS ProximosSieteDias,
        (SELECT COUNT(*) FROM clin.Reservas WHERE EstadoReservaId = 2 AND FechaCancelacionUtc >= @InicioMesUtc) AS CanceladasMes,
        (SELECT COUNT(*) FROM clin.Reservas WHERE FechaCreacionUtc >= @InicioMesUtc) AS CreadasMes,
        (SELECT COUNT(*) FROM clin.Pacientes WHERE Activo = 1) AS PacientesActivos,
        (SELECT COUNT(*) FROM clin.Medicos WHERE Activo = 1) AS MedicosActivos,
        (SELECT COUNT(*) FROM clin.fn_HorariosReservables(@Ahora, @Hoy)) AS SlotsLibresHoy,
        (SELECT COUNT(*) FROM clin.HorariosMedicos h JOIN clin.AgendasMedicas a ON a.AgendaId = h.AgendaId
         WHERE a.Fecha = @Hoy AND a.Activo = 1 AND h.Activo = 1) AS SlotsTotalesHoy;
END
GO

-- Serie diaria de 15 días (7 atrás, hoy, 7 adelante) para el gráfico del panel. Un único result set.
CREATE OR ALTER PROCEDURE api.usp_DashboardSerieDiaria
    @ActorUsuarioId INT
AS
BEGIN
    SET NOCOUNT ON;
    IF seg.fn_TienePermiso(@ActorUsuarioId, 'reportes.ver') = 0 AND seg.fn_TienePermiso(@ActorUsuarioId, 'reservas.gestionar') = 0
        THROW 50403, N'SIN_PERMISO', 1;

    DECLARE @Hoy DATE = CAST(clin.fn_AhoraLocal() AS DATE);

    ;WITH d AS
    (
        SELECT TOP (15) DATEADD(DAY, CAST(ROW_NUMBER() OVER (ORDER BY (SELECT NULL)) AS INT) - 8, @Hoy) AS Fecha FROM sys.all_objects
    )
    SELECT d.Fecha,
           SUM(CASE WHEN r.EstadoReservaId IN (1, 4, 5) THEN 1 ELSE 0 END) AS Activas,
           SUM(CASE WHEN r.EstadoReservaId = 2 THEN 1 ELSE 0 END) AS Canceladas,
           SUM(CASE WHEN r.EstadoReservaId = 5 THEN 1 ELSE 0 END) AS NoAsistio
    FROM d LEFT JOIN clin.Reservas r ON r.FechaCita = d.Fecha
    GROUP BY d.Fecha
    ORDER BY d.Fecha;
END
GO

-- Valida permiso y normaliza rango (máx. 366 días). Uso interno de reportes.
CREATE OR ALTER PROCEDURE clin.usp_ReporteRango
    @ActorUsuarioId INT,
    @FechaDesde     DATE OUTPUT,
    @FechaHasta     DATE OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    EXEC seg.usp_ExigirPermiso @ActorUsuarioId, 'reportes.ver';
    SET @FechaHasta = ISNULL(@FechaHasta, CAST(clin.fn_AhoraLocal() AS DATE));
    SET @FechaDesde = ISNULL(@FechaDesde, DATEADD(DAY, -30, @FechaHasta));
    IF @FechaDesde > @FechaHasta
    BEGIN
        DECLARE @Tmp DATE = @FechaDesde;
        SELECT @FechaDesde = @FechaHasta, @FechaHasta = @Tmp;
    END
    IF DATEDIFF(DAY, @FechaDesde, @FechaHasta) > 366
        SET @FechaDesde = DATEADD(DAY, -366, @FechaHasta);
END
GO

CREATE OR ALTER PROCEDURE api.usp_ReporteReservasPorEspecialidad
    @ActorUsuarioId INT,
    @FechaDesde     DATE = NULL,
    @FechaHasta     DATE = NULL,
    @SedeId         INT  = NULL
AS
BEGIN
    SET NOCOUNT ON;
    EXEC clin.usp_ReporteRango @ActorUsuarioId, @FechaDesde OUTPUT, @FechaHasta OUTPUT;

    SELECT e.EspecialidadId, e.Nombre AS Especialidad,
           COUNT(r.ReservaId) AS Total,
           SUM(CASE WHEN r.EstadoReservaId = 1 THEN 1 ELSE 0 END) AS Confirmadas,
           SUM(CASE WHEN r.EstadoReservaId = 4 THEN 1 ELSE 0 END) AS Atendidas,
           SUM(CASE WHEN r.EstadoReservaId = 5 THEN 1 ELSE 0 END) AS NoAsistio,
           SUM(CASE WHEN r.EstadoReservaId = 2 THEN 1 ELSE 0 END) AS Canceladas,
           SUM(CASE WHEN r.EstadoReservaId = 3 THEN 1 ELSE 0 END) AS Reprogramadas
    FROM clin.Especialidades e
    LEFT JOIN clin.Reservas r ON r.EspecialidadId = e.EspecialidadId
         AND r.FechaCita BETWEEN @FechaDesde AND @FechaHasta
         AND (@SedeId IS NULL OR r.SedeId = @SedeId)
    GROUP BY e.EspecialidadId, e.Nombre, e.Activo
    HAVING COUNT(r.ReservaId) > 0 OR e.Activo = 1
    ORDER BY Total DESC, e.Nombre;
END
GO

-- Ocupación = slots ocupados (confirmada/atendida/no asistió) / slots activos no bloqueados.
CREATE OR ALTER PROCEDURE api.usp_ReporteOcupacionMedicos
    @ActorUsuarioId INT,
    @FechaDesde     DATE = NULL,
    @FechaHasta     DATE = NULL,
    @EspecialidadId INT  = NULL,
    @SedeId         INT  = NULL
AS
BEGIN
    SET NOCOUNT ON;
    EXEC clin.usp_ReporteRango @ActorUsuarioId, @FechaDesde OUTPUT, @FechaHasta OUTPUT;

    ;WITH s AS
    (
        SELECT a.MedicoId,
               COUNT(*) AS SlotsTotales,
               SUM(CASE WHEN h.Bloqueado = 1 AND r.ReservaId IS NULL THEN 1 ELSE 0 END) AS SlotsBloqueados,
               SUM(CASE WHEN r.ReservaId IS NOT NULL THEN 1 ELSE 0 END) AS SlotsOcupados,
               SUM(CASE WHEN r.EstadoReservaId = 4 THEN 1 ELSE 0 END) AS Atendidas,
               SUM(CASE WHEN r.EstadoReservaId = 5 THEN 1 ELSE 0 END) AS NoAsistio
        FROM clin.AgendasMedicas a
        JOIN clin.HorariosMedicos h ON h.AgendaId = a.AgendaId AND h.Activo = 1
        LEFT JOIN clin.Reservas r   ON r.HorarioMedicoId = h.HorarioMedicoId AND r.OcupaHorario = 1
        WHERE a.Activo = 1 AND a.Fecha BETWEEN @FechaDesde AND @FechaHasta
          AND (@EspecialidadId IS NULL OR a.EspecialidadId = @EspecialidadId)
          AND (@SedeId IS NULL OR a.SedeId = @SedeId)
        GROUP BY a.MedicoId
    )
    SELECT m.MedicoId, m.Nombres + N' ' + m.Apellidos AS MedicoNombre, m.CMP,
           s.SlotsTotales, s.SlotsBloqueados, s.SlotsOcupados, s.Atendidas, s.NoAsistio,
           CAST(CASE WHEN s.SlotsTotales - s.SlotsBloqueados = 0 THEN 0
                     ELSE 100.0 * s.SlotsOcupados / (s.SlotsTotales - s.SlotsBloqueados) END AS DECIMAL(5, 1)) AS OcupacionPct
    FROM s JOIN clin.Medicos m ON m.MedicoId = s.MedicoId
    ORDER BY OcupacionPct DESC, MedicoNombre;
END
GO

CREATE OR ALTER PROCEDURE api.usp_ReporteCancelaciones
    @ActorUsuarioId INT,
    @FechaDesde     DATE = NULL,
    @FechaHasta     DATE = NULL,
    @EspecialidadId INT  = NULL
AS
BEGIN
    SET NOCOUNT ON;
    EXEC clin.usp_ReporteRango @ActorUsuarioId, @FechaDesde OUTPUT, @FechaHasta OUTPUT;

    SELECT mc.MotivoCancelacionId, mc.Nombre AS Motivo, mc.SoloPersonal,
           COUNT(*) AS Total,
           SUM(CASE WHEN r.CanceladoPorUsuarioId = p.UsuarioId THEN 1 ELSE 0 END) AS PorPaciente,
           SUM(CASE WHEN r.CanceladoPorUsuarioId = p.UsuarioId THEN 0 ELSE 1 END) AS PorPersonal,
           CAST(AVG(DATEDIFF(MINUTE, CONVERT(DATETIME2(0), r.FechaCancelacionUtc AT TIME ZONE 'UTC' AT TIME ZONE 'SA Pacific Standard Time'),
                             clin.fn_FechaHora(r.FechaCita, r.HoraInicio)) / 60.0) AS DECIMAL(10, 1)) AS HorasAnticipacionPromedio
    FROM clin.Reservas r
    JOIN clin.MotivosCancelacion mc ON mc.MotivoCancelacionId = r.MotivoCancelacionId
    JOIN clin.Pacientes p ON p.PacienteId = r.PacienteId
    WHERE r.EstadoReservaId = 2 AND r.FechaCita BETWEEN @FechaDesde AND @FechaHasta
      AND (@EspecialidadId IS NULL OR r.EspecialidadId = @EspecialidadId)
    GROUP BY mc.MotivoCancelacionId, mc.Nombre, mc.SoloPersonal
    ORDER BY Total DESC;
END
GO

-- Tasa de no asistencia = NO_ASISTIO / (ATENDIDA + NO_ASISTIO), por especialidad.
CREATE OR ALTER PROCEDURE api.usp_ReporteNoAsistencia
    @ActorUsuarioId INT,
    @FechaDesde     DATE = NULL,
    @FechaHasta     DATE = NULL,
    @SedeId         INT  = NULL
AS
BEGIN
    SET NOCOUNT ON;
    EXEC clin.usp_ReporteRango @ActorUsuarioId, @FechaDesde OUTPUT, @FechaHasta OUTPUT;

    SELECT e.EspecialidadId, e.Nombre AS Especialidad,
           SUM(CASE WHEN r.EstadoReservaId = 4 THEN 1 ELSE 0 END) AS Atendidas,
           SUM(CASE WHEN r.EstadoReservaId = 5 THEN 1 ELSE 0 END) AS NoAsistio,
           CAST(100.0 * SUM(CASE WHEN r.EstadoReservaId = 5 THEN 1 ELSE 0 END) / COUNT(*) AS DECIMAL(5, 1)) AS TasaNoAsistenciaPct
    FROM clin.Reservas r
    JOIN clin.Especialidades e ON e.EspecialidadId = r.EspecialidadId
    WHERE r.EstadoReservaId IN (4, 5) AND r.FechaCita BETWEEN @FechaDesde AND @FechaHasta
      AND (@SedeId IS NULL OR r.SedeId = @SedeId)
    GROUP BY e.EspecialidadId, e.Nombre
    ORDER BY TasaNoAsistenciaPct DESC, e.Nombre;
END
GO

/* ============================================================================
   23. PERMISOS DE BASE DE DATOS (mínimo privilegio)
   La aplicación solo puede EJECUTAR procedimientos del esquema api y LEER vistas api.
   Sin acceso directo a seg/clin/audit: el encadenamiento de propiedad (dbo) permite
   que los procedimientos api lean/escriban las tablas.
   ============================================================================ */
IF DATABASE_PRINCIPAL_ID(N'nexa_app_role') IS NULL
    CREATE ROLE nexa_app_role AUTHORIZATION dbo;
GO
GRANT EXECUTE ON SCHEMA::api TO nexa_app_role;
GRANT SELECT  ON SCHEMA::api TO nexa_app_role;
DENY  SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::seg   TO nexa_app_role;
DENY  SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::clin  TO nexa_app_role;
DENY  SELECT, INSERT, UPDATE, DELETE, EXECUTE ON SCHEMA::audit TO nexa_app_role;
GO

/* Producción (NO se ejecuta automáticamente; definir credenciales en el servidor, nunca en el repositorio):

   -- Opción A: cuenta Windows / gMSA del servicio web (recomendada)
   CREATE LOGIN [DOMINIO\svc-nexa-web] FROM WINDOWS;
   -- Opción B: login SQL
   CREATE LOGIN nexa_app WITH PASSWORD = N'<definir-en-servidor>', CHECK_POLICY = ON;

   USE ReservasMedicasWeb;
   CREATE USER nexa_app FOR LOGIN nexa_app;
   ALTER ROLE nexa_app_role ADD MEMBER nexa_app;
*/
GO
