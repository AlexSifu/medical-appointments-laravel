/* ============================================================================
   Nexa Salud — ReservasMedicasWeb_RESET_DEV.sql

   ##########################################################################
   ##   DESTRUCTIVO — SOLO DESARROLLO                                      ##
   ##   Elimina TODOS los objetos y datos de los esquemas api/clin/seg/audit ##
   ##   de la base de datos ACTUAL. No borra la base de datos.             ##
   ##   Nunca se ejecuta automáticamente (setup.ps1 no lo llama).          ##
   ##########################################################################

   Salvaguardas:
     1. Exige confirmación explícita:  sqlcmd ... -v CONFIRMAR_RESET="SI"
        (sin la variable, sqlcmd aborta antes de ejecutar nada).
     2. Solo actúa sobre ReservasMedicasWeb o ReservasMedicasWeb_Test.
     3. Jamás toca ReservasMedicasDB (legacy VB.NET).

   Uso (desarrollo):
     sqlcmd -S localhost -E -d ReservasMedicasWeb -b -v CONFIRMAR_RESET="SI" -i ReservasMedicasWeb_RESET_DEV.sql
     sqlcmd -S localhost -E -d ReservasMedicasWeb -b -i ReservasMedicasWeb_MASTER.sql
     php artisan clinic:seed-demo
   ============================================================================ */
:on error exit
GO
SET NOCOUNT ON;
IF '$(CONFIRMAR_RESET)' <> 'SI'
    THROW 51000, 'RESET_DEV cancelado: ejecute con -v CONFIRMAR_RESET="SI" (DESTRUCTIVO / SOLO DESARROLLO).', 1;
IF DB_NAME() NOT IN (N'ReservasMedicasWeb', N'ReservasMedicasWeb_Test')
    THROW 51001, 'RESET_DEV solo puede ejecutarse sobre ReservasMedicasWeb o ReservasMedicasWeb_Test.', 1;
GO

PRINT CONCAT('RESET_DEV sobre ', DB_NAME(), ' — ', CONVERT(VARCHAR(19), SYSDATETIME(), 120));

DECLARE @sql NVARCHAR(MAX);

-- 1. Claves foráneas
SET @sql = N'';
SELECT @sql += N'ALTER TABLE ' + QUOTENAME(s.name) + N'.' + QUOTENAME(t.name) + N' DROP CONSTRAINT ' + QUOTENAME(fk.name) + N';' + CHAR(10)
FROM sys.foreign_keys fk
JOIN sys.tables t  ON t.object_id = fk.parent_object_id
JOIN sys.schemas s ON s.schema_id = t.schema_id
WHERE s.name IN (N'api', N'clin', N'seg', N'audit');
EXEC sys.sp_executesql @sql;

-- 2. Procedimientos, vistas, funciones
SET @sql = N'';
SELECT @sql += N'DROP ' + CASE o.type WHEN 'P' THEN N'PROCEDURE' WHEN 'V' THEN N'VIEW' ELSE N'FUNCTION' END
             + N' ' + QUOTENAME(s.name) + N'.' + QUOTENAME(o.name) + N';' + CHAR(10)
FROM sys.objects o
JOIN sys.schemas s ON s.schema_id = o.schema_id
WHERE s.name IN (N'api', N'clin', N'seg', N'audit')
  AND o.type IN ('P', 'V', 'FN', 'IF', 'TF')
ORDER BY CASE o.type WHEN 'P' THEN 1 WHEN 'V' THEN 2 ELSE 3 END;
EXEC sys.sp_executesql @sql;

-- 3. Tablas
SET @sql = N'';
SELECT @sql += N'DROP TABLE ' + QUOTENAME(s.name) + N'.' + QUOTENAME(t.name) + N';' + CHAR(10)
FROM sys.tables t
JOIN sys.schemas s ON s.schema_id = t.schema_id
WHERE s.name IN (N'api', N'clin', N'seg', N'audit');
EXEC sys.sp_executesql @sql;

-- 4. Secuencias (si existieran)
SET @sql = N'';
SELECT @sql += N'DROP SEQUENCE ' + QUOTENAME(s.name) + N'.' + QUOTENAME(q.name) + N';' + CHAR(10)
FROM sys.sequences q
JOIN sys.schemas s ON s.schema_id = q.schema_id
WHERE s.name IN (N'api', N'clin', N'seg', N'audit');
EXEC sys.sp_executesql @sql;
GO

-- 5. Esquemas y rol de aplicación
IF SCHEMA_ID(N'api')   IS NOT NULL EXEC (N'DROP SCHEMA api');
IF SCHEMA_ID(N'clin')  IS NOT NULL EXEC (N'DROP SCHEMA clin');
IF SCHEMA_ID(N'seg')   IS NOT NULL EXEC (N'DROP SCHEMA seg');
IF SCHEMA_ID(N'audit') IS NOT NULL EXEC (N'DROP SCHEMA audit');
IF DATABASE_PRINCIPAL_ID(N'nexa_app_role') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.database_role_members WHERE role_principal_id = DATABASE_PRINCIPAL_ID(N'nexa_app_role'))
    DROP ROLE nexa_app_role;
GO
PRINT 'RESET_DEV completado. Ejecute ahora ReservasMedicasWeb_MASTER.sql y php artisan clinic:seed-demo.';
GO
