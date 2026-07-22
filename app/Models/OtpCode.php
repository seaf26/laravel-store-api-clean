<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

#[Fillable([
    'phone',
    'code_hash',
    'purpose',
    'expires_at',
    'consumed_at',
    'delivered_at',
    'delivery_failed_at',
    'superseded_at',
])]
#[Hidden(['code_hash'])]
class OtpCode extends Model
{
    use Prunable;

    /**
     * Spent codes are kept briefly so the rate limiter can count them, then
     * pruned by `php artisan model:prune` to keep the table bounded.
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDay());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'delivery_failed_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * Only a successfully delivered, current, unspent code can be redeemed.
     */
    public function isUsable(): bool
    {
        return ! is_null($this->delivered_at)
            && is_null($this->delivery_failed_at)
            && is_null($this->superseded_at)
            && is_null($this->consumed_at)
            && $this->expires_at->isFuture();
    }
}
