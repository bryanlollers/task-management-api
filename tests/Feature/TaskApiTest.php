<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_read_update_and_delete_a_task(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'Portfolio');
        $this->actingAs($user, 'sanctum');

        $created = $this->postJson('/api/tasks', [
            'project_id' => $project->id,
            'title' => 'Write case study',
            'description' => 'Document the API project',
            'status' => 'pending',
            'priority' => 'high',
            'due_date' => '2026-12-31',
            'password' => 'unexpected-task-secret',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Write case study')
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonStructure([
                'data' => [
                    'id', 'project_id', 'title', 'description', 'status', 'priority', 'due_date',
                    'project' => ['id', 'name', 'description', 'status', 'created_at', 'updated_at'],
                    'created_at', 'updated_at',
                ],
            ]);
        $this->assertApiResponseDoesNotExposePassword($created, [
            $user->password,
            'unexpected-task-secret',
        ]);

        $taskId = $created->json('data.id');

        $show = $this->getJson("/api/tasks/{$taskId}")
            ->assertOk()
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.due_date', '2026-12-31');
        $this->assertApiResponseDoesNotExposePassword($show, [$user->password]);

        $updated = $this->putJson("/api/tasks/{$taskId}", [
            'title' => 'Publish case study',
            'status' => 'in_progress',
            'priority' => 'medium',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Publish case study')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.priority', 'medium');
        $this->assertApiResponseDoesNotExposePassword($updated, [$user->password]);

        $this->deleteJson("/api/tasks/{$taskId}")->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $taskId]);
    }

    public function test_task_requests_validate_fields_and_owned_project_ids(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignProject = $this->createProject($otherUser, 'Foreign project');
        $project = $this->createProject($user, 'My project');
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/tasks', [
            'project_id' => $foreignProject->id,
            'title' => '',
            'status' => 'unknown',
            'priority' => 'unknown',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'project_id', 'title', 'status', 'priority',
        ]);

        $task = $this->createTask($user, $project, 'Existing task');
        $this->putJson("/api/tasks/{$task->id}", ['due_date' => 'not-a-date'])
            ->assertUnprocessable()->assertJsonValidationErrors('due_date');
    }

    public function test_users_cannot_read_update_or_delete_another_users_task(): void
    {
        $owner = User::factory()->create();
        $project = $this->createProject($owner, 'Private project');
        $task = $this->createTask($owner, $project, 'Private task');

        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->getJson("/api/tasks/{$task->id}")->assertForbidden();
        $this->putJson("/api/tasks/{$task->id}", ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();
    }

    public function test_task_list_supports_search_filters_and_pagination_with_user_scoping(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = $this->createProject($user, 'Portfolio project');
        $otherProject = $this->createProject($user, 'Other project');

        $matching = $this->createTask($user, $project, 'Portfolio write-up', 'Document the API', 'pending', 'high');
        $this->createTask($user, $project, 'Portfolio checklist', 'No match in description', 'pending', 'low');
        $this->createTask($user, $otherProject, 'Portfolio deployment', 'Deploy the API', 'pending', 'high');
        $this->createTask($user, $project, 'Completed portfolio task', 'Already done', 'completed', 'high');

        $foreignProject = $this->createProject($otherUser, 'Another user project');
        $this->createTask($otherUser, $foreignProject, 'Portfolio secret', 'Hidden from this user', 'pending', 'high');

        $list = $this->actingAs($user, 'sanctum')
            ->getJson("/api/tasks?search=API&project_id={$project->id}&status=pending&priority=high")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('data.0.project.id', $project->id);
        $this->assertApiResponseDoesNotExposePassword($list, [$user->password]);
    }

    public function test_task_list_paginates_and_validates_filter_parameters(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'Many tasks');
        foreach (range(1, 12) as $number) {
            $this->createTask($user, $project, "Task {$number}");
        }

        $list = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tasks?per_page=5&page=2')
            ->assertOk()
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonCount(5, 'data');
        $this->assertApiResponseDoesNotExposePassword($list, [$user->password]);

        $this->getJson('/api/tasks?per_page=101')->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
        $this->getJson('/api/tasks?status=unknown')->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_task_list_filters_due_date_ranges_and_sorts_results(): void
    {
        $user = User::factory()->create();
        $project = $this->createProject($user, 'Date filtered tasks');
        $early = $this->createTask($user, $project, 'Early task');
        $middle = $this->createTask($user, $project, 'Middle task');
        $late = $this->createTask($user, $project, 'Late task');

        $early->update(['due_date' => '2026-10-08']);
        $middle->update(['due_date' => '2026-10-20']);
        $late->update(['due_date' => '2026-11-01']);

        $list = $this->actingAs($user, 'sanctum')
            ->getJson('/api/tasks?due_date_from=2026-10-09&due_date_to=2026-10-31&sort_by=due_date&sort_direction=desc')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $middle->id);
        $this->assertApiResponseDoesNotExposePassword($list, [$user->password]);

        $list = $this->getJson('/api/tasks?due_date_to=2026-10-10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $early->id);
        $this->assertApiResponseDoesNotExposePassword($list, [$user->password]);

        $this->getJson('/api/tasks?due_date_from=2026-10-31&due_date_to=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_date_to');
    }

    private function createProject(User $user, string $name): Project
    {
        return $user->projects()->create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function createTask(
        User $user,
        Project $project,
        string $title,
        ?string $description = null,
        string $status = 'pending',
        string $priority = 'medium',
    ): Task {
        return Task::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'priority' => $priority,
        ]);
    }
}
