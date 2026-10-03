<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
    ];

    /** Restrict a project query to records owned by the given user. */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user);
    }

    /** Apply the supported text and status filters to a project query. */
    #[Scope]
    protected function filterBy(Builder $query, array $filters): void
    {
        if (filled($filters['search'] ?? null)) {
            $search = $filters['search'];

            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }
    }

    /** Apply the requested project ordering, with a stable default order. */
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

    /** Get the user who owns this project. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Get the tasks associated with this project. */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
