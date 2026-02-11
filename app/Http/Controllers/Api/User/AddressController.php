<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\User\StoreAddressRequest;
use App\Http\Requests\Api\User\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * Display a listing of user addresses.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()
            ->addresses()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => AddressResource::collection($addresses),
            'meta' => [
                'current_page' => $addresses->currentPage(),
                'last_page' => $addresses->lastPage(),
                'total' => $addresses->total(),
                'per_page' => $addresses->perPage()
            ]
        ]);
    }

    /**
     * Store a newly created address.
     *
     * @param StoreAddressRequest $request
     * @return JsonResponse
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $address = $request->user()->addresses()->create([
                'label' => $request->label,
                'receiver_name' => $request->receiver_name,
                'receiver_phone' => $request->receiver_phone,
                'province' => $request->province,
                'province_code' => $request->province_code,
                'city' => $request->city,
                'city_code' => $request->city_code,
                'district' => $request->district,
                'district_code' => $request->district_code,
                'village' => $request->village,
                'postal_code' => $request->postal_code,
                'full_address' => $request->full_address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'is_default' => $request->is_default ?? false,
                'notes' => $request->notes
            ]);

            // If this is default address, unset other defaults
            if ($address->is_default) {
                $request->user()->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            return response()->json([
                'message' => 'Address created successfully',
                'success' => true,
                'data' => new AddressResource($address)
            ], 201);
        });
    }

    /**
     * Display the specified address.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $id): JsonResponse
    {
        $address = $request->user()
            ->addresses()
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new AddressResource($address)
        ]);
    }

    /**
     * Update the specified address.
     *
     * @param UpdateAddressRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateAddressRequest $request, $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $address = $request->user()
                ->addresses()
                ->findOrFail($id);

            $address->update([
                'label' => $request->label,
                'receiver_name' => $request->receiver_name,
                'receiver_phone' => $request->receiver_phone,
                'province' => $request->province,
                'province_code' => $request->province_code,
                'city' => $request->city,
                'city_code' => $request->city_code,
                'district' => $request->district,
                'district_code' => $request->district_code,
                'village' => $request->village,
                'postal_code' => $request->postal_code,
                'full_address' => $request->full_address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'is_default' => $request->is_default ?? $address->is_default,
                'notes' => $request->notes
            ]);

            // If this is default address, unset other defaults
            if ($address->is_default) {
                $request->user()->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            return response()->json([
                'message' => 'Address updated successfully',
                'success' => true,
                'data' => new AddressResource($address)
            ]);
        });
    }

    /**
     * Remove the specified address.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $address = $request->user()
            ->addresses()
            ->findOrFail($id);

        $address->delete();

        return response()->json([
            'message' => 'Address deleted successfully',
            'success' => true
        ]);
    }

    /**
     * Set address as default.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function setDefault(Request $request, $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $address = $request->user()
                ->addresses()
                ->findOrFail($id);

            // Unset all other defaults
            $request->user()->addresses()
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);

            // Set this as default
            $address->update(['is_default' => true]);

            return response()->json([
                'message' => 'Default address updated successfully',
                'success' => true,
                'data' => new AddressResource($address)
            ]);
        });
    }
}
