<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use Illuminate\Http\Request;

class ScanHistoryController extends Controller
{
    public function index(Request $request)
    {
        $scans = Scan::where('user_id', $request->user()->id)
            ->with('project')
            ->latest('id')
            ->paginate(20);

        return view('history.index', compact('scans'));
    }
}
