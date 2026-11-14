<?php

namespace App\Http\Controllers;

use App\Models\Recommendation;
use App\Models\Scan;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $scanIds = Scan::where('user_id', $user->id)->pluck('id');

        $recommendations = Recommendation::whereIn('scan_id', $scanIds)
            ->when($request->filled('priority'), fn ($q) => $q->where('priority_label', $request->priority))
            ->orderByDesc('priority_score')
            ->paginate(20)
            ->withQueryString();

        return view('recommendations.index', compact('recommendations'));
    }
}
