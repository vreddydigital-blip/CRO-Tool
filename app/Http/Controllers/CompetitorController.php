<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use App\Models\CompetitorSnapshot;
use App\Models\Project;
use App\Services\Analysis\CtaAnalyzer;
use App\Services\Analysis\PageContext;
use App\Services\Analysis\RuleLibrary;
use App\Services\Analysis\ScoringEngine;
use App\Services\Analysis\TrustAnalyzer;
use App\Services\Security\SafeHttpClient;
use App\Services\Security\UrlValidator;
use Illuminate\Http\Request;

class CompetitorController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $competitors = $project->competitors()->with('snapshots')->get();
        $latest = $project->scans()->where('status', 'completed')->latest('id')->first();

        return view('competitors.index', compact('project', 'competitors', 'latest'));
    }

    public function store(Request $request, Project $project, UrlValidator $validator)
    {
        $this->authorize('update', $project);

        if ($project->competitors()->count() >= 2) {
            return back()->withErrors(['url' => 'The free plan supports up to 2 competitors per project.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $check = $validator->validate($data['url']);
        if (! $check['ok']) {
            return back()->withInput()->withErrors(['url' => $check['error']]);
        }

        $project->competitors()->create([
            'name' => $data['name'],
            'url' => $check['url'],
        ]);

        return back()->with('status', 'Competitor added.');
    }

    public function analyze(Request $request, Project $project, Competitor $competitor, SafeHttpClient $http)
    {
        $this->authorize('update', $project);
        abort_unless($competitor->project_id === $project->id, 404);

        $res = $http->get($competitor->url, ['timeout' => 10]);

        if (! $res['ok'] || ($res['status'] ?? 0) >= 400) {
            return back()->withErrors(['url' => 'Could not fetch competitor page: '.($res['error'] ?? 'HTTP '.($res['status'] ?? '?'))]);
        }

        $ctx = new PageContext($res['body'] ?? '', $competitor->url, $res['status'] ?? 200, $res['time_ms'] ?? null);

        // Run the same rule set so the comparison is apples-to-apples.
        $findings = [];
        foreach (RuleLibrary::all() as $rule) {
            try {
                $r = $rule->evaluate($ctx);
            } catch (\Throwable $e) {
                continue;
            }
            if ($r !== null) {
                $findings[] = [
                    'rule_id' => $rule->id(),
                    'category' => $rule->category(),
                    'severity' => $rule->severity(),
                ];
            }
        }

        $scoring = new ScoringEngine();
        $result = $scoring->score($findings, 1);

        CompetitorSnapshot::create([
            'competitor_id' => $competitor->id,
            'overall_score' => $result['overall'],
            'category_scores' => $result['categories'],
            'cta_summary' => CtaAnalyzer::summary($ctx),
            'trust_summary' => TrustAnalyzer::summary($ctx),
            'content_summary' => [
                'word_count' => $ctx->wordCount,
                'h1' => $ctx->h1(),
                'title' => $ctx->title(),
                'headings' => count($ctx->headings),
            ],
            'form_summary' => [
                'forms' => count($ctx->forms),
                'fields' => array_sum(array_map(fn ($f) => count($f['fields']), $ctx->forms)),
            ],
            'captured_at' => now(),
        ]);

        return back()->with('status', 'Competitor analysed.');
    }

    public function destroy(Request $request, Project $project, Competitor $competitor)
    {
        $this->authorize('update', $project);
        abort_unless($competitor->project_id === $project->id, 404);
        $competitor->delete();

        return back()->with('status', 'Competitor removed.');
    }
}
