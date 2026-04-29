<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

class ConfigController extends Controller
{
    /**
     * Display Config Manager.
     */
    public function index(): Factory|View
    {
        abort_unless(auth()->user()->group->is_admin, 403);

        return view('Staff.config.index');
    }
}
