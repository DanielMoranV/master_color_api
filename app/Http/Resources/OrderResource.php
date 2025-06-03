<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'client_id' => $this->client_id,
            'user_id' => $this->user_id,
            'delivery_address_id' => $this->delivery_address_id,
            'subtotal' => $this->subtotal,
            'shipping_cost' => $this->shipping_cost,
            'discount' => $this->discount,
            'total' => $this->subtotal + $this->shipping_cost - $this->discount,
            'status' => $this->status,
            'codigo_payment' => $this->codigo_payment,
            'observations' => $this->observations,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'order_details' => $this->whenLoaded('orderDetails', function() {
                return OrderDetailResource::collection($this->orderDetails);
            }),
            'delivery_address' => $this->whenLoaded('deliveryAddress', function() {
                return new AddressResource($this->deliveryAddress);
            }),
        ];
    }
}
