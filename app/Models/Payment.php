<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'payment_method',
        'payment_code',
        'amount',
        'currency',
        'status',
        'external_id',
        'external_response',
        'document_type',
        'nc_reference',
        'observations'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'external_response' => 'array',
    ];

    /**
     * Get the order that owns the payment.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
