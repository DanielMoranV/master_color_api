<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'movement_type' => $this->movement_type,
            'reason' => $this->reason,
            'voucher_number' => $this->voucher_number,
            'user_id' => $this->user_id,
            'canceled_at' => $this->canceled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->whenLoaded('user'),
            'details' => $this->whenLoaded('details'),
            'total_quantity' => $this->details->sum('quantity'),
            'total_value' => $this->details->sum(function ($detail) {
                return $detail->quantity * $detail->unit_price;
            }),
        ];
    }
}
