<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A quiz question written by the AI for one couple space. Its answers use question_index = BASE + id. */
class AiQuizQuestion extends Model
{
    /** Built-in questions use 0..99; AI ones start here so the two never collide. */
    public const BASE = 1000;

    protected $fillable = ['couple_space_id', 'created_by', 'topic', 'question', 'choices'];

    protected function casts(): array
    {
        return ['choices' => 'array'];
    }

    public function index(): int
    {
        return self::BASE + $this->id;
    }
}
