<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'image_url',
        'barcode',
        'brand',
        'description',
        'category',
        'presentation',
        'unidad',
        'user_id'
    ];

    public function getImageUrlAttribute($value)
    {
    if (!$value) {
        return null;
    }

    // Si ya es una URL completa, devolverla tal como está
    if (str_starts_with($value, 'http')) {
        return $value;
    }

    // Si es una ruta relativa, generar URL completa
    return Storage::disk('public')->url($value);
}

    /**
     * Get the user that owns the product.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the stock for the product.
     */
    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    /**
     * Get the order details for the product.
     */
    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class);
    }
}
