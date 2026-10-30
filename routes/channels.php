<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

// Only the two active members of the couple may listen to their own chat.
Broadcast::channel('couple.{spaceId}.chat', function (User $user, $spaceId) {
    return $user->isActiveMemberOf((int) $spaceId);
});
