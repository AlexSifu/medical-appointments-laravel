<?php

namespace App\Repositories\SqlServer;

use App\DTO\ProcedureResult;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Support\Database\StoredProcedureExecutor;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Catálogos. Solo especialidades y sedes se cachean (cambian poco y no son datos sensibles);
 * la caché se invalida al guardarlas por SP.
 */
final class SqlServerCatalogRepository extends SqlServerRepository implements CatalogRepositoryInterface
{
    private const VIEW_SPECIALTIES = 'api.vw_Especialidades';

    private const VIEW_BRANCHES = 'api.vw_Sedes';

    private const VIEW_ROOMS = 'api.vw_Consultorios';

    private const VIEW_CARE_TYPES = 'api.vw_TiposAtencion';

    private const VIEW_CANCEL_REASONS = 'api.vw_MotivosCancelacion';

    private const VIEW_STATUSES = 'api.vw_EstadosReserva';

    private const VIEW_CONFIGURATION = 'api.vw_Configuracion';

    private const SAVE_SPECIALTY = 'api.usp_AdminEspecialidadGuardar';

    private const SAVE_BRANCH = 'api.usp_AdminSedeGuardar';

    private const SAVE_ROOM = 'api.usp_AdminConsultorioGuardar';

    private const UPDATE_CONFIGURATION = 'api.usp_AdminConfiguracionActualizar';

    private const CACHE_SPECIALTIES = 'catalog.specialties';

    private const CACHE_BRANCHES = 'catalog.branches';

    private const CACHE_TTL = 600;

    public function __construct(StoredProcedureExecutor $sql, private readonly Cache $cache)
    {
        parent::__construct($sql);
    }

    public function specialties(bool $onlyActive = true): array
    {
        $all = $this->cache->remember(self::CACHE_SPECIALTIES, self::CACHE_TTL,
            fn () => $this->sql->view(self::VIEW_SPECIALTIES, [], 'Nombre'));

        return $onlyActive ? self::active($all) : $all;
    }

    public function branches(bool $onlyActive = true): array
    {
        $all = $this->cache->remember(self::CACHE_BRANCHES, self::CACHE_TTL,
            fn () => $this->sql->view(self::VIEW_BRANCHES, [], 'Nombre'));

        return $onlyActive ? self::active($all) : $all;
    }

    public function rooms(?int $branchId = null, bool $onlyActive = true): array
    {
        $filters = $branchId !== null ? ['SedeId' => $branchId] : [];
        $rows = $this->sql->view(self::VIEW_ROOMS, $filters, 'Sede, Codigo');

        return $onlyActive ? self::active($rows) : $rows;
    }

    public function careTypes(): array
    {
        return $this->sql->view(self::VIEW_CARE_TYPES, [], 'TipoAtencionId');
    }

    public function cancellationReasons(bool $includeStaffOnly): array
    {
        $rows = $this->sql->view(self::VIEW_CANCEL_REASONS, [], 'MotivoCancelacionId');

        return $includeStaffOnly ? $rows : array_values(array_filter($rows, static fn (array $r): bool => ! $r['SoloPersonal']));
    }

    public function reservationStatuses(): array
    {
        return $this->sql->view(self::VIEW_STATUSES, [], 'EstadoReservaId');
    }

    public function configuration(): array
    {
        return $this->sql->view(self::VIEW_CONFIGURATION, [], 'Clave');
    }

    public function saveSpecialty(int $actorId, ?int $id, array $data): ProcedureResult
    {
        $result = $this->sql->command(self::SAVE_SPECIALTY, [
            'ActorUsuarioId' => $actorId,
            'EspecialidadId' => $id,
            'Nombre' => $data['name'],
            'Descripcion' => $data['description'] ?? null,
            'Activo' => (bool) ($data['active'] ?? true),
        ]);
        $this->forgetCache();

        return $result;
    }

    public function saveBranch(int $actorId, ?int $id, array $data): ProcedureResult
    {
        $result = $this->sql->command(self::SAVE_BRANCH, [
            'ActorUsuarioId' => $actorId,
            'SedeId' => $id,
            'Codigo' => $data['code'],
            'Nombre' => $data['name'],
            'Direccion' => $data['address'] ?? null,
            'Activo' => (bool) ($data['active'] ?? true),
        ]);
        $this->forgetCache();

        return $result;
    }

    public function saveRoom(int $actorId, ?int $id, array $data): ProcedureResult
    {
        return $this->sql->command(self::SAVE_ROOM, [
            'ActorUsuarioId' => $actorId,
            'ConsultorioId' => $id,
            'SedeId' => (int) $data['branch_id'],
            'Codigo' => $data['code'],
            'Nombre' => $data['name'],
            'Piso' => isset($data['floor']) ? (int) $data['floor'] : null,
            'Activo' => (bool) ($data['active'] ?? true),
        ]);
    }

    public function updateConfiguration(int $actorId, string $key, string $value): ProcedureResult
    {
        return $this->sql->command(self::UPDATE_CONFIGURATION, [
            'ActorUsuarioId' => $actorId,
            'Clave' => $key,
            'Valor' => $value,
        ]);
    }

    public function forgetCache(): void
    {
        $this->cache->forget(self::CACHE_SPECIALTIES);
        $this->cache->forget(self::CACHE_BRANCHES);
    }

    /** @return list<array<string, mixed>> */
    private static function active(array $rows): array
    {
        return array_values(array_filter($rows, static fn (array $r): bool => (bool) ($r['Activo'] ?? true)));
    }
}
