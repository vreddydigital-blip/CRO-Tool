@extends('layouts.app')
@section('title', 'Reports — CRO Intelligence')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Reports</h1>
    <p class="mt-1 text-sm text-slate-500">Download a PDF report for any completed scan.</p>
</div>

<div class="space-y-3">
    @forelse ($scans as $scan)
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4">
            <div class="min-w-0 flex-1">
                <div class="text-sm font-medium text-slate-900">{{ $scan->project->name ?? 'Project' }}</div>
                <div class="truncate text-xs text-slate-500">{{ $scan->url }} · {{ $scan->created_at->format('d M Y, H:i') }}</div>
            </div>
            <div class="text-sm font-semibold tabular-nums text-slate-900">{{ number_format($scan->overall_score, 1) }}</div>
            <a href="{{ route('reports.download', $scan) }}" class="rounded-lg bg-[#0B1220] px-3.5 py-2 text-xs font-semibold text-white hover:bg-slate-800">Download PDF</a>
            <a href="{{ route('reports.stream', $scan) }}" target="_blank" class="rounded-lg border border-slate-300 px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Preview</a>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-400">No completed scans yet.</div>
    @endforelse
</div>
<div class="mt-4">{{ $scans->links() }}</div>
@endsection
