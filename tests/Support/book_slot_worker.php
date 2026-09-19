<?php

/*
 * Proceso independiente para la prueba de concurrencia: cada worker abre su propia conexión
 * a SQL Server y reserva el MISMO horario al mismo instante (barrera por reloj).
 *
 * Uso: php book_slot_worker.php <actorId> <patientId> <slotId> <idempotencyKey> <startAtMicrotime>
 * Salida: una línea JSON con el ProcedureResult.
 */

use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Support\RequestContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

[, $actorId, $patientId, $slotId, $key, $startAt] = $argv;

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! str_ends_with((string) config('database.connections.sqlsrv.database'), '_Test')) {
    fwrite(STDERR, "El worker solo corre contra una BD *_Test.\n");
    exit(2);
}

$app->make(RequestContext::class)->initializeForConsole('phpunit-worker');
$repo = $app->make(ReservationRepositoryInterface::class);
DB::connection('sqlsrv')->getPdo(); // conexión abierta antes de la barrera

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    $r = $repo->createForPatient((int) $actorId, (int) $patientId, (int) $slotId, 'Prueba de concurrencia', $key);
    echo json_encode(['success' => $r->success, 'code' => $r->code, 'entityId' => $r->entityId]), PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'code' => 'EXCEPTION', 'error' => get_class($e).': '.$e->getMessage()]), PHP_EOL;
}
