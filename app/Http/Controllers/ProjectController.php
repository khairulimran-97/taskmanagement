<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $projects = Project::query()
            ->where('user_id', Auth::id())
            ->orderBy('sort_order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'color' => $project->color,
                    'status' => $project->status,
                    'priority' => $project->priority,
                    'start_date' => $project->start_date?->toISOString(),
                    'due_date' => $project->due_date?->toISOString(),
                    'completion_percentage' => $project->completion_percentage,
                    'sort_order' => $project->sort_order,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                ];
            });

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Projects/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        if (empty($validated['sort_order'])) {
            $maxSortOrder = Project::where('user_id', Auth::id())->max('sort_order') ?? 0;
            $validated['sort_order'] = $maxSortOrder + 1;
        }

        $project = Project::create($validated);

        return redirect()->route('projects.index')
            ->with('success', 'Project created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): Response
    {
        $project = Project::where('user_id', Auth::id())
            ->with([
                'tasks' => function ($query) {
                    $query->with(['tags', 'subtasks.tags'])
                        ->orderBy('sort_order')
                        ->orderBy('created_at');
                },
                'tasks.parentTask'
            ])
            ->findOrFail($id);

        $tags = Tag::where('user_id', Auth::id())
            ->orderBy('name')
            ->get();

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'tags' => $tags,
            'completionPercentage' => $project->getCompletionPercentageAttribute()
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): Response
    {
        $project = Project::where('user_id', Auth::id())->findOrFail($id);

        return Inertia::render('Projects/Edit', [
            'project' => $project
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectRequest $request, string $id): RedirectResponse
    {
        $project = Project::where('user_id', Auth::id())->findOrFail($id);

        $project->update($request->validated());

        return redirect()->route('projects.index', $project)
            ->with('success', 'Project updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): RedirectResponse
    {
        $project = Project::where('user_id', Auth::id())->findOrFail($id);

        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    public function reorder(Request $request)
    {
        $updates = $request->input('updates');

        foreach ($updates as $update) {
            Project::where('id', $update['id'])
                ->update(['sort_order' => $update['sort_order']]);
        }

        return redirect()->route('projects.index');
    }
}
