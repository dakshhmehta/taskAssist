<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserLeave;

class UserLeavePolicy
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
    public function view(User $user, UserLeave $userLeave): bool
    {
        return ($user->id == $userLeave->user_id || $user->is_admin);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserLeave $userLeave): bool
    {
        return ($user->id == $userLeave->user_id || $user->is_admin);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserLeave $userLeave): bool
    {
        return ($userLeave->status == 'NEW' && ($user->id == $userLeave->user_id || $user->is_admin));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserLeave $userLeave): bool
    {
        return $user->is_admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserLeave $userLeave): bool
    {
        return $user->is_admin;
    }
}
