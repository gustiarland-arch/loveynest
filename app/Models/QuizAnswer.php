<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model
{
    protected $fillable = ['couple_space_id', 'user_id', 'question_index', 'self_choice', 'guess_choice'];
}
