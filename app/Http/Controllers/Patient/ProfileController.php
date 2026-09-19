<?php

namespace App\Http\Controllers\Patient;

use App\Exceptions\NotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileRequest;
use App\Services\PatientService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Perfil del paciente: solo datos de contacto (§ alcance: sin historia clínica). */
final class ProfileController extends Controller
{
    public function __construct(private readonly PatientService $patients) {}

    public function edit(Request $request): View
    {
        $patient = $this->patients->own($request->user())
            ?? throw new NotFoundException('Tu usuario no tiene un perfil de paciente vinculado.');

        return view('patient.profile', ['patient' => $patient]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $result = $this->patients->updateOwnProfile($request->actor(), $request->validated());

        return redirect()->route('patient.profile')->with('success', $result->userMessage());
    }
}
