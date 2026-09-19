<?php

namespace Tests\Unit;

use App\Models\Role;
use App\Support\ErrorMessages;
use App\Support\LocalTime;
use App\Support\Security\LegacyPbkdf2Hasher;
use Tests\TestCase;

final class SecurityHelpersTest extends TestCase
{
    public function test_legacy_pbkdf2_hash_round_trip(): void
    {
        $hasher = new LegacyPbkdf2Hasher;
        $hash = $hasher->make('Clave-Demo-2026', 10_000);

        $this->assertStringStartsWith('pbkdf2_sha256$', $hash);
        $this->assertTrue($hasher->verify('Clave-Demo-2026', $hash));
        $this->assertFalse($hasher->verify('clave-demo-2026', $hash));
        $this->assertFalse($hasher->verify('Clave-Demo-2026', 'pbkdf2_sha256$1$abc$def'), 'Iteraciones fuera de rango');
        $this->assertFalse($hasher->verify('x', 'basura'));
    }

    public function test_utc_columns_are_shown_in_clinic_time(): void
    {
        config(['app.display_timezone' => 'America/Lima']);

        $this->assertSame('21/09/2026 08:30', LocalTime::fromUtc('2026-09-21 13:30:00'));
        $this->assertSame('—', LocalTime::fromUtc(null));
        $this->assertSame('08:30', LocalTime::time('08:30:00'));
    }

    public function test_error_messages_have_spanish_fallback(): void
    {
        $this->assertNotSame('', ErrorMessages::for('SLOT_OCUPADO'));
        $this->assertSame('Mensaje del SP', ErrorMessages::for('CODIGO_DESCONOCIDO_XYZ', 'Mensaje del SP'));
    }

    public function test_every_role_has_a_label(): void
    {
        foreach (Role::PRECEDENCE as $code) {
            $this->assertNotSame($code, Role::label($code), "Falta etiqueta para {$code}");
        }
    }
}
