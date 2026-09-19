<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboards) {}

    public function __invoke(Request $request): View
    {
        $data = $this->dashboards->build($request->user());

        return view('dashboard.'.$data->role, ['data' => $data]);
    }
}
