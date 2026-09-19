<?php

namespace App\Services;

use App\DTO\ProcedureResult;
use App\Models\User;
use App\Repositories\Contracts\CatalogRepositoryInterface;

/** Catálogos de apoyo para filtros y formularios (vistas api.vw_*). */
final class CatalogService
{
    public function __construct(private readonly CatalogRepositoryInterface $catalogs) {}

    /** @return list<array<string, mixed>> */
    public function specialties(bool $onlyActive = true): array
    {
        return $this->catalogs->specialties($onlyActive);
    }

    /** @return list<array<string, mixed>> */
    public function branches(bool $onlyActive = true): array
    {
        return $this->catalogs->branches($onlyActive);
    }

    /** @return list<array<string, mixed>> */
    public function rooms(?int $branchId = null, bool $onlyActive = true): array
    {
        return $this->catalogs->rooms($branchId, $onlyActive);
    }

    /** @return list<array<string, mixed>> */
    public function careTypes(): array
    {
        return $this->catalogs->careTypes();
    }

    /** @return list<array<string, mixed>> */
    public function statuses(): array
    {
        return $this->catalogs->reservationStatuses();
    }

    /** @return list<array<string, mixed>> */
    public function configuration(): array
    {
        return $this->catalogs->configuration();
    }

    /** @param array<string, mixed> $data */
    public function saveRoom(User $actor, ?int $id, array $data): ProcedureResult
    {
        return $this->catalogs->saveRoom($actor->id(), $id, $data)->throwIfFailed();
    }

    public function updateConfiguration(User $actor, string $key, string $value): ProcedureResult
    {
        return $this->catalogs->updateConfiguration($actor->id(), $key, $value)->throwIfFailed();
    }

    /** Opciones id => nombre para selects. */
    public static function options(array $rows, string $idKey, string $labelKey = 'Nombre'): array
    {
        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row[$idKey]] = (string) $row[$labelKey];
        }

        return $options;
    }
}
