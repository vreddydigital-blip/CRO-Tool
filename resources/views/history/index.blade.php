@extends('layouts.app')
@section('title', 'Scan History — CRO Intelligence')
@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Scan History</h1>
    <p class="mt-1 text-sm text-slate-500">All scans across your projects.</p>
</div>

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-3 font-medium">Date</th>
                    <th class="px-5 py-3 font-medium">Project</th>
                    <th class="px-5 py-3 font-medium">Score</th>
                    <th class="px-5 py-3 font-medium">Critical</th>
                    <th class="px-5 py-3 font-medium">High</th>
                    <th class="px-5 py-3 font-medium">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($scans as $scan)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap px-5 py-3 text-slate-600">{{ $scan->created_at->format('d M Y, H:i') }}</td>
                        <td class="px-5 py-3 text-slate-700">{{ $scan->project->name ?? '—' }}</td>
                        <td class="px-5 py-3 font-semibold tabular-nums text-slate-900">{{ $scan->overall_score !== null ? number_format($scan->overall_score, 1) : '—' }}</td>
                        <td class="px-5 py-3 tabular-nums text-rose-600">{{ $scan->critical_count }}</td>
                        <td class="px-5 py-3 tabular-nums text-amber-600">{{ $scan->high_count }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $scan->status === 'completed' ? 'bg-teal-50 text-teal-700' : ($scan->status === 'failed' ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600') }}">{{ $scan->status }}</span>
                        </td>
                        <td class="px-5 py-3 text-right"><a href="{{ route('scans.show', $scan) }}" class="text-xs font-medium text-teal-600 hover:text-teal-700">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-slate-400">No scans yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $scans->links() }}</div>
@endsection
