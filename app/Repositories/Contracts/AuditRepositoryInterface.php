<?php

namespace App\Repositories\Contracts;

use App\DTO\AuditEntryData;
use App\DTO\PagedResult;

interface AuditRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters  Texto, UsuarioId, Accion, Exitoso, FechaDesde, FechaHasta, Entidad, CorrelationIdFiltro
     * @return PagedResult<AuditEntryData>
     */
    public function search(int $actorId, array $filters, int $page, int $perPage): PagedResult;

    /** @return list<string> */
    public function actions(int $actorId): array;
}
