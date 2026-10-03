<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['user_id', 'priority'], 'tasks_user_priority_index');
            $table->index(['user_id', 'due_date'], 'tasks_user_due_date_index');
            $table->index(['user_id', 'created_at'], 'tasks_user_created_at_index');
            $table->index(['project_id', 'due_date'], 'tasks_project_due_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_priority_index');
            $table->dropIndex('tasks_user_due_date_index');
            $table->dropIndex('tasks_user_created_at_index');
            $table->dropIndex('tasks_project_due_date_index');
        });
    }
};
