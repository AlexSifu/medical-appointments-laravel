<?php

namespace Tests\Unit;

use App\DTO\PagedResult;
use App\DTO\ProcedureResult;
use App\Exceptions\BusinessRuleException;
use Tests\TestCase;

final class ProcedureResultTest extends TestCase
{
    public function test_maps_standard_result_row(): void
    {
        $r = ProcedureResult::fromRow(['Exito' => 1, 'Codigo' => 'RESERVA_CREADA', 'Mensaje' => 'Reserva registrada.', 'EntidadId' => '42']);

        $this->assertTrue($r->success);
        $this->assertSame('RESERVA_CREADA', $r->code);
        $this->assertSame(42, $r->entityId);
        $this->assertSame($r, $r->throwIfFailed());
    }

    public function test_failed_result_throws_business_rule_exception_with_code(): void
    {
        $r = ProcedureResult::fromRow(['Exito' => 0, 'Codigo' => 'SLOT_OCUPADO', 'Mensaje' => 'Ocupado', 'EntidadId' => null]);

        try {
            $r->throwIfFailed();
            $this->fail('Se esperaba BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertSame('SLOT_OCUPADO', $e->businessCode);
            $this->assertNotSame('', $e->getMessage());
        }
    }

    public function test_paged_result_uses_total_rows_column(): void
    {
        $page = PagedResult::fromRows([['Id' => 1, 'TotalFilas' => 57], ['Id' => 2, 'TotalFilas' => 57]], 2, 20, fn (array $r) => $r['Id']);

        $this->assertSame([1, 2], $page->items);
        $this->assertSame(57, $page->total);
        $this->assertFalse($page->isEmpty());
        $this->assertSame(0, PagedResult::fromRows([], 1, 20, fn ($r) => $r)->total);
    }
}
