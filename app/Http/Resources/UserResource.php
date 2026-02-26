<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->role,
            'is_active'  => $this->is_active,
            'avatar_url' => $this->avatar_url,
            'profile'    => $this->whenLoaded('profile', function () {
                return [
                    'phone'      => $this->profile->phone,
                    'gender'     => $this->profile->gender,
                    'birth_date' => $this->profile->birth_date?->toDateString(),
                ];
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
