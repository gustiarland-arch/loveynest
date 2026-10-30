<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Nudge extends Model
{
    /** One-tap signs of affection; the key is what is stored and shown. */
    public const KINDS = ['peluk', 'kangen', 'cium', 'semangat'];

    protected $fillable = ['couple_space_id', 'sender_id', 'kind'];
}
