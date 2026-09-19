<?php

namespace Tests\Integration;

use App\DTO\TimeSlotData;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Repositories\Contracts\PatientRepositoryInterface;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Services\DemoSeedService;
use App\Support\LocalTime;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Pruebas contra SQL Server real (ReservasMedicasWeb_Test). Nunca contra la BD de desarrollo:
 * si la conexión no apunta a una base *_Test o no está disponible, la prueba se omite.
 * Los datos se crean con clinic:seed-demo (idempotente) usando cuentas exclusivas de prueba.
 */
abstract class IntegrationTestCase extends TestCase
{
    /** Contraseña solo para la BD de pruebas; no es una credencial real. */
    protected const PASSWORD = 'Prueba-Integracion-2026';

    private static bool $seeded = false;

    /** @var array<string, int> */
    private static array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.sqlsrv.database');
        if (! str_ends_with($database, '_Test')) {
            $this->markTestSkipped("Las pruebas de integración solo corren sobre una BD *_Test (actual: {$database}).");
        }

        try {
            DB::connection('sqlsrv')->getPdo();
        } catch (Throwable $e) {
            $this->markTestSkipped('SQL Server de pruebas no disponible: '.$e->getMessage());
        }

        app(RequestContext::class)->initializeForConsole('phpunit');
        config(['demo.accounts' => self::accounts()]);

        if (! self::$seeded) {
            app(DemoSeedService::class)->run(self::accounts(), resetPasswords: true, log: static fn () => null);
            self::$seeded = true;
        }
    }

    /** @return array<string, array{user: string, email: string, password: string}> */
    protected static function accounts(): array
    {
        $accounts = [];
        foreach (['superadmin', 'admin', 'reception', 'doctor', 'patient', 'auditor'] as $key) {
            $accounts[$key] = ['user' => "it_{$key}", 'email' => "it.{$key}@nexasalud.test", 'password' => self::PASSWORD];
        }

        return $accounts;
    }

    protected function userId(string $account): int
    {
        if (! isset(self::$userIds[$account])) {
            $row = app(AuthRepositoryInterface::class)->findForLogin("it_{$account}");
            $this->assertNotNull($row, "No existe la cuenta de prueba it_{$account}");
            self::$userIds[$account] = $row->userId;
        }

        return self::$userIds[$account];
    }

    /** @return list<int> ids de pacientes demo activos */
    protected function patientIds(int $count): array
    {
        $page = app(PatientRepositoryInterface::class)->search($this->userId('reception'), 'DEMO', true, 1, 50);
        $ids = array_map(static fn ($p) => $p->id, $page->items);
        $this->assertGreaterThanOrEqual($count, count($ids), 'Faltan pacientes demo en la BD de pruebas');

        return array_slice($ids, 0, $count);
    }

    /**
     * Horario libre lejano (evita reglas de anticipación mínima y plazo de cancelación).
     * Recorre especialidades y fechas desde el final del rango hacia atrás.
     */
    protected function freeSlot(array $exclude = []): TimeSlotData
    {
        $actor = $this->userId('reception');
        $schedules = app(ScheduleRepositoryInterface::class);
        $from = LocalTime::today()->addDays(3)->toDateString();
        $to = LocalTime::today()->addDays(40)->toDateString();

        foreach (app(CatalogRepositoryInterface::class)->specialties() as $specialty) {
            $specialtyId = (int) $specialty['EspecialidadId'];
            $dates = $schedules->availableDates($actor, $specialtyId, null, null, $from, $to);
            foreach (array_reverse($dates) as $date) {
                foreach ($schedules->slotStates($actor, $specialtyId, (string) $date['Fecha'], null, null) as $slot) {
                    if ($slot->state === TimeSlotData::AVAILABLE && ! in_array($slot->id, $exclude, true)) {
                        return $slot;
                    }
                }
            }
        }

        $this->fail('No hay horarios libres en la BD de pruebas; ejecuta clinic:seed-demo contra ReservasMedicasWeb_Test.');
    }
}
