@extends('layouts.app')
@section('title', 'Edit project — CRO Intelligence')
@section('content')
<div class="mx-auto max-w-xl">
    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Edit project</h1>

    <form method="POST" action="{{ route('projects.update', $project) }}" class="mt-6 space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf @method('PUT')
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Project name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $project->name) }}" required maxlength="120"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
        </div>
        <div>
            <label for="url" class="block text-sm font-medium text-slate-700">Website URL</label>
            <input id="url" name="url" type="url" value="{{ old('url', $project->url) }}" required maxlength="2048"
                   class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-slate-700">Description</label>
            <textarea id="description" name="description" rows="3" maxlength="1000"
                      class="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500">{{ old('description', $project->description) }}</textarea>
        </div>
        <div class="flex items-center gap-3 pt-1">
            <button type="submit" class="rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-600">Save changes</button>
            <a href="{{ route('projects.show', $project) }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
        </div>
    </form>

    <form method="POST" action="{{ route('projects.destroy', $project) }}" class="mt-4"
          onsubmit="return confirm('Delete this project and all its scans? This cannot be undone.')">
        @csrf @method('DELETE')
        <button class="text-sm font-medium text-rose-600 hover:text-rose-700">Delete project</button>
    </form>
</div>
@endsection
