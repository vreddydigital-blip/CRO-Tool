@extends('layouts.app')
@section('title', $finding->rule_name.' — CRO Intelligence')
@section('content')
<div class="mx-auto max-w-3xl">
    <a href="{{ route('scans.show', $scan) }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← Back to scan</a>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        <span class="rounded px-2 py-0.5 text-[11px] font-bold uppercase
            {{ $finding->severity === 'critical' ? 'bg-rose-100 text-rose-700' : ($finding->severity === 'high' ? 'bg-amber-100 text-amber-700' : ($finding->severity === 'medium' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600')) }}">{{ $finding->severity }}</span>
        <span class="font-mono text-xs text-slate-400">{{ $finding->rule_id }}</span>
        <span class="text-xs capitalize text-slate-500">{{ str_replace('_',' ',$finding->category) }}</span>
    </div>
    <h1 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">{{ $finding->rule_name }}</h1>

    <div class="mt-5 space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Evidence</h2>
            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-800">{{ $finding->evidence }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Why it matters</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ $finding->explanation }}</p>
        </div>
        <div class="rounded-xl border border-teal-200 bg-teal-50/50 p-5">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-teal-700">Recommended action</h2>
            <p class="mt-2 text-sm font-medium leading-relaxed text-slate-900">{{ $finding->recommended_action }}</p>
            <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-teal-700">Implementation guidance</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-700">{{ $finding->implementation_guidance }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Limitations</h2>
            <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ $finding->limitations }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Priority calculation</h2>
            <p class="mt-2 text-sm text-slate-700">
                (Impact {{ $finding->impact }} × Confidence {{ $finding->confidence }} × Reach {{ $finding->reach }}) ÷ Effort {{ $finding->effort }}
                = <strong class="tabular-nums">{{ number_format($finding->priority_score, 2) }}</strong>
                → <span class="font-semibold uppercase">{{ $finding->priority_label }}</span>
            </p>
            <p class="mt-2 text-xs text-slate-500">Page: <span class="break-all">{{ $finding->page->url ?? '—' }}</span></p>
        </div>
    </div>
</div>
@endsection
