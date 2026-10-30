<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventRsvp extends Model
{
    public const STATUSES = ['yes', 'maybe', 'no'];

    protected $fillable = ['event_id', 'user_id', 'status'];
}
