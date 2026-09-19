<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UserRequest;
use App\Http\Requests\UserRoleRequest;
use App\Services\AdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Usuarios (§82): alta, edición, activar/desactivar, roles, restablecer contraseña. */
final class UserController extends Controller
{
    public function __construct(private readonly AdminService $admin) {}

    public function index(FilterRequest $request): View
    {
        $f = $request->filters();

        return view('admin.users.index', [
            'page' => $this->admin->searchUsers(
                $request->actor(), $f['q'] ?? null, $f['role'] ?? null,
                isset($f['active']) ? (bool) $f['active'] : null, $request->page(),
            ),
            'filters' => $f,
            'roles' => $this->admin->roles(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['roles' => $this->admin->roles()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $result = $this->admin->createUser($request->actor(), $request->validated());

        return redirect()->route('admin.users.show', $result->entityId)->with('success', $result->userMessage());
    }

    public function show(Request $request, int $user): View
    {
        return view('admin.users.show', [
            'account' => $this->admin->findUser($request->user(), $user),
            'roles' => $this->admin->roles(),
            'permissions' => $this->admin->effectivePermissions($user),
        ]);
    }

    public function update(UserRequest $request, int $user): RedirectResponse
    {
        $result = $this->admin->updateUser($request->actor(), $user, $request->validated());

        return redirect()->route('admin.users.show', $user)->with('success', $result->userMessage());
    }

    public function role(UserRoleRequest $request, int $user): RedirectResponse
    {
        $data = $request->validated();
        $result = $this->admin->assignRole($request->actor(), $user, $data['role'], (bool) $data['assign']);

        return redirect()->route('admin.users.show', $user)->with('success', $result->userMessage());
    }

    public function password(ResetPasswordRequest $request, int $user): RedirectResponse
    {
        $result = $this->admin->resetPassword($request->actor(), $user, $request->validated('password'));

        return redirect()->route('admin.users.show', $user)->with('success', $result->userMessage());
    }
}
