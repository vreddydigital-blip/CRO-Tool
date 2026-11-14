<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CRO Report — {{ $project->name }}</title>
    <style>
        @page { margin: 32px 34px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.5; }
        h1 { font-size: 20px; margin: 0 0 2px; color: #0b1220; }
        h2 { font-size: 12px; margin: 18px 0 6px; color: #0b1220; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        h3 { font-size: 10px; margin: 10px 0 3px; color: #0f172a; }
        .muted { color: #64748b; }
        .small { font-size: 8.5px; }
        .header { border-bottom: 2px solid #0d9488; padding-bottom: 10px; margin-bottom: 14px; }
        .brand { font-size: 9px; letter-spacing: 1.5px; text-transform: uppercase; color: #0d9488; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; border-bottom: 1px solid #e2e8f0; padding: 5px 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .score-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; text-align: center; }
        .score-num { font-size: 30px; font-weight: bold; color: #0d9488; }
        .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7.5px; font-weight: bold; text-transform: uppercase; }
        .critical { background: #ffe4e6; color: #be123c; }
        .high { background: #fef3c7; color: #b45309; }
        .medium { background: #e0f2fe; color: #0369a1; }
        .low { background: #f1f5f9; color: #475569; }
        .finding { border: 1px solid #e2e8f0; padding: 8px; margin-bottom: 7px; page-break-inside: avoid; }
        .evidence { background: #f8fafc; border-left: 2px solid #0d9488; padding: 5px 7px; margin: 4px 0; font-size: 9px; }
        .disclaimer { background: #fffbeb; border: 1px solid #fde68a; padding: 8px; font-size: 8.5px; color: #78350f; margin-top: 10px; }
        .page-break { page-break-before: always; }
        ul { margin: 3px 0 3px 14px; padding: 0; }
        li { margin-bottom: 2px; }
    </style>
</head>
<body>

<div class="header">
    <div class="brand">CRO Intelligence</div>
    <h1>{{ $project->name }} — CRO Health Report</h1>
    <div class="muted small">
        {{ $scan->url }} &nbsp;·&nbsp; Scanned {{ $scan->created_at->format('d M Y, H:i') }} &nbsp;·&nbsp; Report generated {{ $generatedAt->format('d M Y, H:i') }}
    </div>
</div>

<table>
    <tr>
        <td style="width: 25%; padding: 0 6px 0 0;">
            <div class="score-box">
                <div class="small muted">CRO HEALTH SCORE</div>
                <div class="score-num">{{ number_format($scan->overall_score, 0) }}</div>
                <div class="small muted">out of 100</div>
            </div>
        </td>
        <td style="width: 25%; padding: 0 6px;">
            <div class="score-box">
                <div class="small muted">CONFIDENCE</div>
                <div class="score-num" style="color:#0f172a;">{{ number_format($scan->confidence, 0) }}%</div>
                <div class="small muted">{{ $scan->pages_crawled }} page(s)</div>
            </div>
        </td>
        <td style="width: 25%; padding: 0 6px;">
            <div class="score-box">
                <div class="small muted">EVIDENCE COVERAGE</div>
                <div class="score-num" style="color:#0f172a;">{{ number_format($scan->evidence_coverage, 0) }}%</div>
                <div class="small muted">of site inspected</div>
            </div>
        </td>
        <td style="width: 25%; padding: 0 0 0 6px;">
            <div class="score-box">
                <div class="small muted">FINDINGS</div>
                <div class="score-num" style="color:#be123c;">{{ $scan->critical_count + $scan->high_count + $scan->medium_count + $scan->low_count }}</div>
                <div class="small muted">{{ $scan->critical_count }} critical · {{ $scan->high_count }} high</div>
            </div>
        </td>
    </tr>
</table>

<h2>Category scores</h2>
<table>
    <thead>
        <tr><th>Category</th><th>Weight</th><th>Score</th><th>Issues</th></tr>
    </thead>
    <tbody>
        @foreach ($scores as $cat => $score)
            <tr>
                <td style="text-transform: capitalize;">{{ str_replace('_', ' ', $cat) }}</td>
                <td>{{ $score->weight }}%</td>
                <td><strong>{{ number_format($score->score, 0) }}</strong> / 100</td>
                <td>{{ $score->issues_count }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>Prioritised recommendations</h2>
@forelse ($recommendations as $rec)
    <div class="finding">
        <span class="badge {{ $rec->priority_label }}">{{ $rec->priority_label }}</span>
        <strong style="margin-left: 4px;">{{ $rec->title }}</strong>
        <div class="small muted" style="margin-top: 2px;">
            Priority {{ number_format($rec->priority_score, 1) }} = (Impact {{ $rec->impact }} × Confidence {{ $rec->confidence }} × Reach {{ $rec->reach }}) ÷ Effort {{ $rec->effort }}
        </div>
        <div style="margin-top: 3px;">{{ $rec->body }}</div>
    </div>
@empty
    <p class="muted">No recommendations were generated.</p>
@endforelse

<div class="page-break"></div>

<h2>All findings ({{ $findings->count() }})</h2>
@foreach ($findings as $f)
    <div class="finding">
        <span class="badge {{ $f->severity }}">{{ $f->severity }}</span>
        <span class="small muted" style="margin-left: 4px;">{{ $f->rule_id }} · {{ str_replace('_', ' ', $f->category) }}</span>
        <h3>{{ $f->rule_name }}</h3>
        <div class="evidence"><strong>Evidence:</strong> {{ $f->evidence }}</div>
        <div><strong>Why it matters:</strong> {{ $f->explanation }}</div>
        <div style="margin-top: 3px;"><strong>Recommended action:</strong> {{ $f->recommended_action }}</div>
        <div style="margin-top: 2px;"><strong>Implementation:</strong> {{ $f->implementation_guidance }}</div>
        <div class="small muted" style="margin-top: 3px;"><strong>Limitations:</strong> {{ $f->limitations }}</div>
    </div>
@endforeach

@if ($competitors->isNotEmpty())
    <h2>Competitor comparison</h2>
    <table>
        <thead>
            <tr><th>Signal</th><th>{{ $project->name }}</th>@foreach ($competitors as $c)<th>{{ $c['name'] }}</th>@endforeach</tr>
        </thead>
        <tbody>
            <tr>
                <td>CRO score</td>
                <td>{{ number_format($scan->overall_score, 0) }}</td>
                @foreach ($competitors as $c)
                    <td>{{ $c['snapshot'] ? number_format($c['snapshot']->overall_score, 0) : '—' }}</td>
                @endforeach
            </tr>
            <tr>
                <td>CTAs detected</td>
                <td>—</td>
                @foreach ($competitors as $c)
                    <td>{{ $c['snapshot']?->cta_summary['total'] ?? '—' }}</td>
                @endforeach
            </tr>
            <tr>
                <td>Testimonials</td>
                <td>—</td>
                @foreach ($competitors as $c)
                    <td>{{ $c['snapshot']?->trust_summary['testimonials'] ?? '—' }}</td>
                @endforeach
            </tr>
            <tr>
                <td>Word count</td>
                <td>—</td>
                @foreach ($competitors as $c)
                    <td>{{ $c['snapshot']?->content_summary['word_count'] ?? '—' }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
    <p class="small muted">Comparison uses publicly accessible page content only. Competitor conversion rates, revenue and private analytics are not knowable and are not claimed.</p>
@endif

<h2>Methodology</h2>
<p><strong>{{ $methodology['title'] }}</strong></p>
<ul>
    @foreach ($methodology['steps'] as $step)
        <li>{{ $step }}</li>
    @endforeach
</ul>
<p class="small"><strong>Category weights:</strong>
    @foreach ($methodology['weights'] as $cat => $w)
        {{ str_replace('_', ' ', $cat) }} {{ $w }}%@if(!$loop->last) · @endif
    @endforeach
</p>

<h2>Limitations — what this report does not measure</h2>
<ul>
    @foreach ($methodology['not_measured'] as $item)
        <li>{{ $item }}</li>
    @endforeach
</ul>

<div class="disclaimer">
    <strong>Important:</strong> {{ $methodology['disclaimer'] }}
    This report is a heuristic assessment of publicly visible website elements produced by an automated rule engine.
    It is not a conversion rate, not a prediction of conversion, and not a guarantee of uplift. Validate any change with
    a controlled experiment before treating it as an improvement.
</div>

</body>
</html>
