@extends('layouts.app')
@section('title', 'New project — CRO Intelligence')
@section('content')
<div class="mx-auto max-w-xl">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">New project</h1>
    <p class="mt-1 text-sm text-slate-500">Add a website to analyse. Only public http/https URLs are accepted.</p>

    <form method="POST" action="{{ route('projects.store') }}" class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Project name</label>
            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="120"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                   placeholder="Acme marketing site">
        </div>
        <div>
            <label for="url" class="block text-sm font-medium text-slate-700">Website URL</label>
            <input id="url" name="url" type="url" value="{{ old('url') }}" required maxlength="2048"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                   placeholder="https://example.com">
            <p class="mt-1.5 text-xs text-slate-500">Private IPs, localhost and internal hosts are blocked.</p>
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-slate-700">Description <span class="text-slate-400">(optional)</span></label>
            <textarea id="description" name="description" rows="3" maxlength="1000"
                      class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500"
                      placeholder="What this site is for, target audience, main conversion goal.">{{ old('description') }}</textarea>
        </div>
        <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-600">Create project</button>
            <a href="{{ route('projects.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
