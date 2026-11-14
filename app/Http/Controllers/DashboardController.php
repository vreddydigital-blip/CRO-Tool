<?php

namespace App\Http\Controllers;

use App\Models\Finding;
use App\Models\Project;
use App\Models\Scan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $projects = Project::ownedBy($user->id)->withCount('scans')->get();
        $scans = Scan::where('user_id', $user->id)->where('status', 'completed');

        $totalScans = (clone $scans)->count();
        $avgScore = (clone $scans)->avg('overall_score');

        $latestScan = (clone $scans)->latest('id')->first();

        $critical = $latestScan?->critical_count ?? 0;
        $high = $latestScan?->high_count ?? 0;

        $recentScans = Scan::where('user_id', $user->id)
            ->with('project')
            ->latest('id')
            ->limit(8)
            ->get();

        // Score trend across completed scans (oldest -> newest).
        $trend = Scan::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereNotNull('overall_score')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'overall_score', 'created_at']);

        $categoryScores = $latestScan
            ? $latestScan->scores()->pluck('score', 'category')
            : collect();

        $topOpportunities = $latestScan
            ? $latestScan->recommendations()->orderByDesc('priority_score')->limit(5)->get()
            : collect();

        return view('dashboard', compact(
            'projects', 'totalScans', 'avgScore', 'latestScan', 'critical', 'high',
            'recentScans', 'trend', 'categoryScores', 'topOpportunities'
        ));
    }
}
