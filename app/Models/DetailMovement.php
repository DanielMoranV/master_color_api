<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailMovement extends Model
{
    protected $table = 'details_movements';
    protected $fillable = [
        'stock_movement_id',
        'stock_id',
        'quantity',
        'unit_price',
        'previous_stock',
        'new_stock'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
    ];

    public function stockMovement()
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
