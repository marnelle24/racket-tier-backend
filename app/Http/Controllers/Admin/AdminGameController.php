<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\View\View;

class AdminGameController extends Controller
{
    public function index(): View
    {
        $games = Game::query()
            ->with(['creator:id,name,email', 'facility:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(30);

        return view('admin.games.index', compact('games'));
    }

    public function show(Game $game): View
    {
        $game->load([
            'creator:id,name,email',
            'facility:id,name,country,address',
            'participants.user:id,name,email',
        ]);

        return view('admin.games.show', compact('game'));
    }
}
