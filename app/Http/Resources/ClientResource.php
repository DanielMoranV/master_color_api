<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
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
            'client_type' => $this->client_type,
            'identity_document' => $this->identity_document,
            'document_type' => $this->document_type,
            'token_version' => $this->token_version,
            'verification_token' => $this->verification_token,
            'email_verified_at' => $this->email_verified_at,
            'phone' => $this->phone,
            'addresses' => $this->whenLoaded('addresses'),
            'main_address' => $this->whenLoaded('addresses', function () {
                return $this->addresses->where('is_main', true)->first();
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
