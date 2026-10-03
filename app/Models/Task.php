<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    /** Restrict a task query to records owned by the given user. */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user);
    }

    /** Apply supported search, relationship, status, priority, and date filters. */
    #[Scope]
    protected function filterBy(Builder $query, array $filters): void
    {
        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];

            $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (filled($filters['project_id'] ?? null)) {
            $query->where('project_id', $filters['project_id']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        if (filled($filters['priority'] ?? null)) {
            $query->where('priority', $filters['priority']);
        }

        if (filled($filters['due_date_from'] ?? null)) {
            $query->where('due_date', '>=', $filters['due_date_from']);
        }

        if (filled($filters['due_date_to'] ?? null)) {
            $query->where('due_date', '<=', $filters['due_date_to']);
        }
    }

    /** Apply the requested task ordering, with a stable default order. */
    #[Scope]
    protected function orderForListing(
        Builder $query,
        ?string $sortBy = null,
        string $direction = 'asc',
    ): void {
        if ($sortBy === null) {
            $query->latest()->orderByDesc('id');

            return;
        }

        $query->orderBy($sortBy, $direction)->orderBy('id', $direction);
    }

    /** Get the project this task belongs to. */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Get the user who owns this task. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
