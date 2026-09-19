<?php

namespace Tests\Feature;

use App\DTO\PagedResult;
use App\DTO\UserAccountData;
use App\Models\Permission;
use App\Models\Role;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\RequestContext;
use Mockery;
use Tests\TestCase;

/** Middleware de sesión, permisos y cabeceras. Sin SQL: los repositorios se simulan. */
final class AccessControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/admin/usuarios')->assertRedirect(route('login'));
    }

    public function test_login_page_sends_security_headers_and_correlation_id(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $response->assertHeader('X-Frame-Options');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader(RequestContext::HEADER);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9-]{8,64}$/', (string) $response->headers->get(RequestContext::HEADER));
    }

    public function test_foreign_correlation_id_is_not_trusted_if_malformed(): void
    {
        $response = $this->withHeader(RequestContext::HEADER, "abc'; DROP TABLE x--")->get(route('login'));

        $this->assertStringNotContainsString('DROP', (string) $response->headers->get(RequestContext::HEADER));
    }

    public function test_patient_cannot_open_admin_pages(): void
    {
        $this->actingAsRole(Role::PATIENT, [Permission::RESERVATIONS_OWN, Permission::RESERVATIONS_CREATE], patientId: 5);

        $this->get('/admin/usuarios')->assertForbidden();
        $this->get('/auditoria')->assertForbidden();
        $this->post('/admin/usuarios', [])->assertForbidden();
    }

    public function test_admin_sees_users_listed_by_the_repository(): void
    {
        $repo = Mockery::mock(UserRepositoryInterface::class);
        $repo->shouldReceive('roles')->andReturn([['Codigo' => Role::ADMIN, 'Nombre' => 'Administrador']]);
        $repo->shouldReceive('search')
            ->withArgs(fn ($actor, $text, $role, $active, $page, $perPage) => $actor === 10 && $text === 'ana' && $page === 1)
            ->once()
            ->andReturn(new PagedResult([UserAccountData::fromRow([
                'UsuarioId' => 7, 'NombreUsuario' => 'ana.demo', 'Email' => 'ana@nexasalud.test',
                'Nombres' => 'Ana', 'Apellidos' => 'Demo', 'Activo' => 1, 'Roles' => 'RECEPCIONISTA',
                'PasswordAlgoritmo' => 'BCRYPT', 'VersionFila' => '00000000000007D1',
            ])], 1, 1, 20));
        $this->app->instance(UserRepositoryInterface::class, $repo);

        $this->actingAsRole(Role::ADMIN, [Permission::USERS_VIEW]);

        $this->get('/admin/usuarios?q=ana')
            ->assertOk()
            ->assertSee('ana.demo')
            ->assertSee('Recepcionista')
            ->assertDontSee('PasswordHash');
    }
}
