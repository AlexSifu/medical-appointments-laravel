<?php

namespace App\Support\Database;

use App\DTO\ProcedureResult;
use App\Exceptions\ConcurrencyException;
use App\Exceptions\DatabaseUnavailableException;
use App\Exceptions\PermissionDeniedException;
use App\Exceptions\StoredProcedureException;
use App\Support\RequestContext;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LogicException;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Único punto de ejecución de SQL de la aplicación.
 *
 *  - Solo ejecuta procedimientos api.usp_* y vistas api.vw_* (nombres constantes del código).
 *  - Todos los valores viajan como parámetros enlazados (?), nunca concatenados.
 *  - Los comandos se ejecutan fuera de cualquier transacción PHP: la transacción vive en el SP.
 *  - Traduce errores SQL: 50403 → PermissionDenied, 50409/1205/1222 → Concurrency, conexión → Unavailable.
 */
final class StoredProcedureExecutor
{
    private const PROCEDURE_PATTERN = '/^api\.usp_[A-Za-z0-9]+$/';

    private const VIEW_PATTERN = '/^api\.vw_[A-Za-z0-9]+$/';

    private const IDENTIFIER_PATTERN = '/^[A-Za-z][A-Za-z0-9]{0,63}$/';

    private const ORDER_PATTERN = '/^[A-Za-z][A-Za-z0-9]*( (ASC|DESC))?(, ?[A-Za-z][A-Za-z0-9]*( (ASC|DESC))?)*$/';

    private const SLOW_MS = 1500;

    public function __construct(
        private readonly DatabaseManager $db,
        private readonly RequestContext $context,
        private readonly ?string $connection = null,
    ) {}

    /** Copia del ejecutor apuntando a otra conexión (p. ej. pruebas de integración). */
    public function onConnection(?string $connection): self
    {
        return new self($this->db, $this->context, $connection);
    }

    /**
     * Procedimiento de consulta con un único result set.
     *
     * @param  array<string, mixed>  $params  ['NombreParametro' => valor] (sin '@')
     * @return list<array<string, mixed>>
     */
    public function select(string $procedure, array $params = []): array
    {
        $this->assertProcedure($procedure);

        return $this->run($procedure, $params)[0] ?? [];
    }

    /** @return array<string, mixed>|null */
    public function selectOne(string $procedure, array $params = []): ?array
    {
        return $this->select($procedure, $params)[0] ?? null;
    }

    /**
     * Comando: devuelve la fila estándar Exito|Codigo|Mensaje|EntidadId.
     * Añade automáticamente @Ip, @UserAgent y @CorrelationId del request actual.
     *
     * @param  list<string>  $contextParameters  parámetros de contexto que acepta el SP
     */
    public function command(string $procedure, array $params = [], array $contextParameters = ['Ip', 'UserAgent', 'CorrelationId']): ProcedureResult
    {
        $this->assertProcedure($procedure);

        if ($this->pdo()->inTransaction()) {
            throw new LogicException("{$procedure}: los comandos no deben ejecutarse dentro de una transacción externa.");
        }

        $context = array_intersect_key($this->context->procedureParameters(), array_flip($contextParameters));
        $sets = $this->run($procedure, $params + $context);

        // La fila estándar es el último result set con la columna Exito.
        for ($i = count($sets) - 1; $i >= 0; $i--) {
            if (isset($sets[$i][0]) && array_key_exists('Exito', $sets[$i][0])) {
                return ProcedureResult::fromRow($sets[$i][0]);
            }
        }

        Log::error('Comando SQL sin fila de resultado', ['procedure' => $procedure, 'correlation_id' => $this->context->correlationId()]);

        return new ProcedureResult(false, 'ERROR_INTERNO', '');
    }

    /**
     * Lectura de una vista api.vw_* con filtros de igualdad opcionales.
     *
     * @param  array<string, scalar|null>  $filters  ['Columna' => valor]
     * @return list<array<string, mixed>>
     */
    public function view(string $view, array $filters = [], string $orderBy = ''): array
    {
        if (preg_match(self::VIEW_PATTERN, $view) !== 1) {
            throw new InvalidArgumentException("Vista no permitida: {$view}");
        }
        if ($orderBy !== '' && preg_match(self::ORDER_PATTERN, $orderBy) !== 1) {
            throw new InvalidArgumentException('ORDER BY no permitido.');
        }

        $where = [];
        $bindings = [];
        foreach ($filters as $column => $value) {
            $this->assertIdentifier((string) $column);
            $where[] = "[{$column}] = ?";
            $bindings[] = $value;
        }

        $sql = "SELECT * FROM {$view}"
            .($where !== [] ? ' WHERE '.implode(' AND ', $where) : '')
            .($orderBy !== '' ? " ORDER BY {$orderBy}" : '');

        return $this->execute($view, $sql, $bindings)[0] ?? [];
    }

