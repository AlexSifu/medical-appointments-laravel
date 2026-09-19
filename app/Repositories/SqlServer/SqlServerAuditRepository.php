<?php

namespace App\Repositories\SqlServer;

use App\DTO\AuditEntryData;
use App\DTO\PagedResult;
use App\Repositories\Contracts\AuditRepositoryInterface;

final class SqlServerAuditRepository extends SqlServerRepository implements AuditRepositoryInterface
{
    private const SEARCH = 'api.usp_AuditoriaBuscar';

    private const ACTIONS = 'api.usp_AuditoriaAcciones';

    private const FILTERS = ['Texto', 'UsuarioId', 'Accion', 'Exitoso', 'FechaDesde', 'FechaHasta', 'Entidad', 'CorrelationIdFiltro'];

    public function search(int $actorId, array $filters, int $page, int $perPage): PagedResult
    {
        $params = ['ActorUsuarioId' => $actorId];
        foreach (self::FILTERS as $name) {
            $value = $filters[$name] ?? null;
            $params[$name] = $value === '' ? null : $value;
        }
        $params['Texto'] = self::text($params['Texto']);

        return $this->paged(self::SEARCH, $params, $page, $perPage, AuditEntryData::fromRow(...));
    }

    public function actions(int $actorId): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['Accion'],
            $this->sql->select(self::ACTIONS, ['ActorUsuarioId' => $actorId]),
        );
    }
}
