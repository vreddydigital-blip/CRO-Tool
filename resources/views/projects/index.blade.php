@extends('layouts.app')
@section('title', 'Projects — CRO Intelligence')
@section('content')
<div class="mb-6 flex items-end justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Projects</h1>
        <p class="mt-1 text-sm text-slate-500">Websites you are analysing.</p>
    </div>
    <a href="{{ route('projects.create') }}" class="rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-600">New project</a>
</div>

@if ($projects->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
        <p class="text-sm text-slate-500">No projects yet.</p>
        <a href="{{ route('projects.create') }}" class="mt-4 inline-flex rounded-lg bg-teal-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-600">Create your first project</a>
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($projects as $project)
            <a href="{{ route('projects.show', $project) }}" class="group rounded-xl border border-slate-200 bg-white p-5 transition hover:border-teal-300 hover:shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <h2 class="font-semibold text-slate-900 group-hover:text-teal-700">{{ $project->name }}</h2>
                    @if ($project->is_primary)
                        <span class="shrink-0 rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-semibold uppercase text-teal-700">Primary</span>
                    @endif
                </div>
                <p class="mt-1 truncate text-xs text-slate-500">{{ $project->url }}</p>
                <div class="mt-4 flex items-center gap-4 text-xs text-slate-500">
                    <span>{{ $project->scans_count }} scan{{ $project->scans_count === 1 ? '' : 's' }}</span>
                    <span>{{ $project->competitors->count() }} competitor{{ $project->competitors->count() === 1 ? '' : 's' }}</span>
                </div>
            </a>
        @endforeach
    </div>
    <div class="mt-6">{{ $projects->links() }}</div>
@endif
@endsection
