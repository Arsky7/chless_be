<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    /**
     * Get all settings as a key-value pair.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // Transform settings into a flat key-value object
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        // Convert boolean strings to actual booleans if needed
        foreach ($settings as $key => $value) {
            if ($value === 'true' || $value === '1') {
                $settings[$key] = true;
            } elseif ($value === 'false' || $value === '0') {
                $settings[$key] = false;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Bulk update settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $settingsPayload = $request->all();

        DB::beginTransaction();
        try {
            foreach ($settingsPayload as $key => $value) {
                // If it's a boolean, convert it to string for DB storage
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                } elseif (is_array($value)) {
                    $value = json_encode($value);
                }

                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => (string) $value,
                        'type' => is_numeric($value) ? 'string' : (is_bool($value) || $value === 'true' || $value === 'false' ? 'boolean' : 'string')
                    ]
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => Setting::all()->pluck('value', 'key')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
