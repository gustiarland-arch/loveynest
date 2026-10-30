<?php

namespace App\Policies;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/** Letters are the writer's words: only the writer may change or remove them. */
class JournalEntryPolicy extends SpaceItemPolicy
{
    public function update(User $user, Model $item): Response
    {
        return $this->ownLetter($user, $item) ?? parent::update($user, $item);
    }

    public function delete(User $user, Model $item): Response
    {
        return $this->ownLetter($user, $item) ?? parent::delete($user, $item);
    }

    private function ownLetter(User $user, Model $item): ?Response
    {
        if (! $item instanceof JournalEntry || ! $item->isLetter()) {
            return null;
        }

        return $item->isAccessibleBy($user) && $item->created_by === $user->id ? Response::allow() : Response::denyAsNotFound();
    }
}
