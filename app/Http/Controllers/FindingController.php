<?php

namespace App\Http\Controllers;

use App\Models\Finding;
use App\Models\Scan;
use Illuminate\Http\Request;

class FindingController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $scanIds = Scan::where('user_id', $user->id)->pluck('id');

        $findings = Finding::whereIn('scan_id', $scanIds)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->severity))
            ->when($request->filled('scan'), fn ($q) => $q->where('scan_id', $request->scan))
            ->orderByDesc('priority_score')
            ->paginate(25)
            ->withQueryString();

        $scans = Scan::where('user_id', $user->id)->latest('id')->limit(20)->get();

        return view('findings.index', compact('findings', 'scans'));
    }

    public function show(Request $request, Finding $finding)
    {
        $scan = $finding->scan;
        abort_unless($scan && $scan->user_id === $request->user()->id, 403);

        return view('findings.show', compact('finding', 'scan'));
    }
}
