<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyAnswer extends Model
{
    protected $fillable = ['couple_space_id', 'user_id', 'answer_date', 'question_index', 'answer'];

    protected function casts(): array
    {
        return ['answer_date' => 'date:Y-m-d'];
    }
}
