<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogRequest;
use App\Services\AdminService;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Especialidades y sedes (sin borrado físico: se desactivan). */
final class CatalogController extends Controller
{
    public function __construct(
        private readonly AdminService $admin,
        private readonly CatalogService $catalogs,
    ) {}

    public function specialties(): View
    {
        return view('admin.catalogs.specialties', ['items' => $this->catalogs->specialties(false)]);
    }

    public function saveSpecialty(CatalogRequest $request, ?int $specialty = null): RedirectResponse
    {
        $result = $this->admin->saveSpecialty($request->actor(), $specialty, $request->validated());

        return redirect()->route('admin.specialties.index')->with('success', $result->userMessage());
    }

    public function branches(): View
    {
        return view('admin.catalogs.branches', ['items' => $this->catalogs->branches(false)]);
    }

    public function saveBranch(CatalogRequest $request, ?int $branch = null): RedirectResponse
    {
        $result = $this->admin->saveBranch($request->actor(), $branch, $request->validated());

        return redirect()->route('admin.branches.index')->with('success', $result->userMessage());
    }
}
