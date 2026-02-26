<?php

namespace App\Http\Requests\Api\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:50'],
            'receiver_name' => ['required', 'string', 'max:255'],
            'receiver_phone' => ['required', 'string', 'max:20'],
            'province' => ['required', 'string', 'max:100'],
            'province_code' => ['nullable', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'city_code' => ['nullable', 'string', 'max:20'],
            'district' => ['required', 'string', 'max:100'],
            'district_code' => ['nullable', 'string', 'max:20'],
            'village' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:10'],
            'full_address' => ['required', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
