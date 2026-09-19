<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class LoginController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $data = $this->auth->attempt($request->string('login')->toString(), $request->string('password')->toString());
        } catch (BusinessRuleException $e) {
            return back()
                ->withInput($request->only('login'))
                ->withErrors(['login' => $e->getMessage()]);
        }

        // Evita fijación de sesión: nuevo id antes de guardar la identidad.
        $request->session()->regenerate();
        Auth::guard()->login(new User($data));

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->auth->logout($user);
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sesión cerrada correctamente.');
    }
}
