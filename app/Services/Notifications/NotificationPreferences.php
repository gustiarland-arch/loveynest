<?php

namespace App\Services\Notifications;

use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferences
{
    /** @return array{in_app: bool, push: bool, show_preview: bool} */
    public function for(User $user, string $type): array
    {
        return $this->all($user)[$type];
    }

    /** @return array<string, array{in_app: bool, push: bool, show_preview: bool}> */
    public function all(User $user): array
    {
        $stored = $user->notificationPreferences()->get()->keyBy('type');

        return collect(config('usandus.notifications.defaults'))
            ->map(fn (array $default, string $type) => $stored->has($type)
                ? $stored[$type]->only(['in_app', 'push', 'show_preview'])
                : $default)
            ->all();
    }

    /** @param array<string, array{in_app?: bool, push?: bool, show_preview?: bool}> $changes */
    public function update(User $user, array $changes): void
    {
        $current = $this->all($user);

        foreach ($changes as $type => $values) {
            if (! in_array($type, NotificationPreference::TYPES, true)) {
                continue;
            }
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'type' => $type],
                array_merge($current[$type], array_intersect_key($values, $current[$type])),
            );
        }
    }
}