    /** @return list<list<array<string, mixed>>> */
    private function run(string $procedure, array $params): array
    {
        $assignments = [];
        foreach (array_keys($params) as $name) {
            $this->assertIdentifier((string) $name);
            $assignments[] = "@{$name} = ?";
        }

        $sql = 'EXEC '.$procedure.($assignments !== [] ? ' '.implode(', ', $assignments) : '');

        return $this->execute($procedure, $sql, array_values($params));
    }

    /** @return list<list<array<string, mixed>>> */
    private function execute(string $object, string $sql, array $bindings): array
    {
        $pdo = $this->pdo();
        $started = hrtime(true);

        try {
            $statement = $pdo->prepare($sql);
            $statement->setAttribute(PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE, true);
            $this->bind($statement, $bindings);
            $statement->execute();

            $sets = [];
            do {
                if ($statement->columnCount() > 0) {
                    $sets[] = $statement->fetchAll(PDO::FETCH_ASSOC);
                }
            } while ($statement->nextRowset());
            $statement->closeCursor();
        } catch (PDOException $e) {
            throw $this->translate($object, $e);
        }

        $elapsedMs = intdiv(hrtime(true) - $started, 1_000_000);
        if ($elapsedMs > self::SLOW_MS) {
            Log::warning('Objeto SQL lento', ['object' => $object, 'ms' => $elapsedMs, 'correlation_id' => $this->context->correlationId()]);
        }

        return $sets;
    }

    private function bind(PDOStatement $statement, array $bindings): void
    {
        foreach (array_values($bindings) as $i => $value) {
            [$value, $type] = match (true) {
                $value === null => [null, PDO::PARAM_NULL],
                is_bool($value) => [$value ? 1 : 0, PDO::PARAM_INT],
                is_int($value) => [$value, PDO::PARAM_INT],
                $value instanceof DateTimeInterface => [$value->format('Y-m-d H:i:s'), PDO::PARAM_STR],
                $value instanceof BackedEnum => [$value->value, is_int($value->value) ? PDO::PARAM_INT : PDO::PARAM_STR],
                default => [(string) $value, PDO::PARAM_STR],
            };
            $statement->bindValue($i + 1, $value, $type);
        }
    }

    private function pdo(): PDO
    {
        try {
            return $this->db->connection($this->connection)->getPdo();
        } catch (PDOException $e) {
            Log::critical('SQL Server no disponible', ['sqlstate' => $e->getCode(), 'correlation_id' => $this->context->correlationId()]);

            throw new DatabaseUnavailableException($e);
        }
    }

    private function translate(string $object, PDOException $e): RuntimeException
    {
        $number = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : null;
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());

        if ($number === 50403) {
            return new PermissionDeniedException;
        }
        if (in_array($number, [50409, 1205, 1222], true)) {
            return new ConcurrencyException($e);
        }
        if (str_starts_with($sqlState, '08') || $sqlState === 'HYT00' || in_array($number, [18456, 4060, 233, 10054], true)) {
            Log::critical('SQL Server no disponible', ['object' => $object, 'sql_error' => $number, 'correlation_id' => $this->context->correlationId()]);

            return new DatabaseUnavailableException($e);
        }

        // Error técnico: se registra sin parámetros (pueden contener datos personales o hashes).
        Log::error('Error SQL al ejecutar objeto de base de datos', [
            'object' => $object,
            'sql_error' => $number,
            'sqlstate' => $sqlState,
            'message' => $e->errorInfo[2] ?? $e->getMessage(),
            'correlation_id' => $this->context->correlationId(),
        ]);

        return new StoredProcedureException($object, $number, $e);
    }

    private function assertProcedure(string $procedure): void
    {
        if (preg_match(self::PROCEDURE_PATTERN, $procedure) !== 1) {
            throw new InvalidArgumentException("Procedimiento no permitido: {$procedure}");
        }
    }

    private function assertIdentifier(string $name): void
    {
        if (preg_match(self::IDENTIFIER_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException("Identificador no permitido: {$name}");
        }
    }
}
