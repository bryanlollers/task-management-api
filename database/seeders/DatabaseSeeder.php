<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'password' => 'password',
            ],
        );

        $projects = [
            [
                'name' => 'Personal Website',
                'description' => 'Plan and launch a personal portfolio website.',
                'status' => 'active',
                'tasks' => [
                    ['title' => 'Draft the portfolio homepage', 'description' => 'Write the introduction and choose featured projects.', 'status' => 'in_progress', 'priority' => 'high'],
                    ['title' => 'Review mobile layouts', 'description' => 'Check the main pages at phone and tablet widths.', 'status' => 'pending', 'priority' => 'medium'],
                    ['title' => 'Publish the website', 'description' => 'Deploy the finished portfolio.', 'status' => 'pending', 'priority' => 'low'],
                ],
            ],
            [
                'name' => 'Task API Portfolio Project',
                'description' => 'Build and document a Laravel task management API.',
                'status' => 'active',
                'tasks' => [
                    ['title' => 'Document API endpoints', 'description' => 'Describe authentication, projects, tasks, and filters.', 'status' => 'completed', 'priority' => 'medium'],
                    ['title' => 'Expand feature test coverage', 'description' => 'Cover authorization, validation, CRUD, and filtering.', 'status' => 'in_progress', 'priority' => 'high'],
                    ['title' => 'Prepare Postman examples', 'description' => 'Keep the collection aligned with the API.', 'status' => 'pending', 'priority' => 'low'],
                ],
            ],
            [
                'name' => 'Job Search',
                'description' => 'Track applications and follow-up tasks.',
                'status' => 'completed',
                'tasks' => [
                    ['title' => 'Update resume', 'description' => 'Add recent project work and technical skills.', 'status' => 'completed', 'priority' => 'high'],
                    ['title' => 'Prepare interview notes', 'description' => 'Collect examples of project decisions and outcomes.', 'status' => 'completed', 'priority' => 'medium'],
                ],
            ],
        ];

        foreach ($projects as $projectData) {
            $tasks = $projectData['tasks'];
            unset($projectData['tasks']);

            $project = $user->projects()->firstOrCreate(
                ['name' => $projectData['name']],
                [
                    'description' => $projectData['description'],
                    'status' => $projectData['status'],
                ],
            );

            foreach ($tasks as $taskData) {
                $project->tasks()->firstOrCreate(
                    ['title' => $taskData['title']],
                    [
                        'user_id' => $user->id,
                        'description' => $taskData['description'],
                        'status' => $taskData['status'],
                        'priority' => $taskData['priority'],
                    ],
                );
            }
        }
    }
}
