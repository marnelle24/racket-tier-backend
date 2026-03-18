<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\View\View;

class AdminFacilityController extends Controller
{
    public function index(): View
    {
        $facilities = Facility::query()
            ->withCount(['games', 'presences'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.facilities.index', compact('facilities'));
    }

    public function show(Facility $facility): View
    {
        $facility->loadCount(['games', 'presences']);

        return view('admin.facilities.show', compact('facility'));
    }
}
