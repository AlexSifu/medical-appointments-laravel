<?php

namespace Tests\Integration;

use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Services\DemoSeedService;
use Illuminate\Support\Str;

/** Idempotencia, control optimista y permisos resueltos por los procedimientos api.usp_*. */
final class ReservationRulesTest extends IntegrationTestCase
{
    private function repo(): ReservationRepositoryInterface
    {
        return app(ReservationRepositoryInterface::class);
    }

    public function test_same_idempotency_key_returns_the_same_reservation(): void
    {
        $reception = $this->userId('reception');
        [$patientId] = $this->patientIds(1);
        $slot = $this->freeSlot();
        $key = (string) Str::uuid();

        $first = $this->repo()->createForPatient($reception, $patientId, $slot->id, null, $key);
        $retry = $this->repo()->createForPatient($reception, $patientId, $slot->id, null, $key);

        $this->assertTrue($first->success, $first->code);
        $this->assertTrue($retry->success, $retry->code);
        $this->assertSame('RESERVA_EXISTENTE', $retry->code);
        $this->assertSame($first->entityId, $retry->entityId);

        // Misma clave con otros datos: se rechaza, no crea otra reserva.
        $other = $this->freeSlot([$slot->id]);
        $conflict = $this->repo()->createForPatient($reception, $patientId, $other->id, null, $key);
        $this->assertFalse($conflict->success);
        $this->assertSame('IDEMPOTENCIA_CONFLICTO', $conflict->code);

        $r = $this->repo()->find($reception, $first->entityId);
        $this->repo()->cancel($reception, $r->id, 1, 'Limpieza de prueba', $r->version);
    }

    public function test_patient_reschedules_own_reservation_and_old_slot_is_released(): void
    {
        $patient = $this->userId('patient');
        $reception = $this->userId('reception');
        $slot = $this->freeSlot();
        $target = $this->freeSlot([$slot->id]);

        $created = $this->repo()->create($patient, null, $slot->id, 'Control', (string) Str::uuid());
        $this->assertTrue($created->success, $created->code);
        $original = $this->repo()->find($patient, $created->entityId);

        $moved = $this->repo()->reschedule($patient, $original->id, $target->id, 'Cambio de horario', $original->version, (string) Str::uuid());
        $this->assertTrue($moved->success, $moved->code);
        $this->assertNotSame($original->id, $moved->entityId);

        $old = $this->repo()->find($patient, $original->id);
        $new = $this->repo()->find($patient, $moved->entityId);
        $this->assertSame('REPROGRAMADA', $old->statusCode);
        $this->assertSame($moved->entityId, $old->replacementId);
        $this->assertSame('CONFIRMADA', $new->statusCode);
        $this->assertSame($target->id, $new->slotId);
        $this->assertSame($old->code, $new->originCode);

        // El horario original quedó libre: otra persona puede tomarlo.
        [, $otherPatient] = $this->patientIds(2);
        $retake = $this->repo()->createForPatient($reception, $otherPatient, $slot->id, null, (string) Str::uuid());
        $this->assertTrue($retake->success, $retake->code);

        // Una reserva REPROGRAMADA es final: no se puede reprogramar de nuevo.
        $again = $this->repo()->reschedule($patient, $old->id, $this->freeSlot([$slot->id, $target->id])->id, null, $old->version, (string) Str::uuid());
        $this->assertFalse($again->success);

        foreach ([[$patient, $new->id], [$reception, $retake->entityId]] as [$actor, $id]) {
            $r = $this->repo()->find($actor, $id);
            $this->repo()->cancel($actor, $r->id, 1, 'Limpieza de prueba', $r->version);
        }
    }

    public function test_stale_version_is_rejected_on_cancel(): void
    {
        $reception = $this->userId('reception');
        [$patientId] = $this->patientIds(1);
        $slot = $this->freeSlot();

        $created = $this->repo()->createForPatient($reception, $patientId, $slot->id, null, (string) Str::uuid());
        $this->assertTrue($created->success, $created->code);
        $stale = $this->repo()->find($reception, $created->entityId)->version;

        $this->assertTrue($this->repo()->cancel($reception, $created->entityId, 1, null, $stale)->success);

        $again = $this->repo()->cancel($reception, $created->entityId, 1, null, $stale);
        $this->assertFalse($again->success);
        $this->assertSame('CONFLICTO_EDICION', $again->code);
    }

    public function test_sql_denies_operations_outside_the_actor_permissions(): void
    {
        [, $otherPatient] = $this->patientIds(2);
        $slot = $this->freeSlot();

        // El paciente no puede reservar para otro paciente (aunque llame directo al SP).
        $asPatient = $this->repo()->createForPatient($this->userId('patient'), $otherPatient, $slot->id, null, (string) Str::uuid());
        $this->assertFalse($asPatient->success);
        $this->assertSame('SIN_PERMISO', $asPatient->code);

        // El auditor es de solo lectura.
        $asAuditor = $this->repo()->create($this->userId('auditor'), null, $slot->id, null, (string) Str::uuid());
        $this->assertFalse($asAuditor->success);
        $this->assertSame('SIN_PERMISO', $asAuditor->code);
    }

    public function test_patient_cannot_read_another_patients_reservation(): void
    {
        $reception = $this->userId('reception');
        [, $otherPatient] = $this->patientIds(2);
        $slot = $this->freeSlot();

        $created = $this->repo()->createForPatient($reception, $otherPatient, $slot->id, null, (string) Str::uuid());
        $this->assertTrue($created->success, $created->code);

        $this->assertNull($this->repo()->find($this->userId('patient'), $created->entityId));

        $r = $this->repo()->find($reception, $created->entityId);
        $this->repo()->cancel($reception, $r->id, 1, 'Limpieza de prueba', $r->version);
    }

    public function test_demo_seed_is_idempotent(): void
    {
        $stats = app(DemoSeedService::class)->run(self::accounts(), log: static fn () => null);

        foreach ($stats as $label => $count) {
            $this->assertStringNotContainsString('cread', $label, "La segunda ejecución volvió a crear datos: {$label}={$count}");
            $this->assertNotSame('rangos de agenda generados', $label);
        }
        $this->assertArrayHasKey('pacientes existentes', $stats);
    }
}
