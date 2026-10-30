<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agreement extends Model
{
    public const STATUSES = ['active', 'paused', 'done'];

    protected $fillable = ['title', 'note', 'status'];
}
