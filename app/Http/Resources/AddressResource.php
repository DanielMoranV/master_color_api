<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
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
            'address_full' => $this->address_full,
            'district' => $this->district,
            'province' => $this->province,
            'department' => $this->department,
            'postal_code' => $this->postal_code,
            'reference' => $this->reference,
            'is_main' => $this->is_main,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
