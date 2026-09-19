<?php

namespace App\Services;

use App\DTO\AuditEntryData;
use App\DTO\PagedResult;
use App\Models\User;
use App\Repositories\Contracts\AuditRepositoryInterface;

/** Consulta de la bitácora (solo lectura: la bitácora se escribe únicamente desde los SP). */
final class AuditService
{
    public const ENTITIES = [
        'Agenda', 'Bloqueo', 'Configuracion', 'Consultorio', 'Especialidad', 'Medico',
        'Paciente', 'PlantillaAgenda', 'Reserva', 'Sede', 'Usuario',
    ];

    public function __construct(private readonly AuditRepositoryInterface $audit) {}

    /**
     * @param  array<string, mixed>  $filters  validados por AuditFilterRequest
     * @return PagedResult<AuditEntryData>
     */
    public function search(User $actor, array $filters, int $page, int $perPage = 25): PagedResult
    {
        $success = $filters['result'] ?? null;

        return $this->audit->search($actor->id(), [
            'Texto' => $filters['q'] ?? null,
            'UsuarioId' => $filters['user_id'] ?? null,
            'Accion' => $filters['action'] ?? null,
            'Exitoso' => $success === 'ok' ? true : ($success === 'error' ? false : null),
            'FechaDesde' => $filters['date_from'] ?? null,
            'FechaHasta' => $filters['date_to'] ?? null,
            'Entidad' => $filters['entity'] ?? null,
            'CorrelationIdFiltro' => $filters['correlation_id'] ?? null,
        ], $page, $perPage);
    }

    /** @return list<string> */
    public function actions(User $actor): array
    {
        return $this->audit->actions($actor->id());
    }
}
