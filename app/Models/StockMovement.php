<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'movement_type',
        'reason',
        'user_id',
        'voucher_number',
        'canceled_at'
    ];

    protected $casts = [
        'canceled_at' => 'datetime',
    ];

    /**
     * Get the user that owns the movement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(DetailMovement::class);
    }

    public function getTotalQuantityAttribute(): int
    {
        return $this->details->sum('quantity');
    }

    public function getAveragePriceAttribute(): float
    {
        $details = $this->details->where('unit_price', '>', 0);
        
        if ($details->isEmpty()) {
            return 0;
        }

        $totalValue = $details->sum(function ($detail) {
            return $detail->quantity * $detail->unit_price;
        });

        $totalQuantity = $details->sum('quantity');

        return $totalQuantity > 0 ? $totalValue / $totalQuantity : 0;
    }

    public function getTotalValueAttribute(): float
    {
        return $this->details->sum(function ($detail) {
            return $detail->quantity * ($detail->unit_price ?? 0);
        });
    }
}
