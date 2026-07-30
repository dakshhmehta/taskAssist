<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TagPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Tag $tag): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Tag $tag): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Tag $tag): bool|Response
    {
        if (!$user->is_admin) {
            return false;
        }

        if ($tag->tasks()->count() > 0) {
            return Response::deny("Cannot delete tag '{$tag->name}' — tag has associated tasks.");
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Tag $tag): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Tag $tag): bool|Response
    {
        if (!$user->is_admin) {
            return false;
        }

        if ($tag->tasks()->count() > 0) {
            return Response::deny("Cannot delete tag '{$tag->name}' — tag has associated tasks.");
        }

        return true;
    }

    /**
     * Determine whether the user can view cost information.
     */
    public function viewCost(User $user): bool
    {
        return $user->is_admin;
    }
}
