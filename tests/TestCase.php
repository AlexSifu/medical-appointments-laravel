<?php

namespace Tests;

use App\DTO\AuthenticatedUserData;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Usuario autenticado en memoria (sin SQL): loadedAt reciente evita la relectura de permisos. */
    protected function userWith(string $role, array $permissions, int $id = 10, ?int $doctorId = null, ?int $patientId = null): User
    {
        return new User(new AuthenticatedUserData(
            userId: $id,
            username: strtolower($role).'.test',
            email: null,
            firstNames: 'Prueba',
            lastNames: $role,
            roles: [$role],
            permissions: $permissions,
            doctorId: $doctorId,
            patientId: $patientId,
            loadedAt: time(),
        ));
    }

    protected function actingAsRole(string $role, array $permissions, int $id = 10, ?int $doctorId = null, ?int $patientId = null): static
    {
        return $this->actingAs($this->userWith($role, $permissions, $id, $doctorId, $patientId));
    }
}
