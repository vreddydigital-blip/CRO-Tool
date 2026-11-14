@extends('layouts.app')
@section('title', 'Settings — CRO Intelligence')
@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Settings</h1>

    <form method="POST" action="{{ route('settings.update') }}" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        @csrf @method('PATCH')
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Name</label>
            <input id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required maxlength="120"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required maxlength="190"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm focus:border-teal-500 focus:ring-teal-500">
        </div>
        <button class="rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-600">Save</button>
    </form>

    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-semibold text-slate-900">AI recommendations</h2>
        <div class="mt-2 flex items-center gap-2">
            <span class="h-2 w-2 rounded-full {{ $aiConfigured ? 'bg-teal-500' : 'bg-slate-300' }}"></span>
            <span class="text-sm text-slate-700">
                {{ $aiConfigured ? 'External AI provider configured ('.$aiModel.')' : 'Rule-based explainer (no AI key configured)' }}
            </span>
        </div>
        <p class="mt-2 text-xs leading-relaxed text-slate-500">
            The app works fully without an AI key. To enable AI explanations, set <code class="rounded bg-slate-100 px-1">AI_API_KEY</code>,
            <code class="rounded bg-slate-100 px-1">AI_BASE_URL</code> and <code class="rounded bg-slate-100 px-1">AI_MODEL</code> in the
            environment. Keys are read from the environment only and never stored in the database.
        </p>
    </div>

    <div class="mt-5 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-sm font-semibold text-slate-900">Plan limits</h2>
        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-xs text-slate-500">Max pages per scan</dt><dd class="font-medium text-slate-900">{{ $limits['max_pages'] }}</dd></div>
            <div><dt class="text-xs text-slate-500">Max crawl depth</dt><dd class="font-medium text-slate-900">{{ $limits['max_depth'] }}</dd></div>
            <div><dt class="text-xs text-slate-500">Scans per hour</dt><dd class="font-medium text-slate-900">{{ $limits['scans_per_hour'] }}</dd></div>
            <div><dt class="text-xs text-slate-500">Competitors per project</dt><dd class="font-medium text-slate-900">{{ $limits['competitors_per_project'] }}</dd></div>
        </dl>
        <p class="mt-3 text-xs text-slate-500">These limits exist because the app runs on a free hosting tier with 256 MB RAM and no background workers.</p>
    </div>
</div>
@endsection
