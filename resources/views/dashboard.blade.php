@extends('layouts.app')

@section('title', 'Dashboard — CRO Intelligence')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Conversion health across your websites.</p>
    </div>
    @if ($projects->isNotEmpty())
        <form method="POST" action="{{ route('scans.store', $projects->firstWhere('is_primary', true) ?? $projects->first()) }}">
            @csrf
            <button class="inline-flex items-center gap-2 rounded-lg bg-[#0B1220] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
                </svg>
                Quick scan
            </button>
        </form>
    @endif
</div>

@if ($projects->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
        <div class="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-slate-100">
            <svg class="h-6 w-6 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M12 4v16m8-8H4"/>
            </svg>
        </div>
        <h2 class="mt-4 text-base font-semibold text-slate-900">Add your first website</h2>
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
            CRO Intelligence crawls your site and scores it against 86 conversion best-practice rules.
        </p>
        <a href="{{ route('projects.create') }}"
           class="mt-5 inline-flex rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-600">
            Create project
        </a>
    </div>
@else
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Projects</div>
            <div class="mt-2 text-3xl font-semibold tabular-nums text-slate-900">{{ $projects->count() }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Scans</div>
            <div class="mt-2 text-3xl font-semibold tabular-nums text-slate-900">{{ $totalScans }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Avg. CRO score</div>
            <div class="mt-2 text-3xl font-semibold tabular-nums text-slate-900">
                {{ $avgScore !== null ? number_format($avgScore, 1) : '—' }}
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Critical / High</div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-3xl font-semibold tabular-nums text-rose-600">{{ $critical }}</span>
                <span class="text-lg font-medium tabular-nums text-amber-600">/ {{ $high }}</span>
            </div>
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900">Score trend</h2>
                <span class="text-xs text-slate-400">Last {{ $trend->count() }} completed scans</span>
            </div>
            <div class="mt-4 h-56">
                @if ($trend->count() > 1)
                    <canvas id="trendChart"></canvas>
                @else
                    <div class="grid h-full place-items-center text-sm text-slate-400">
                        Run at least two scans to see a trend.
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Category scores</h2>
            @if ($categoryScores->isNotEmpty())
                <div class="mt-4 space-y-3">
                    @foreach ($categoryScores as $cat => $score)
                        @php
                            $pct = (float) $score;
                            $color = $pct >= 75 ? 'bg-teal-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-rose-500');
                        @endphp
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="capitalize text-slate-600">{{ str_replace('_', ' ', $cat) }}</span>
                                <span class="font-semibold tabular-nums text-slate-900">{{ number_format($pct, 0) }}</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $color }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-4 text-sm text-slate-400">No completed scan yet.</p>
            @endif
        </div>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Recent scans</h2>
                <a href="{{ route('history.index') }}" class="text-xs font-medium text-teal-600 hover:text-teal-700">View all</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($recentScans as $scan)
                    <a href="{{ route('scans.show', $scan) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-slate-900">{{ $scan->project->name ?? 'Project' }}</div>
                            <div class="truncate text-xs text-slate-500">{{ $scan->url }}</div>
                        </div>
                        <div class="text-right">
                            @if ($scan->overall_score !== null)
                                <div class="text-sm font-semibold tabular-nums text-slate-900">{{ number_format($scan->overall_score, 1) }}</div>
                            @endif
                            <div class="text-[11px] text-slate-400">{{ $scan->created_at->diffForHumans() }}</div>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium
                            {{ $scan->status === 'completed' ? 'bg-teal-50 text-teal-700' : ($scan->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600') }}">
                            {{ $scan->status }}
                        </span>
                    </a>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-400">No scans yet.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Top opportunities</h2>
                <a href="{{ route('recommendations.index') }}" class="text-xs font-medium text-teal-600 hover:text-teal-700">View all</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($topOpportunities as $rec)
                    <div class="px-5 py-3">
                        <div class="flex items-start gap-2">
                            <span class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase
                                {{ $rec->priority_label === 'critical' ? 'bg-rose-100 text-rose-700' : ($rec->priority_label === 'high' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                {{ $rec->priority_label }}
                            </span>
                            <p class="text-sm leading-snug text-slate-700">{{ $rec->title }}</p>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-slate-400">No recommendations yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endif

@if ($trend->count() > 1)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('trendChart');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: @json($trend->map(fn ($s) => $s->created_at->format('d M'))),
            datasets: [{
                label: 'CRO score',
                data: @json($trend->map(fn ($s) => (float) $s->overall_score)),
                borderColor: '#0d9488',
                backgroundColor: 'rgba(13,148,136,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#0d9488',
                tension: 0.35,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { min: 0, max: 100, grid: { color: '#f1f5f9' }, ticks: { color: '#94a3b8', font: { size: 11 } } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } }
            }
        }
    });
});
</script>
@endif
@endsection
