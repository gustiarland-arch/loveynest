<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Scope;
use Closure;

/**
 * The client may only say *which* of the user's spaces ("mine" or "ours");
 * owner and space ids always come from the session.
 */
trait ChoosesSpace
{
    /** @return array<int, mixed> */
    protected function spaceRules(): array
    {
        return ['required', 'in:mine,ours', function (string $attribute, mixed $value, Closure $fail) {
            if ($value === 'ours' && ! $this->user()->activeSpaceId()) {
                $fail('Hubungkan akun dengan pasanganmu dulu untuk memakai Our Space.');
            }
        }];
    }

    public function scope(): Scope
    {
        return Scope::fromSpace($this->validated('space'));
    }
}
