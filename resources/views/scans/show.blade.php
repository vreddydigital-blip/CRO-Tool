@extends('layouts.app')
@section('title', 'Scan results — CRO Intelligence')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <a href="{{ route('projects.show', $scan->project) }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← {{ $scan->project->name }}</a>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Scan results</h1>
        <p class="mt-1 truncate text-sm text-slate-500">{{ $scan->url }} · {{ $scan->created_at->format('d M Y, H:i') }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        @if ($scan->status === 'completed')
            <a href="{{ route('reports.download', $scan) }}" class="inline-flex items-center gap-2 rounded-lg bg-[#0B1220] px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 10v6m0 0l-3-3m3 3l3-3M4 18v2h16v-2"/></svg>
                Download PDF
            </a>
        @endif
        <form method="POST" action="{{ route('scans.store', $scan->project) }}">
            @csrf
            <button class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Re-scan</button>
        </form>
    </div>
</div>

@if ($scan->status === 'failed')
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-5">
        <h2 class="text-sm font-semibold text-rose-900">Scan failed</h2>
        <p class="mt-1 text-sm text-rose-800">{{ $scan->error_message }}</p>
    </div>
@elseif ($scan->status !== 'completed')
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <p class="text-sm text-slate-600">Scan is {{ $scan->status }} ({{ $scan->progress }}%).</p>
    </div>
@else
    {{-- Score summary --}}
    <div class="grid gap-5 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Overall score</div>
            @php $s = (float) $scan->overall_score; $ring = $s >= 75 ? '#0d9488' : ($s >= 50 ? '#f59e0b' : '#e11d48'); @endphp
            <div class="mt-2 flex items-end gap-2">
                <span class="text-4xl font-semibold tabular-nums" style="color: {{ $ring }}">{{ number_format($s, 0) }}</span>
                <span class="mb-1 text-xs text-slate-400">/ 100</span>
            </div>
            @if ($delta !== null)
                <div class="mt-2 text-xs font-medium {{ $delta >= 0 ? 'text-teal-600' : 'text-rose-600' }}">
                    {{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta), 1) }} vs previous scan
                </div>
            @endif
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Confidence</div>
            <div class="mt-2 text-4xl font-semibold tabular-nums text-slate-900">{{ number_format($scan->confidence, 0) }}<span class="text-lg text-slate-400">%</span></div>
            <p class="mt-2 text-xs text-slate-500">Based on {{ $scan->pages_crawled }} page(s) analysed.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Evidence coverage</div>
            <div class="mt-2 text-4xl font-semibold tabular-nums text-slate-900">{{ number_format($scan->evidence_coverage, 0) }}<span class="text-lg text-slate-400">%</span></div>
            <p class="mt-2 text-xs text-slate-500">How much of the site was inspected.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Findings</div>
            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                <span class="font-semibold text-rose-600">{{ $scan->critical_count }} critical</span>
                <span class="font-semibold text-amber-600">{{ $scan->high_count }} high</span>
                <span class="font-semibold text-sky-600">{{ $scan->medium_count }} medium</span>
                <span class="font-semibold text-slate-500">{{ $scan->low_count }} low</span>
            </div>
        </div>
    </div>

    {{-- Category scores --}}
    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Category scores</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($scan->scores as $score)
                @php $pct = (float) $score->score; $c = $pct >= 75 ? 'bg-teal-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-rose-500'); @endphp
                <div>
                    <div class="flex justify-between text-xs">
                        <span class="capitalize text-slate-600">{{ str_replace('_', ' ', $score->category) }}</span>
                        <span class="font-semibold tabular-nums text-slate-900">{{ number_format($pct, 0) }}</span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full {{ $c }}" style="width: {{ $pct }}%"></div></div>
                    <div class="mt-1 text-[11px] text-slate-400">Weight {{ $score->weight }}% · {{ $score->issues_count }} issue(s)</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- AI / rule-based explanation --}}
    @if (! empty($ai['text']))
        <div class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-900">Plain-language summary</h2>
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                    {{ ($ai['provider'] ?? '') === 'rule_based' ? 'Rule-based' : 'AI-generated' }}
                </span>
            </div>
            <pre class="mt-3 whitespace-pre-wrap font-sans text-sm leading-relaxed text-slate-700">{{ $ai['text'] }}</pre>
            @if (! empty($ai['fallback_reason']))
                <p class="mt-2 text-xs text-amber-700">AI provider unavailable ({{ $ai['fallback_reason'] }}); showing rule-based output.</p>
            @endif
        </div>
    @endif

    {{-- Recommendations --}}
    @if ($recommendations->isNotEmpty())
        <div class="mt-5 rounded-xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Prioritised recommendations</h2>
                <p class="mt-0.5 text-xs text-slate-500">Priority = (Impact × Confidence × Reach) ÷ Effort</p>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($recommendations as $rec)
                    <div class="px-5 py-4">
                        <div class="flex flex-wrap items-start gap-2">
                            <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase
                                {{ $rec->priority_label === 'critical' ? 'bg-rose-100 text-rose-700' : ($rec->priority_label === 'high' ? 'bg-amber-100 text-amber-700' : ($rec->priority_label === 'medium' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600')) }}">
                                {{ $rec->priority_label }}
                            </span>
                            <h3 class="flex-1 text-sm font-medium text-slate-900">{{ $rec->title }}</h3>
                            <span class="text-xs tabular-nums text-slate-400">{{ number_format($rec->priority_score, 1) }}</span>
                        </div>
                        <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $rec->body }}</p>
                        <div class="mt-2 flex flex-wrap gap-3 text-[11px] text-slate-400">
                            <span>Impact {{ $rec->impact }}/5</span>
                            <span>Confidence {{ $rec->confidence }}/5</span>
                            <span>Reach {{ $rec->reach }}/5</span>
                            <span>Effort {{ $rec->effort }}/5</span>
                            <span class="font-mono">{{ $rec->source }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Findings with filters --}}
    <div class="mt-5 rounded-xl border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">All findings</h2>
            <form method="GET" action="{{ route('scans.show', $scan) }}" class="flex flex-wrap items-center gap-2">
                <select name="category" onchange="this.form.submit()" class="rounded-lg border-slate-300 py-1.5 pl-3 pr-8 text-xs focus:border-teal-500 focus:ring-teal-500">
                    <option value="">All categories</option>
                    @foreach (['value_proposition','cta','trust','ux','mobile','content','technical'] as $cat)
                        <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ ucfirst(str_replace('_',' ',$cat)) }}</option>
                    @endforeach
                </select>
                <select name="severity" onchange="this.form.submit()" class="rounded-lg border-slate-300 py-1.5 pl-3 pr-8 text-xs focus:border-teal-500 focus:ring-teal-500">
                    <option value="">All severities</option>
                    @foreach (['critical','high','medium','low'] as $sev)
                        <option value="{{ $sev }}" @selected(request('severity') === $sev)>{{ ucfirst($sev) }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($findings as $finding)
                <a href="{{ route('findings.show', $finding) }}" class="block px-5 py-4 transition hover:bg-slate-50">
                    <div class="flex flex-wrap items-start gap-2">
                        <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase
                            {{ $finding->severity === 'critical' ? 'bg-rose-100 text-rose-700' : ($finding->severity === 'high' ? 'bg-amber-100 text-amber-700' : ($finding->severity === 'medium' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600')) }}">
                            {{ $finding->severity }}
                        </span>
                        <h3 class="flex-1 text-sm font-medium text-slate-900">{{ $finding->rule_name }}</h3>
                        <span class="font-mono text-[11px] text-slate-400">{{ $finding->rule_id }}</span>
                    </div>
                    <p class="mt-1.5 line-clamp-2 text-sm text-slate-600">{{ $finding->explanation }}</p>
                    <div class="mt-1.5 flex flex-wrap gap-3 text-[11px] text-slate-400">
                        <span class="capitalize">{{ str_replace('_', ' ', $finding->category) }}</span>
                        <span>Confidence {{ $finding->confidence }}/5</span>
                        <span>Priority {{ number_format($finding->priority_score, 1) }}</span>
                    </div>
                </a>
            @empty
                <div class="px-5 py-10 text-center text-sm text-slate-400">No findings match these filters.</div>
            @endforelse
        </div>
    </div>
    <div class="mt-4">{{ $findings->links() }}</div>

    {{-- Methodology --}}
    <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-5">
        <h2 class="text-sm font-semibold text-slate-900">Methodology & limitations</h2>
        <p class="mt-2 text-xs leading-relaxed text-slate-600">
            This is a heuristic assessment of publicly visible website elements. It is <strong>not a conversion rate</strong>
            and does not predict or guarantee conversion uplift. Rules inspect server-rendered HTML only; JavaScript-injected
            content, visual design quality, rendered contrast and real visitor behaviour were not measured.
        </p>
        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500">
            <span>Pages analysed: {{ $scan->pages_crawled }}</span>
            <span>Pages failed: {{ $scan->pages_failed }}</span>
            <span>Rules evaluated: {{ $scan->meta['rules_evaluated'] ?? '—' }}</span>
        </div>
    </div>
@endif
@endsection
