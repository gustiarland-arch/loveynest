<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyQuote extends Model
{
    protected $fillable = ['couple_space_id', 'quote_date', 'body'];

    protected function casts(): array
    {
        return ['quote_date' => 'date:Y-m-d'];
    }
}
