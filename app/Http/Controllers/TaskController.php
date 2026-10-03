<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\TaskIndexRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    /** List the authenticated user's tasks using the requested filters. */
    public function index(TaskIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $tasks = Task::query()
            ->ownedBy($request->user())
            ->filterBy($filters)
            ->with('project')
            ->orderForListing($filters['sort_by'] ?? null, $filters['sort_direction'] ?? 'asc')
            ->paginate($filters['per_page'] ?? 10)
            ->withQueryString();

        return TaskResource::collection($tasks);
    }

    /** Create a task for the authenticated user. */
    public function store(StoreTaskRequest $request): TaskResource
    {
        $task = $request->user()->tasks()->create($request->validated());

        return new TaskResource($task->load('project'));
    }

    /** Return a task with its associated project. */
    public function show(Task $task): TaskResource
    {
        return new TaskResource($task->load('project'));
    }

    /** Update a task with validated input. */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());

        return new TaskResource($task->load('project'));
    }

    /** Delete a task and return an empty response. */
    public function destroy(Task $task): Response
    {
        $task->delete();

        return response()->noContent();
    }
}
