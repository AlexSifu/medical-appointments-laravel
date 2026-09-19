<?php

namespace Tests\Integration;

use App\Repositories\Contracts\ReservationRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Carrera real: N procesos PHP (conexiones distintas) reservan el mismo horario a la vez.
 * La regla vive en SQL Server (UPDLOCK/HOLDLOCK + índice único filtrado): exactamente uno gana.
 */
final class ReservationConcurrencyTest extends IntegrationTestCase
{
    private const WORKERS = 6;

    public function test_only_one_parallel_booking_wins_the_same_slot(): void
    {
        $slot = $this->freeSlot();
        $patients = $this->patientIds(self::WORKERS);
        $reception = $this->userId('reception');
        $startAt = microtime(true) + 4.0; // margen para que todos arranquen y conecten

        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'sqlsrv',
            'DB_DATABASE' => (string) config('database.connections.sqlsrv.database'),
        ]);

        $processes = [];
        foreach ($patients as $i => $patientId) {
            $cmd = [PHP_BINARY, base_path('tests/Support/book_slot_worker.php'),
                (string) $reception, (string) $patientId, (string) $slot->id, (string) Str::uuid(), sprintf('%.6F', $startAt)];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
            $this->assertIsResource($proc, "No se pudo lanzar el worker {$i}");
            $processes[] = [$proc, $pipes];
        }

        $results = [];
        foreach ($processes as [$proc, $pipes]) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
            $decoded = json_decode(trim((string) $out), true);
            $this->assertIsArray($decoded, "Salida inválida del worker: {$out} {$err}");
            $results[] = $decoded;
        }

        $winners = array_values(array_filter($results, fn ($r) => $r['success'] === true));
        $losers = array_values(array_filter($results, fn ($r) => $r['success'] !== true));

        $this->assertCount(1, $winners, 'Debe existir exactamente una reserva ganadora: '.json_encode($results));
        foreach ($losers as $r) {
            $this->assertContains($r['code'], ['SLOT_OCUPADO', 'CONCURRENCIA'], json_encode($r));
        }

        // Limpieza: se cancela la reserva ganadora para que la prueba sea repetible.
        $repo = app(ReservationRepositoryInterface::class);
        $reservation = $repo->find($reception, (int) $winners[0]['entityId']);
        $this->assertNotNull($reservation);
        $this->assertSame($slot->id, $reservation->slotId);
        $this->assertTrue($repo->cancel($reception, $reservation->id, 1, 'Limpieza de prueba', $reservation->version)->success);
    }
}
