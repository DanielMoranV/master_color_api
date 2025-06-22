<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailMovement extends Model
{
    protected $table = 'details_movements';
    protected $fillable = [
        'stock_movement_id',
        'stock_id',
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
