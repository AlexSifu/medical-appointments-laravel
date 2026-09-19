<?php

namespace Tests\Feature;

use App\DTO\ProcedureResult;
use App\Models\Permission;
use App\Models\Role;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/** Controller → FormRequest → Service → Repository, con el repositorio simulado. */
final class BookingFlowTest extends TestCase
{
    private const KEY = '9b2f7c1e-3d4a-4f5b-8c6d-7e8f9a0b1c2d';

    private MockInterface $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->repo = Mockery::mock(ReservationRepositoryInterface::class);
        $this->app->instance(ReservationRepositoryInterface::class, $this->repo);
    }

    private function asPatient(): void
    {
        $this->actingAsRole(Role::PATIENT, [Permission::RESERVATIONS_OWN, Permission::RESERVATIONS_CREATE, Permission::RESERVATIONS_CANCEL], id: 21, patientId: 5);
    }

    public function test_invalid_payload_never_reaches_the_repository(): void
    {
        $this->asPatient();
        $this->repo->shouldNotReceive('create');

        $this->from('/reservar')->post('/reservas', ['slot_id' => 'abc', 'idempotency_key' => 'no-es-uuid'])
            ->assertRedirect('/reservar')
            ->assertSessionHasErrors(['slot_id', 'idempotency_key']);
    }

    public function test_patient_books_for_self_even_if_patient_id_is_tampered(): void
    {
        $this->asPatient();
        $this->repo->shouldReceive('create')->once()
            ->with(21, null, 300, 'Control', self::KEY)
            ->andReturn(new ProcedureResult(true, 'RESERVA_CREADA', 'Reserva registrada.', 45));
        $this->repo->shouldNotReceive('createForPatient');

        $this->post('/reservas', ['slot_id' => 300, 'patient_id' => 999, 'notes' => 'Control', 'idempotency_key' => self::KEY])
            ->assertRedirect(route('reservations.show', ['reservation' => 45, 'confirmada' => 1]))
            ->assertSessionHas('success');
    }

    public function test_reception_books_for_the_chosen_patient(): void
    {
        $this->actingAsRole(Role::RECEPTIONIST, [Permission::RESERVATIONS_CREATE, Permission::RESERVATIONS_MANAGE], id: 3);
        $this->repo->shouldReceive('createForPatient')->once()
            ->with(3, 8, 300, null, self::KEY)
            ->andReturn(new ProcedureResult(true, 'RESERVA_CREADA', 'Reserva registrada.', 46));

        $this->post('/reservas', ['slot_id' => 300, 'patient_id' => 8, 'idempotency_key' => self::KEY])
            ->assertRedirect(route('reservations.show', ['reservation' => 46, 'confirmada' => 1]));
    }

    public function test_business_rule_rejection_returns_to_form_with_ux_message(): void
    {
        $this->asPatient();
        $this->repo->shouldReceive('create')->once()
            ->andReturn(new ProcedureResult(false, 'SLOT_OCUPADO', 'texto del SP', null));

        $this->from('/reservar')->post('/reservas', ['slot_id' => 300, 'idempotency_key' => self::KEY])
            ->assertRedirect('/reservar')
            ->assertSessionHas('error_code', 'SLOT_OCUPADO')
            ->assertSessionHas('error', 'El horario acaba de ser reservado por otra persona. Elige otro horario.');
    }

    public function test_business_rule_rejection_is_json_for_ajax(): void
    {
        $this->asPatient();
        $this->repo->shouldReceive('cancel')->once()
            ->with(21, 45, 2, null, '0x00000000000007D1')
            ->andReturn(new ProcedureResult(false, 'CONFLICTO_EDICION', 'x', null));

        $this->postJson('/reservas/45/cancelar', ['reason_id' => 2, 'version' => '0x00000000000007D1'])
            ->assertStatus(422)
            ->assertJson(['code' => 'CONFLICTO_EDICION']);
    }

    public function test_booking_requires_permission(): void
    {
        $this->actingAsRole(Role::AUDITOR, [Permission::AUDIT_VIEW, Permission::REPORTS_VIEW]);
        $this->repo->shouldNotReceive('create');

        $this->post('/reservas', ['slot_id' => 300, 'idempotency_key' => self::KEY])->assertForbidden();
    }
}
