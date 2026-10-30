<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rule for tasks and events: personal items belong to their owner,
 * shared items to the active members of their space. Both members of a
 * space are equal, so either may edit or delete shared items.
 *
 * Denials are reported as 404 so that guessing ids reveals nothing.
 */
abstract class SpaceItemPolicy
{
    protected function decide(User $user, Model $item): Response
    {
        return $item->isAccessibleBy($user) ? Response::allow() : Response::denyAsNotFound();
    }

    public function view(User $user, Model $item): Response
    {
        return $this->decide($user, $item);
    }

    public function update(User $user, Model $item): Response
    {
        return $this->decide($user, $item);
    }

    public function delete(User $user, Model $item): Response
    {
        return $this->decide($user, $item);
    }
}
