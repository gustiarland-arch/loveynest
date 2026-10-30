<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DateExpense extends Model
{
    public const CATEGORIES = ['makan', 'jalan', 'hadiah', 'nonton', 'lainnya'];

    protected $fillable = ['title', 'note', 'amount', 'payer_share', 'category', 'spent_on'];

    protected function casts(): array
    {
        return ['spent_on' => 'date', 'settled_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /** What the other person owes the payer for this expense. */
    public function owed(): int
    {
        return (int) round($this->amount * (100 - $this->payer_share) / 100);
    }
}
