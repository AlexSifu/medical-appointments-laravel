<?php

namespace App\Console\Commands;

use App\Services\DemoSeedService;
use App\Support\RequestContext;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Carga datos demo ficticios mediante los procedimientos api.usp_* (idempotente).
 * Nunca imprime contraseñas: solo usuarios, roles y contadores.
 */
final class SeedDemoCommand extends Command
{
    protected $signature = 'clinic:seed-demo
        {--reset-passwords : Vuelve a aplicar las contraseñas de .env a las cuentas demo existentes}
        {--force : Permite ejecutar en producción}';

    protected $description = 'Crea sedes, especialidades, médicos, pacientes, agendas, reservas y cuentas demo (idempotente).';

    public function handle(DemoSeedService $seeder, RequestContext $context): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->error('clinic:seed-demo es solo para desarrollo. Usa --force si realmente lo necesitas.');

            return self::FAILURE;
        }

        $context->initializeForConsole('clinic:seed-demo');
        $this->info('Cargando datos demo en la base '.config('database.connections.'.config('database.default').'.database').'…');

        try {
            $stats = $seeder->run(
                (array) config('demo.accounts'),
                (bool) $this->option('reset-passwords'),
                function (string $level, string $message): void {
                    $level === 'warn' ? $this->warn('  ! '.$message) : $this->line('  · '.$message);
                },
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            report($e);
            $this->error('Error inesperado. Revisa storage/logs (correlation '.$context->correlationId().').');

            return self::FAILURE;
        }

        ksort($stats);
        $this->table(['Elemento', 'Cantidad'], array_map(null, array_keys($stats), array_values($stats)));
        $this->info('Datos demo listos. Las contraseñas de las cuentas demo están en tu .env (no se muestran).');

        return self::SUCCESS;
    }
}
