<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'receiver_name' => $this->receiver_name,
            'receiver_phone' => $this->receiver_phone,
            'province' => $this->province,
            'province_code' => $this->province_code,
            'city' => $this->city,
            'city_code' => $this->city_code,
            'district' => $this->district,
            'district_code' => $this->district_code,
            'village' => $this->village,
            'postal_code' => $this->postal_code,
            'full_address' => $this->full_address,
            'is_default' => (bool)$this->is_default,
            'notes' => $this->notes,
        ];
    }
}
