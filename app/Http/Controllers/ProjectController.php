<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectIndexRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProjectController extends Controller
{
    /** List the authenticated user's projects using the requested filters. */
    public function index(ProjectIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $projects = Project::query()
            ->ownedBy($request->user())
            ->filterBy($filters)
            ->withCount('tasks')
            ->orderForListing($filters['sort_by'] ?? null, $filters['sort_direction'] ?? 'asc')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return ProjectResource::collection($projects);
    }

    /** Create a project for the authenticated user. */
    public function store(StoreProjectRequest $request): ProjectResource
    {
        $project = $request->user()->projects()->create($request->validated());

        return new ProjectResource($project);
    }

    /** Return a project with its task count. */
    public function show(Project $project): ProjectResource
    {
        $project->loadCount('tasks');

        return new ProjectResource($project);
    }

    /** Update a project with validated input. */
    public function update(
        UpdateProjectRequest $request,
        Project $project
    ): ProjectResource {
        $project->update($request->validated());

        $project->loadCount('tasks');

        return new ProjectResource($project);
    }

    /** Delete a project and return an empty response. */
    public function destroy(Project $project): Response
    {
        $project->delete();

        return response()->noContent();
    }
}
