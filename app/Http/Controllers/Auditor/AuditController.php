<?php

namespace App\Http\Controllers\Auditor;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterRequest;
use App\Services\AuditService;
use Illuminate\View\View;

/** Bitácora (§84): solo lectura. */
final class AuditController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function __invoke(FilterRequest $request): View
    {
        $f = $request->filters();

        return view('auditor.audit', [
            'page' => $this->audit->search($request->actor(), $f, $request->page()),
            'filters' => $f,
            'actions' => $this->audit->actions($request->actor()),
            'entities' => AuditService::ENTITIES,
        ]);
    }
}
