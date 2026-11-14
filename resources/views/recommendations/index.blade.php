@extends('layouts.app')
@section('title', 'Recommendations — CRO Intelligence')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Recommendations</h1>
    <p class="mt-1 text-sm text-slate-500">Ranked by (Impact × Confidence × Reach) ÷ Effort.</p>
</div>

<form method="GET" class="mb-4">
    <select name="priority" onchange="this.form.submit()" class="rounded-lg border-slate-300 py-2 pl-3 pr-8 text-sm focus:border-teal-500 focus:ring-teal-500">
        <option value="">All priorities</option>
        @foreach (['critical','high','medium','low'] as $p)
            <option value="{{ $p }}" @selected(request('priority') === $p)>{{ ucfirst($p) }}</option>
        @endforeach
    </select>
</form>

<div class="space-y-3">
    @forelse ($recommendations as $rec)
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-start gap-2">
                <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase
                    {{ $rec->priority_label === 'critical' ? 'bg-rose-100 text-rose-700' : ($rec->priority_label === 'high' ? 'bg-amber-100 text-amber-700' : ($rec->priority_label === 'medium' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600')) }}">{{ $rec->priority_label }}</span>
                <h2 class="flex-1 text-sm font-semibold text-slate-900">{{ $rec->title }}</h2>
                <span class="text-xs tabular-nums text-slate-400">{{ number_format($rec->priority_score, 1) }}</span>
            </div>
            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $rec->body }}</p>
            <div class="mt-2 flex flex-wrap gap-3 text-[11px] text-slate-400">
                <span>Impact {{ $rec->impact }}</span><span>Confidence {{ $rec->confidence }}</span>
                <span>Reach {{ $rec->reach }}</span><span>Effort {{ $rec->effort }}</span>
                <span class="font-mono">{{ $rec->source }}</span>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-400">No recommendations yet.</div>
    @endforelse
</div>
<div class="mt-4">{{ $recommendations->links() }}</div>
@endsection
