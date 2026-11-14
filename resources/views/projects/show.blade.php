@extends('layouts.app')
@section('title', $project->name.' — CRO Intelligence')
@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <div class="flex items-center gap-2">
            <h1 class="truncate text-2xl font-semibold tracking-tight text-slate-900">{{ $project->name }}</h1>
            @if ($project->is_primary)
                <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-teal-700">Primary</span>
            @endif
        </div>
        <a href="{{ $project->url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block truncate text-sm text-teal-600 hover:text-teal-700">{{ $project->url }}</a>
        @if ($project->description)
            <p class="mt-2 max-w-2xl text-sm text-slate-600">{{ $project->description }}</p>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <form method="POST" action="{{ route('scans.store', $project) }}">
            @csrf
            <button class="inline-flex items-center gap-2 rounded-lg bg-[#0B1220] px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                Run scan
            </button>
        </form>
        <a href="{{ route('competitors.index', $project) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Competitors</a>
        <a href="{{ route('projects.edit', $project) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Edit</a>
    </div>
</div>

@if ($latest)
    <div class="grid gap-5 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">CRO health score</div>
            @php
                $s = (float) $latest->overall_score;
                $ring = $s >= 75 ? '#0d9488' : ($s >= 50 ? '#f59e0b' : '#e11d48');
            @endphp
            <div class="mt-3 flex items-end gap-3">
                <span class="text-5xl font-semibold tabular-nums" style="color: {{ $ring }}">{{ number_format($s, 0) }}</span>
                <span class="mb-1.5 text-sm text-slate-400">/ 100</span>
            </div>
            <div class="mt-4 space-y-1.5 text-xs text-slate-500">
                <div class="flex justify-between"><span>Confidence</span><span class="font-medium text-slate-700">{{ number_format($latest->confidence, 0) }}%</span></div>
                <div class="flex justify-between"><span>Evidence coverage</span><span class="font-medium text-slate-700">{{ number_format($latest->evidence_coverage, 0) }}%</span></div>
                <div class="flex justify-between"><span>Pages analysed</span><span class="font-medium text-slate-700">{{ $latest->pages_crawled }}</span></div>
            </div>
            <a href="{{ route('scans.show', $latest) }}" class="mt-4 inline-block text-sm font-medium text-teal-600 hover:text-teal-700">View full report →</a>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2">
            <h2 class="text-sm font-semibold text-slate-900">Issues by severity</h2>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ([['Critical', $latest->critical_count, 'rose'], ['High', $latest->high_count, 'amber'], ['Medium', $latest->medium_count, 'sky'], ['Low', $latest->low_count, 'slate']] as [$label, $count, $tone])
                    <div class="rounded-lg border border-slate-100 bg-slate-50/60 p-3">
                        <div class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-semibold tabular-nums text-{{ $tone }}-600">{{ $count }}</div>
                    </div>
                @endforeach
            </div>
            <div class="mt-5 space-y-2.5">
                @foreach ($latest->scores as $score)
                    @php $pct = (float) $score->score; $c = $pct >= 75 ? 'bg-teal-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-rose-500'); @endphp
                    <div>
                        <div class="flex justify-between text-xs">
                            <span class="capitalize text-slate-600">{{ str_replace('_', ' ', $score->category) }} <span class="text-slate-400">({{ $score->weight }}%)</span></span>
                            <span class="font-semibold tabular-nums text-slate-900">{{ number_format($pct, 0) }}</span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full {{ $c }}" style="width: {{ $pct }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@else
    <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
        <p class="text-sm text-slate-500">No completed scan yet. Run your first scan to see findings.</p>
    </div>
@endif

<div class="mt-6 rounded-xl border border-slate-200 bg-white">
    <div class="border-b border-slate-100 px-5 py-4">
        <h2 class="text-sm font-semibold text-slate-900">Scan history</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-medium">Date</th>
                    <th class="px-5 py-3 font-medium">URL</th>
                    <th class="px-5 py-3 font-medium">Score</th>
                    <th class="px-5 py-3 font-medium">Pages</th>
                    <th class="px-5 py-3 font-medium">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($scans as $scan)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $scan->created_at->format('d M Y, H:i') }}</td>
                        <td class="max-w-xs truncate px-5 py-3 text-slate-600">{{ $scan->url }}</td>
                        <td class="px-5 py-3 font-semibold tabular-nums text-slate-900">{{ $scan->overall_score !== null ? number_format($scan->overall_score, 1) : '—' }}</td>
                        <td class="px-5 py-3 tabular-nums text-slate-600">{{ $scan->pages_crawled }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $scan->status === 'completed' ? 'bg-teal-50 text-teal-700' : ($scan->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600') }}">{{ $scan->status }}</span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('scans.show', $scan) }}" class="text-xs font-medium text-teal-600 hover:text-teal-700">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-8 text-center text-slate-400">No scans yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $scans->links() }}</div>
@endsection
