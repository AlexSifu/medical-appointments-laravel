<?php

namespace Tests\Integration;

use App\DTO\AgendaData;
use App\DTO\ProcedureResult;
use App\DTO\TimeSlotData;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\DoctorRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Support\LocalTime;
use Illuminate\Support\Str;

/**
 * Validaciones críticas que no cubren las otras pruebas: cada una llama al SP real y comprueba el
 * código de rechazo. Usa médicos propios (IT-FIX-*) con agendas nocturnas (21:00–22:00, fuera del
 * horario demo) y deja todo como estaba al terminar (cancela reservas, desactiva agendas, reactiva
 * al médico y restaura la configuración).
 */
final class CriticalValidationsTest extends IntegrationTestCase
{
    /** @var list<array{int, int, string}> [agendaId, medicoId, fecha] creadas por la prueba en curso */
    private array $agendas = [];

    /** @var list<array{int, int}> [actor, reservaId] a cancelar al final */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as [$actor, $id]) {
            $r = $this->reservations()->find($actor, $id);
            if ($r !== null && $r->statusCode === 'CONFIRMADA') {
                $this->reservations()->cancel($this->userId('reception'), $id, 1, 'Limpieza de prueba', $r->version);
            }
        }
        foreach ($this->agendas as [$agendaId, $doctorId, $date]) {
            $agenda = $this->findAgenda($agendaId, $doctorId, $date);
            if ($agenda !== null && $agenda->active) {
                $this->schedules()->deactivateAgenda($this->userId('admin'), $agendaId, $agenda->version);
            }
        }
        $this->agendas = $this->created = [];

        parent::tearDown();
    }

    public function test_patient_cannot_hold_two_overlapping_appointments(): void
    {
        [$roomA, $roomB] = $this->rooms();
        $date = $this->day(20);
        $slotA = $this->agendaSlot($this->doctor(1), $date, $roomA);
        $slotB = $this->agendaSlot($this->doctor(2), $date, $roomB);

        $first = $this->book($this->userId('patient'), $slotA);
        $this->assertTrue($first->success, $first->code);

        $this->assertRejected('PACIENTE_CITA_SOLAPADA', $this->book($this->userId('patient'), $slotB));
    }

    public function test_doctor_and_room_agendas_cannot_overlap(): void
    {
        [$roomA, $roomB] = $this->rooms();
        $date = $this->day(21);
        $this->agendaSlot($this->doctor(1), $date, $roomA);

        // Mismo médico, otro consultorio, horario que se cruza.
        $this->assertRejected('MEDICO_AGENDA_SOLAPADA', $this->createAgenda($this->doctor(1), $date, $roomB, '21:30', '22:30'));

        // Otro médico, mismo consultorio, horario que se cruza.
        $this->assertRejected('CONSULTORIO_SOLAPADO', $this->createAgenda($this->doctor(2), $date, $roomA, '21:30', '22:30'));
    }

    public function test_blocked_slot_cannot_be_booked(): void
    {
        $slot = $this->agendaSlot($this->doctor(1), $this->day(22), $this->rooms()[0]);
        $admin = $this->userId('admin');

        $block = $this->schedules()->block($admin, ['doctor_id' => $this->doctor(1), 'type' => 'SLOT', 'slot_id' => $slot->id, 'reason' => 'Prueba']);
        $this->assertTrue($block->success, $block->code);

        try {
            $this->assertRejected('SLOT_BLOQUEADO', $this->book($this->userId('reception'), $slot));
        } finally {
            $this->schedules()->unblock($admin, (int) $block->entityId);
        }
    }

    public function test_inactive_agenda_cannot_be_booked(): void
    {
        $slot = $this->agendaSlot($this->doctor(1), $this->day(23), $this->rooms()[0]);
        $agenda = $this->findAgenda($slot->scheduleId, $slot->doctorId, $slot->date);
        $this->assertTrue($this->schedules()->deactivateAgenda($this->userId('admin'), $agenda->id, $agenda->version)->success);

        $this->assertRejected('AGENDA_INACTIVA', $this->book($this->userId('reception'), $slot));
    }

    public function test_inactive_patient_cannot_be_booked(): void
    {
        $slot = $this->agendaSlot($this->doctor(1), $this->day(24), $this->rooms()[0]);

        $result = $this->reservations()->createForPatient($this->userId('reception'), $this->inactivePatient(), $slot->id, null, (string) Str::uuid());
        $this->assertRejected('PACIENTE_INACTIVO', $result);
    }

    public function test_inactive_doctor_cannot_be_booked(): void
    {
        $doctorId = $this->doctor(3);
        $slot = $this->agendaSlot($doctorId, $this->day(25), $this->rooms()[0]);
        $admin = $this->userId('admin');
        $doctors = app(DoctorRepositoryInterface::class);

        $this->assertTrue($doctors->changeStatus($admin, $doctorId, false, $doctors->find($admin, $doctorId)->version)->success);
        try {
            $this->assertRejected('MEDICO_INACTIVO', $this->book($this->userId('reception'), $slot));
        } finally {
            $doctors->changeStatus($admin, $doctorId, true, $doctors->find($admin, $doctorId)->version);
        }
    }

    public function test_reschedule_to_an_occupied_slot_is_rejected(): void
    {
        $date = $this->day(26);
        $this->agendaSlot($this->doctor(1), $date, $this->rooms()[0]);
        [$slotA, $slotB] = $this->slots($this->doctor(1), $date);

        $mine = $this->book($this->userId('patient'), $slotA);
        $theirs = $this->book($this->userId('reception'), $slotB, $this->patientIds(2)[1]);
        $this->assertTrue($mine->success, $mine->code);
        $this->assertTrue($theirs->success, $theirs->code);

        $r = $this->reservations()->find($this->userId('patient'), $mine->entityId);
        $moved = $this->reservations()->reschedule($this->userId('patient'), $r->id, $slotB->id, null, $r->version, (string) Str::uuid());
        $this->assertRejected('SLOT_OCUPADO', $moved);
        $this->assertSame('CONFIRMADA', $this->reservations()->find($this->userId('patient'), $r->id)->statusCode, 'La reserva original no debe cambiar');
    }

    public function test_patient_cannot_cancel_inside_the_minimum_notice(): void
    {
        $slot = $this->agendaSlot($this->doctor(1), $this->day(1), $this->rooms()[0]); // mañana 21:00: entre 21 y 45 h
        $catalog = app(CatalogRepositoryInterface::class);
        $superadmin = $this->userId('superadmin');
        $previous = (string) collect($catalog->configuration())->firstWhere('Clave', 'HORAS_MINIMAS_CANCELACION')['Valor'];

        $created = $this->book($this->userId('patient'), $slot);
        $this->assertTrue($created->success, $created->code);
        $r = $this->reservations()->find($this->userId('patient'), $created->entityId);

        $this->assertTrue($catalog->updateConfiguration($superadmin, 'HORAS_MINIMAS_CANCELACION', '72')->success);
        try {
            $this->assertRejected('FUERA_DE_PLAZO', $this->reservations()->cancel($this->userId('patient'), $r->id, 1, null, $r->version));
            // El personal no está sujeto al plazo.
            $this->assertTrue($this->reservations()->cancel($this->userId('reception'), $r->id, 1, 'Limpieza de prueba', $r->version)->success);
        } finally {
            $catalog->updateConfiguration($superadmin, 'HORAS_MINIMAS_CANCELACION', $previous);
            $catalog->forgetCache();
        }
    }

    // ----- apoyo -----

    private function reservations(): ReservationRepositoryInterface
    {
        return app(ReservationRepositoryInterface::class);
    }

    private function schedules(): ScheduleRepositoryInterface
    {
        return app(ScheduleRepositoryInterface::class);
    }

    private function catalog(): CatalogRepositoryInterface
    {
        return app(CatalogRepositoryInterface::class);
    }

    private function assertRejected(string $code, ProcedureResult $result): void
    {
        $this->assertFalse($result->success, "Se esperaba {$code} pero el SP aceptó la operación");
        $this->assertSame($code, $result->code);
    }

    private function day(int $offset): string
    {
        return LocalTime::today()->addDays($offset)->toDateString();
    }

    /** Reserva como paciente (para sí) o como personal (para $patientId o el primer paciente demo) y la registra para limpieza. */
    private function book(int $actor, TimeSlotData $slot, ?int $patientId = null): ProcedureResult
    {
        $result = $actor === $this->userId('patient')
            ? $this->reservations()->create($actor, null, $slot->id, null, (string) Str::uuid())
            : $this->reservations()->createForPatient($actor, $patientId ?? $this->patientIds(1)[0], $slot->id, null, (string) Str::uuid());
        if ($result->success) {
            $this->created[] = [$actor, (int) $result->entityId];
        }

        return $result;
    }

    /** @return array{int, int} sede (con 2+ consultorios) y especialidad de los médicos de prueba */
    private function place(): array
    {
        $specialty = (int) $this->catalog()->specialties()[0]['EspecialidadId'];
        foreach ($this->catalog()->branches() as $branch) {
            if (count($this->catalog()->rooms((int) $branch['SedeId'])) >= 2) {
                return [(int) $branch['SedeId'], $specialty];
            }
        }
        $this->fail('Se necesita una sede con al menos dos consultorios activos.');
    }

    /** @return array{int, int} dos consultorios de la sede de prueba */
    private function rooms(): array
    {
        $rows = array_values($this->catalog()->rooms($this->place()[0]));

        return [(int) $rows[0]['ConsultorioId'], (int) $rows[1]['ConsultorioId']];
    }

    /** Médico de prueba IT-FIX-{n}; se crea la primera vez y se reactiva si quedó inactivo. */
    private function doctor(int $n): int
    {
        $admin = $this->userId('admin');
        $doctors = app(DoctorRepositoryInterface::class);
        $cmp = "IT-FIX-{$n}";
        $found = collect($doctors->search($admin, $cmp, null, null, null, 1, 10)->items)->first(fn ($d) => $d->cmp === $cmp);

        if ($found === null) {
            [$branch, $specialty] = $this->place();
            $created = $doctors->create($admin, ['cmp' => $cmp, 'first_names' => 'Prueba', 'last_names' => "Integración {$n}",
                'specialty_id' => $specialty, 'branch_id' => $branch]);
            $this->assertTrue($created->success, $created->code);

            return (int) $created->entityId;
        }
        if (! $found->active) {
            $doctors->changeStatus($admin, $found->id, true, $found->version);
        }

        return $found->id;
    }

    private function createAgenda(int $doctorId, string $date, int $roomId, string $start, string $end): ProcedureResult
    {
        [$branch, $specialty] = $this->place();

        return $this->schedules()->createAgenda($this->userId('admin'), [
            'doctor_id' => $doctorId, 'specialty_id' => $specialty, 'branch_id' => $branch, 'room_id' => $roomId,
            'care_type_id' => 1, 'date' => $date, 'start' => $start, 'end' => $end, 'duration' => 30,
        ]);
    }

    /** Agenda 21:00–22:00 (2 slots de 30 min); devuelve su primer slot. */
    private function agendaSlot(int $doctorId, string $date, int $roomId): TimeSlotData
    {
        $result = $this->createAgenda($doctorId, $date, $roomId, '21:00', '22:00');
        $this->assertTrue($result->success, "No se pudo crear la agenda de prueba: {$result->code}");
        $this->agendas[] = [(int) $result->entityId, $doctorId, $date];

        return $this->slots($doctorId, $date)[0];
    }

    /** @return list<TimeSlotData> slots nocturnos libres del médico ese día */
    private function slots(int $doctorId, string $date): array
    {
        $slots = array_values(array_filter(
            $this->schedules()->slotStates($this->userId('reception'), $this->place()[1], $date, $doctorId, null),
            static fn (TimeSlotData $s) => $s->start >= '21:00' && $s->state === TimeSlotData::AVAILABLE,
        ));
        $this->assertNotEmpty($slots, "La agenda de prueba del {$date} no tiene slots libres");

        return $slots;
    }

    private function findAgenda(int $agendaId, int $doctorId, string $date): ?AgendaData
    {
        foreach ($this->schedules()->listAgendas($this->userId('admin'), $doctorId, null, null, $date, $date, null, 1, 50)->items as $agenda) {
            if ($agenda->id === $agendaId) {
                return $agenda;
            }
        }

        return null;
    }

    /** Paciente de prueba inactivo (documento fijo, sin cuenta); se crea una sola vez. */
    private function inactivePatient(): int
    {
        $reception = $this->userId('reception');
        $patients = app(PatientRepositoryInterface::class);
        $data = ['document_type' => 'DNI', 'document_number' => '99000116', 'first_names' => 'Prueba', 'last_names' => 'Integración Inactivo', 'birth_date' => '1990-01-01'];

        $found = collect($patients->search($reception, $data['document_number'], null, 1, 10)->items)
            ->first(fn ($p) => $p->documentNumber === $data['document_number']);
        if ($found === null) {
            $created = $patients->create($reception, $data);
            $this->assertTrue($created->success, $created->code);
            $found = $patients->find($reception, (int) $created->entityId);
        }
        if ($found->active) {
            $this->assertTrue($patients->update($reception, $found->id, $data + ['active' => false], $found->version)->success);
        }

        return $found->id;
    }
}
