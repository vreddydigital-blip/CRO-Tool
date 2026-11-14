@extends('layouts.app')
@section('title', 'CRO Findings — CRO Intelligence')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">CRO Findings</h1>
    <p class="mt-1 text-sm text-slate-500">Every issue detected across your scans.</p>
</div>

<form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
    <select name="scan" onchange="this.form.submit()" class="rounded-lg border-slate-300 py-2 pl-3 pr-8 text-sm focus:border-teal-500 focus:ring-teal-500">
        <option value="">All scans</option>
        @foreach ($scans as $s)
            <option value="{{ $s->id }}" @selected((string) request('scan') === (string) $s->id)>#{{ $s->id }} · {{ $s->created_at->format('d M') }}</option>
        @endforeach
    </select>
    <select name="category" onchange="this.form.submit()" class="rounded-lg border-slate-300 py-2 pl-3 pr-8 text-sm focus:border-teal-500 focus:ring-teal-500">
        <option value="">All categories</option>
        @foreach (['value_proposition','cta','trust','ux','mobile','content','technical'] as $cat)
            <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ ucfirst(str_replace('_',' ',$cat)) }}</option>
        @endforeach
    </select>
    <select name="severity" onchange="this.form.submit()" class="rounded-lg border-slate-300 py-2 pl-3 pr-8 text-sm focus:border-teal-500 focus:ring-teal-500">
        <option value="">All severities</option>
        @foreach (['critical','high','medium','low'] as $sev)
            <option value="{{ $sev }}" @selected(request('severity') === $sev)>{{ ucfirst($sev) }}</option>
        @endforeach
    </select>
</form>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="divide-y divide-slate-100">
        @forelse ($findings as $finding)
            <a href="{{ route('findings.show', $finding) }}" class="block px-5 py-4 hover:bg-slate-50">
                <div class="flex flex-wrap items-start gap-2">
                    <span class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase
                        {{ $finding->severity === 'critical' ? 'bg-rose-100 text-rose-700' : ($finding->severity === 'high' ? 'bg-amber-100 text-amber-700' : ($finding->severity === 'medium' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600')) }}">{{ $finding->severity }}</span>
                    <h3 class="flex-1 text-sm font-medium text-slate-900">{{ $finding->rule_name }}</h3>
                    <span class="font-mono text-[11px] text-slate-400">{{ $finding->rule_id }}</span>
                </div>
                <p class="mt-1.5 line-clamp-2 text-sm text-slate-600">{{ $finding->explanation }}</p>
                <div class="mt-1.5 flex flex-wrap gap-3 text-[11px] text-slate-400">
                    <span class="capitalize">{{ str_replace('_',' ',$finding->category) }}</span>
                    <span>Scan #{{ $finding->scan_id }}</span>
                    <span>Priority {{ number_format($finding->priority_score, 1) }}</span>
                </div>
            </a>
        @empty
            <div class="px-5 py-12 text-center text-sm text-slate-400">No findings yet. Run a scan first.</div>
        @endforelse
    </div>
</div>
<div class="mt-4">{{ $findings->links() }}</div>
@endsection
