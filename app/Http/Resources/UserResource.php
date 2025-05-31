<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'role' => $this->role,
            'role_name' => $this->role ? $this->role->name : null,
            'is_active' => $this->is_active,
            'phone' => $this->phone,
            'dni' => $this->dni,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
