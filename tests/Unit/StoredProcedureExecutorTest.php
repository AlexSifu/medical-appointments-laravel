<?php

namespace Tests\Unit;

use App\Support\Database\StoredProcedureExecutor;
use App\Support\RequestContext;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** El ejecutor rechaza cualquier objeto SQL que no sea api.usp_* / api.vw_* antes de tocar la conexión. */
final class StoredProcedureExecutorTest extends TestCase
{
    private function executor(): StoredProcedureExecutor
    {
        $db = Mockery::mock(DatabaseManager::class);
        $db->shouldNotReceive('connection');

        return new StoredProcedureExecutor($db, new RequestContext);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenProcedures(): iterable
    {
        yield 'esquema interno clin' => ['clin.usp_ReservaCerrar'];
        yield 'esquema seg' => ['seg.usp_Resultado'];
        yield 'sin esquema' => ['usp_ReservaCrear'];
        yield 'inyección' => ['api.usp_ReservaCrear; DROP TABLE clin.Reservas'];
        yield 'comentario' => ['api.usp_X--'];
        yield 'tabla directa' => ['clin.Reservas'];
        yield 'sistema' => ['sp_executesql'];
    }

    #[DataProvider('forbiddenProcedures')]
    public function test_rejects_procedures_outside_api_schema(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->executor()->select($name);
    }

    #[DataProvider('forbiddenProcedures')]
    public function test_rejects_commands_outside_api_schema(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->executor()->command($name);
    }

    public function test_rejects_views_outside_api_schema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->executor()->view('clin.Reservas');
    }

    public function test_rejects_unsafe_order_by(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->executor()->view('api.vw_Sedes', [], 'Nombre; DROP TABLE x');
    }

    public function test_rejects_unsafe_filter_column(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->executor()->view('api.vw_Sedes', ['Nombre] = 1 OR [x' => 'a']);
    }
}
