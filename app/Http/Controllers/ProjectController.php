<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Security\UrlValidator;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::ownedBy($request->user()->id)
            ->withCount('scans')
            ->with('competitors')
            ->latest()
            ->paginate(12);

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        return view('projects.create');
    }

    public function store(Request $request, UrlValidator $validator)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $check = $validator->validate($data['url']);
        if (! $check['ok']) {
            return back()->withInput()->withErrors(['url' => $check['error']]);
        }

        $project = Project::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'url' => $check['url'],
            'primary_url' => $check['url'],
            'description' => $data['description'] ?? null,
            'is_primary' => Project::ownedBy($request->user()->id)->count() === 0,
        ]);

        return redirect()->route('projects.show', $project)->with('status', 'Project created.');
    }

    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $scans = $project->scans()->latest('id')->paginate(10);
        $latest = $project->scans()->where('status', 'completed')->latest('id')->first();

        return view('projects.show', compact('project', 'scans', 'latest'));
    }

    public function edit(Request $request, Project $project)
    {
        $this->authorize('update', $project);
        return view('projects.edit', compact('project'));
    }

    public function update(Request $request, Project $project, UrlValidator $validator)
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'string', 'max:2048'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $check = $validator->validate($data['url']);
        if (! $check['ok']) {
            return back()->withInput()->withErrors(['url' => $check['error']]);
        }

        $project->update([
            'name' => $data['name'],
            'url' => $check['url'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    public function destroy(Request $request, Project $project)
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project deleted.');
    }

    public function setPrimary(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        Project::ownedBy($request->user()->id)->update(['is_primary' => false]);
        $project->update(['is_primary' => true]);

        return back()->with('status', 'Primary website updated.');
    }
}
