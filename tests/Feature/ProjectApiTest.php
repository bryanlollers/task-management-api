<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_read_update_and_delete_a_project(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $created = $this->postJson('/api/projects', [
            'name' => 'Portfolio',
            'description' => 'Portfolio work',
            'status' => 'active',
            'password' => 'unexpected-project-secret',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Portfolio')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description', 'status', 'created_at', 'updated_at'],
            ]);
        $this->assertApiResponseDoesNotExposePassword($created, [
            $user->password,
            'unexpected-project-secret',
        ]);

        $projectId = $created->json('data.id');

        $show = $this->getJson("/api/projects/{$projectId}")
            ->assertOk()
            ->assertJsonPath('data.id', $projectId)
            ->assertJsonPath('data.task_count', 0);
        $this->assertApiResponseDoesNotExposePassword($show, [$user->password]);

        $updated = $this->putJson("/api/projects/{$projectId}", [
            'name' => 'Updated Portfolio',
            'status' => 'completed',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Portfolio')
            ->assertJsonPath('data.status', 'completed');
        $this->assertApiResponseDoesNotExposePassword($updated, [$user->password]);

        $this->deleteJson("/api/projects/{$projectId}")->assertNoContent();
        $this->assertDatabaseMissing('projects', ['id' => $projectId]);
    }

    public function test_project_requests_validate_fields_and_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/projects', [
            'name' => '',
            'status' => 'not-a-status',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'status']);

        $project = $user->projects()->create([
            'name' => 'Valid project',
            'status' => 'active',
        ]);

        $this->putJson("/api/projects/{$project->id}", [
            'status' => 'not-a-status',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_users_cannot_read_update_or_delete_another_users_project(): void
    {
        $owner = User::factory()->create();
        $project = $owner->projects()->create([
            'name' => 'Private project',
            'description' => null,
            'status' => 'active',
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->getJson("/api/projects/{$project->id}")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
        $this->putJson("/api/projects/{$project->id}", ['name' => 'Changed'])->assertForbidden();
        $this->deleteJson("/api/projects/{$project->id}")->assertForbidden();
    }

    public function test_project_list_is_scoped_and_paginated_and_includes_task_counts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $projects = collect(range(1, 11))->map(fn ($number) => $user->projects()->create([
            'name' => "Project {$number}",
            'status' => 'active',
        ]));
        $otherUser->projects()->create(['name' => 'Other user project', 'status' => 'active']);

        Task::create([
            'project_id' => $projects->first()->id,
            'user_id' => $user->id,
            'title' => 'One task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $list = $this->actingAs($user, 'sanctum')
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('meta.total', 11)
            ->assertJsonCount(10, 'data');
        $this->assertApiResponseDoesNotExposePassword($list, [$user->password]);

        $show = $this->getJson("/api/projects/{$projects->first()->id}")
            ->assertOk()
            ->assertJsonPath('data.task_count', 1);
        $this->assertApiResponseDoesNotExposePassword($show, [$user->password]);
    }

    public function test_project_list_supports_search_status_sorting_and_page_size(): void
    {
        $user = User::factory()->create();
        $first = $user->projects()->create([
            'name' => 'A Portfolio API',
            'description' => 'Laravel showcase',
            'status' => 'active',
        ]);
        $user->projects()->create([
            'name' => 'B Portfolio Site',
            'description' => 'Another showcase',
            'status' => 'active',
        ]);
        $user->projects()->create([
            'name' => 'Completed Portfolio',
            'description' => 'Archived showcase',
            'status' => 'completed',
        ]);

        $list = $this->actingAs($user, 'sanctum')
            ->getJson('/api/projects?search=showcase&status=active&sort_by=name&sort_direction=asc&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.id', $first->id);
        $this->assertApiResponseDoesNotExposePassword($list, [$user->password]);

        $this->getJson('/api/projects?status=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }
}
