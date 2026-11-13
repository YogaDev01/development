<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterDetail extends Model
{
    protected $fillable = ['letter_id', 'header', 'body', 'meta', 'items', 'tax', 'note', 'closing'];

    protected $casts = ['meta' => 'array', 'items' => 'array', 'tax' => 'float'];

    public function subtotal(): int
    {
        return (int) collect($this->items ?? [])
            ->sum(fn ($i) => (int) ($i['price'] ?? 0) * (float) ($i['qty'] ?? 0));
    }

    public function taxAmount(): int
    {
        return (int) round($this->subtotal() * (float) $this->tax / 100);
    }

    public function grandTotal(): int
    {
        return $this->subtotal() + $this->taxAmount();
    }
}
