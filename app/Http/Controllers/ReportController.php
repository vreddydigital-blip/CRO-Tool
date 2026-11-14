<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $scans = Scan::where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->with('project')
            ->latest('id')
            ->paginate(20);

        return view('reports.index', compact('scans'));
    }

    public function download(Request $request, Scan $scan, ReportService $reports)
    {
        $this->authorize('view', $scan);

        abort_unless($scan->status === 'completed', 404, 'Report is only available for completed scans.');

        return $reports->build($scan)->download($reports->filename($scan));
    }

    public function stream(Request $request, Scan $scan, ReportService $reports)
    {
        $this->authorize('view', $scan);
        abort_unless($scan->status === 'completed', 404);

        return $reports->build($scan)->stream($reports->filename($scan));
    }
}
