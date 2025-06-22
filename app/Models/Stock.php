<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Stock extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'quantity',
        'min_stock',
        'max_stock',
        'purchase_price',
        'sale_price'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'min_stock' => 'integer',
        'max_stock' => 'integer',
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
    ];

    /**
     * Get the product that owns the stock.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }


    public function details(): HasMany
    {
        return $this->hasMany(DetailMovement::class);
    }

    public function movementsThroughDetails(): HasManyThrough
    {
        return $this->hasManyThrough(
            StockMovement::class,
            DetailMovement::class,
            'stock_id',
            'stock_movement_id',
            'id'
        );
    }
}
