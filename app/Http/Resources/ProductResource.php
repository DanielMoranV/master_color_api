<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => $this->name,
            'sku' => $this->sku,
            'image_url' => $this->image_url,
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'description' => $this->description,
            'presentation' => $this->presentation,
            'category' => $this->category,
            'unidad' => $this->unidad,
            'user_id' => $this->user_id,
            'user_name' => optional($this->user)->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
