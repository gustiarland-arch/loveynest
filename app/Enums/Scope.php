<?php

namespace App\Enums;

enum Scope: string
{
    case Personal = 'personal';
    case Shared = 'shared';

    /**
     * Maps the UI's space names to scopes (`mine` → personal, `ours` → shared).
     */
    public static function fromSpace(?string $space): self
    {
        return $space === 'ours' ? self::Shared : self::Personal;
    }

    public function space(): string
    {
        return $this === self::Shared ? 'ours' : 'mine';
    }
}
