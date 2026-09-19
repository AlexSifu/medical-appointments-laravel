<?php

namespace Tests\Feature;

use App\DTO\LoginAccountData;
use App\DTO\ProcedureResult;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Support\Security\LegacyPbkdf2Hasher;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    private MockInterface $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->repo = Mockery::mock(AuthRepositoryInterface::class);
        $this->app->instance(AuthRepositoryInterface::class, $this->repo);
    }

    private function account(string $hash, string $algorithm = 'BCRYPT', bool $active = true, bool $locked = false): LoginAccountData
    {
        return new LoginAccountData(12, 'paciente.prueba', $hash, $algorithm, $active, $locked, 0, [
            'UsuarioId' => 12, 'NombreUsuario' => 'paciente.prueba', 'Email' => 'p@nexasalud.test',
            'Nombres' => 'Paciente', 'Apellidos' => 'Prueba', 'PacienteId' => 5, 'MedicoId' => null,
        ]);
    }

    private function grants(): array
    {
        return [['Tipo' => 'ROL', 'Codigo' => 'PACIENTE'], ['Tipo' => 'PERMISO', 'Codigo' => 'reservas.propias']];
    }

    public function test_valid_credentials_log_in_and_register_success(): void
    {
        $this->repo->shouldReceive('findForLogin')->with('paciente.prueba')->andReturn($this->account(password_hash('Clave-Segura-2026', PASSWORD_BCRYPT)));
        $this->repo->shouldReceive('registerSuccess')->once()->with(12)->andReturn(new ProcedureResult(true, 'OK', ''));
        $this->repo->shouldReceive('grants')->with(12)->andReturn($this->grants());

        $this->post('/login', ['login' => 'paciente.prueba', 'password' => 'Clave-Segura-2026'])
            ->assertRedirect(route('dashboard'));

        $user = Auth::user();
        $this->assertSame(12, $user->id());
        $this->assertTrue($user->can('reservas.propias'));
        $this->assertFalse($user->can('usuarios.ver'));
    }

    public function test_wrong_password_is_generic_and_audited(): void
    {
        $this->repo->shouldReceive('findForLogin')->andReturn($this->account(password_hash('Clave-Segura-2026', PASSWORD_BCRYPT)));
        $this->repo->shouldReceive('registerFailure')->once()->with(12, 'paciente.prueba', 'CREDENCIALES')->andReturn(new ProcedureResult(true, 'OK', ''));
        $this->repo->shouldNotReceive('registerSuccess');

        $this->from('/login')->post('/login', ['login' => 'paciente.prueba', 'password' => 'otra-clave-123'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['login' => 'Usuario o contraseña incorrectos.'])
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }

    public function test_unknown_user_gets_the_same_generic_message(): void
    {
        $this->repo->shouldReceive('findForLogin')->andReturn(null);
        $this->repo->shouldReceive('registerFailure')->once()->with(null, 'nadie', 'CREDENCIALES')->andReturn(new ProcedureResult(true, 'OK', ''));

        $this->from('/login')->post('/login', ['login' => 'nadie', 'password' => 'otra-clave-123'])
            ->assertSessionHasErrors(['login' => 'Usuario o contraseña incorrectos.']);
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        $this->repo->shouldReceive('findForLogin')->andReturn($this->account(password_hash('Clave-Segura-2026', PASSWORD_BCRYPT), active: false));
        $this->repo->shouldReceive('registerFailure')->once()->andReturn(new ProcedureResult(true, 'OK', ''));

        $this->post('/login', ['login' => 'paciente.prueba', 'password' => 'Clave-Segura-2026'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_legacy_hash_is_upgraded_to_bcrypt_after_login(): void
    {
        $legacy = (new LegacyPbkdf2Hasher)->make('Clave-Legacy-2026', 10_000);
        $this->repo->shouldReceive('findForLogin')->andReturn($this->account($legacy, LegacyPbkdf2Hasher::ALGORITHM));
        $this->repo->shouldReceive('registerSuccess')->andReturn(new ProcedureResult(true, 'OK', ''));
        $this->repo->shouldReceive('grants')->andReturn($this->grants());
        $this->repo->shouldReceive('updatePasswordHash')->once()
            ->withArgs(fn ($actor, $user, $hash, $alg) => $actor === 12 && $user === 12 && $alg === 'BCRYPT' && password_verify('Clave-Legacy-2026', $hash))
            ->andReturn(new ProcedureResult(true, 'OK', ''));

        $this->post('/login', ['login' => 'paciente.prueba', 'password' => 'Clave-Legacy-2026'])->assertRedirect(route('dashboard'));
    }

    public function test_login_page_never_shows_demo_credentials(): void
    {
        config(['demo.accounts.patient.password' => 'Demo-Secreta-XYZ-2026']);

        $this->get('/login')->assertOk()->assertDontSee('Demo-Secreta-XYZ-2026');
    }
}
