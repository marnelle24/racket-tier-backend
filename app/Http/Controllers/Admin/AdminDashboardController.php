<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Game;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'userCount' => User::query()->count(),
            'adminCount' => User::query()->where('role', User::ROLE_ADMIN)->count(),
            'gameCount' => Game::query()->count(),
            'facilityCount' => Facility::query()->count(),
            'recentGames' => Game::query()
                ->with(['creator:id,name,email', 'facility:id,name'])
                ->latest('updated_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
