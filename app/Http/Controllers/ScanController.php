<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Scan;
use App\Services\Analysis\ScanRunner;
use App\Services\Security\UrlValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ScanController extends Controller
{
    public function store(Request $request, Project $project, ScanRunner $runner, UrlValidator $validator)
    {
        $this->authorize('view', $project);

        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:2048'],
        ]);

        $url = $data['url'] ?? $project->primary_url ?? $project->url;

        $check = $validator->validate($url);
        if (! $check['ok']) {
            return back()->withErrors(['url' => $check['error']]);
        }

        // Reap abandoned scans. Scans run synchronously inside the request that
        // starts them, so a queued/running row older than 5 minutes was left
        // behind by a crashed or timed-out request and must not block the user.
        Scan::where('user_id', $request->user()->id)
            ->whereIn('status', ['queued', 'running'])
            ->where('created_at', '<', now()->subMinutes(5))
            ->update([
                'status' => 'failed',
                'error_message' => 'Scan was abandoned before it finished (request ended or timed out).',
                'completed_at' => now(),
            ]);

        // One scan at a time per user (free-plan constraint).
        $running = Scan::where('user_id', $request->user()->id)
            ->whereIn('status', ['queued', 'running'])
            ->exists();

        if ($running) {
            return back()->withErrors(['url' => 'A scan is already running. Wait for it to finish before starting another.']);
        }

        // Rate limit: 10 scans per hour per user.
        $key = 'scan:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors(['url' => 'Scan limit reached (10 per hour). Try again later.']);
        }
        RateLimiter::hit($key, 3600);

        $scan = Scan::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'url' => $check['url'],
            'status' => 'queued',
        ]);

        // Synchronous execution: no persistent worker on the free plan.
        $runner->run($scan);

        return redirect()->route('scans.show', $scan)->with('status', 'Scan completed.');
    }

    public function show(Request $request, Scan $scan)
    {
        $this->authorize('view', $scan);

        $scan->load(['project', 'scores', 'logs']);

        $findings = $scan->findings()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->severity))
            ->orderByDesc('priority_score')
            ->paginate(20)
            ->withQueryString();

        $recommendations = $scan->recommendations()->orderByDesc('priority_score')->limit(10)->get();

        // Optional AI layer. With no key configured this is the deterministic
        // rule-based explainer, and the view labels it as such.
        $ai = app(\App\Services\Ai\AiManager::class)->explain(
            $scan->findings()->orderByDesc('priority_score')->limit(15)->get()
                ->map(fn ($f) => [
                    'rule_id' => $f->rule_id,
                    'rule_name' => $f->rule_name,
                    'severity' => $f->severity,
                    'evidence' => $f->evidence,
                    'recommended_action' => $f->recommended_action,
                ])->all(),
            $scan->url
        );

        $previous = Scan::where('project_id', $scan->project_id)
            ->where('status', 'completed')
            ->where('id', '<', $scan->id)
            ->latest('id')
            ->first();

        $delta = ($previous && $scan->overall_score !== null && $previous->overall_score !== null)
            ? round($scan->overall_score - $previous->overall_score, 2)
            : null;

        return view('scans.show', compact('scan', 'findings', 'recommendations', 'previous', 'delta', 'ai'));
    }

    public function destroy(Request $request, Scan $scan)
    {
        $this->authorize('delete', $scan);
        $project = $scan->project;
        $scan->delete();

        return redirect()->route('projects.show', $project)->with('status', 'Scan deleted.');
    }
}
