@extends('layouts.app')
@section('title', 'Competitor Analysis — CRO Intelligence')
@section('content')
<div class="mb-6">
    <a href="{{ route('projects.show', $project) }}" class="text-xs font-medium text-slate-500 hover:text-slate-700">← {{ $project->name }}</a>
    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Competitor Analysis</h1>
    <p class="mt-1 text-sm text-slate-500">Compare publicly visible CRO signals. Up to 2 competitors on the free plan.</p>
</div>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Add competitor</h2>
        <form method="POST" action="{{ route('competitors.store', $project) }}" class="mt-3 space-y-3">
            @csrf
            <input name="name" required maxlength="120" placeholder="Competitor name"
                   class="block w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
            <input name="url" type="url" required maxlength="2048" placeholder="https://competitor.com"
                   class="block w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
            <button class="w-full rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-600">Add</button>
        </form>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-5 lg:col-span-2">
        <h2 class="text-sm font-semibold text-slate-900">Your site</h2>
        @if ($latest)
            <div class="mt-3 flex items-center gap-4">
                <div class="text-3xl font-semibold tabular-nums text-slate-900">{{ number_format($latest->overall_score, 0) }}</div>
                <div class="text-xs text-slate-500">{{ $project->name }}<br>{{ $latest->created_at->format('d M Y') }}</div>
            </div>
        @else
            <p class="mt-3 text-sm text-slate-400">Run a scan on your site first to compare.</p>
        @endif
    </div>
</div>

@if ($competitors->isNotEmpty())
    <div class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3 font-medium">Signal</th>
                        <th class="px-5 py-3 font-medium">{{ $project->name }}</th>
                        @foreach ($competitors as $c)
                            <th class="px-5 py-3 font-medium">{{ $c->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-700">CRO score</td>
                        <td class="px-5 py-3 tabular-nums text-slate-900">{{ $latest ? number_format($latest->overall_score, 0) : '—' }}</td>
                        @foreach ($competitors as $c)
                            @php $snap = $c->latestSnapshot(); @endphp
                            <td class="px-5 py-3 tabular-nums text-slate-900">{{ $snap ? number_format($snap->overall_score, 0) : '—' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-700">CTAs detected</td>
                        <td class="px-5 py-3 text-slate-600">—</td>
                        @foreach ($competitors as $c)
                            @php $snap = $c->latestSnapshot(); @endphp
                            <td class="px-5 py-3 text-slate-600">{{ $snap?->cta_summary['total'] ?? '—' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-700">Testimonials</td>
                        <td class="px-5 py-3 text-slate-600">—</td>
                        @foreach ($competitors as $c)
                            @php $snap = $c->latestSnapshot(); @endphp
                            <td class="px-5 py-3 text-slate-600">{{ $snap?->trust_summary['testimonials'] ?? '—' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-700">Trust score</td>
                        <td class="px-5 py-3 text-slate-600">—</td>
                        @foreach ($competitors as $c)
                            @php $snap = $c->latestSnapshot(); @endphp
                            <td class="px-5 py-3 text-slate-600">{{ $snap?->trust_summary['score'] ?? '—' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-700">Word count</td>
                        <td class="px-5 py-3 text-slate-600">—</td>
                        @foreach ($competitors as $c)
                            @php $snap = $c->latestSnapshot(); @endphp
                            <td class="px-5 py-3 text-slate-600">{{ $snap?->content_summary['word_count'] ?? '—' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-700">Forms / fields</td>
                        <td class="px-5 py-3 text-slate-600">—</td>
                        @foreach ($competitors as $c)
                            @php $snap = $c->latestSnapshot(); @endphp
                            <td class="px-5 py-3 text-slate-600">{{ $snap ? ($snap->form_summary['forms'] ?? 0).' / '.($snap->form_summary['fields'] ?? 0) : '—' }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-3"></td>
                        <td class="px-5 py-3"></td>
                        @foreach ($competitors as $c)
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <form method="POST" action="{{ route('competitors.analyze', [$project, $c]) }}">
                                        @csrf
                                        <button class="rounded-lg bg-[#0B1220] px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Analyse</button>
                                    </form>
                                    <form method="POST" action="{{ route('competitors.destroy', [$project, $c]) }}" onsubmit="return confirm('Remove this competitor?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Remove</button>
                                    </form>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-xs text-slate-500">
        Comparison uses publicly accessible page content only. It does not and cannot know a competitor's actual
        conversion rate, revenue or private analytics.
    </p>
@endif
@endsection
