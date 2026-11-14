<?php

namespace App\Services\Reports;

use App\Models\Scan;
use App\Services\Analysis\ScoringEngine;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Builds the PDF report.
 *
 * The PDF is streamed to the browser and never written to disk: EthioDeploy
 * wipes the filesystem on every deploy, so a stored file would vanish.
 */
class ReportService
{
    public function build(Scan $scan)
    {
        $scan->load(['project', 'scores', 'findings', 'recommendations', 'pages']);

        $findings = $scan->findings()->orderByDesc('priority_score')->get();
        $recommendations = $scan->recommendations()->orderByDesc('priority_score')->get();

        $competitors = $scan->project->competitors()->with('snapshots')->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'url' => $c->url,
                'snapshot' => $c->latestSnapshot(),
            ]);

        $data = [
            'scan' => $scan,
            'project' => $scan->project,
            'scores' => $scan->scores->keyBy('category'),
            'findings' => $findings,
            'recommendations' => $recommendations,
            'competitors' => $competitors,
            'methodology' => ScoringEngine::methodology(),
            'generatedAt' => now(),
        ];

        return Pdf::loadView('reports.pdf', $data)
            ->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true]);
    }

    public function filename(Scan $scan): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($scan->project->name));
        return trim($slug, '-').'-cro-report-'.$scan->id.'.pdf';
    }
}
