<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    /**
     * Display all settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $group = $request->get('group');
        $query = Setting::query();

        if ($group) {
            $query->where('group', $group);
        }

        $settings = $query->orderBy('group')->orderBy('order')->get();

        return response()->json([
            'success' => true,
            'data' => $settings->groupBy('group')
        ]);
    }

    /**
     * Display settings by group.
     *
     * @param string $group
     * @return JsonResponse
     */
    public function group($group): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $settings = Setting::where('group', $group)
            ->orderBy('order')
            ->get()
            ->mapWithKeys(function($item) {
                return [$item['key'] => $item['value']];
            });

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update settings.
     *
     * @param UpdateSettingRequest $request
     * @return JsonResponse
     */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        foreach ($request->settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => $request->group ?? 'general'
                ]
            );
        }

        // Clear settings cache
        Cache::forget('app_settings');

        return response()->json([
            'message' => 'Settings updated successfully',
            'success' => true
        ]);
    }

    /**
     * Get general settings.
     *
     * @return JsonResponse
     */
    public function general(): JsonResponse
    {
        $settings = Cache::remember('app_settings', 3600, function() {
            return Setting::pluck('value', 'key')->toArray();
        });

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Get shipping settings.
     *
     * @return JsonResponse
     */
    public function shipping(): JsonResponse
    {
        $settings = Setting::where('group', 'shipping')
            ->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Get payment settings.
     *
     * @return JsonResponse
     */
    public function payment(): JsonResponse
    {
        $settings = Setting::where('group', 'payment')
            ->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Get email settings.
     *
     * @return JsonResponse
     */
    public function email(): JsonResponse
    {
        $this->authorize('viewEmailSettings', Setting::class);

        $settings = Setting::where('group', 'email')
            ->pluck('value', 'key');

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update email settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $this->authorize('updateEmailSettings', Setting::class);

        $request->validate([
            'mail_mailer' => 'required|in:smtp,sendmail,mailgun,ses,postmark',
            'mail_host' => 'required_if:mail_mailer,smtp',
            'mail_port' => 'required_if:mail_mailer,smtp|numeric',
            'mail_username' => 'nullable|string',
            'mail_password' => 'nullable|string',
            'mail_encryption' => 'nullable|in:tls,ssl',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string'
        ]);

        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'mail_')) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'group' => 'email',
                        'type' => 'string'
                    ]
                );
            }
        }

        // Update .env file (would need custom implementation)
        // $this->updateEnvFile($request->all());

        return response()->json([
            'message' => 'Email settings updated successfully',
            'success' => true
        ]);
    }

    /**
     * Test email configuration.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testEmail(Request $request): JsonResponse
    {
        $this->authorize('testEmail', Setting::class);

        $request->validate([
            'email' => 'required|email'
        ]);

        try {
            // Send test email
            \Mail::raw('This is a test email from CHLESS Fashion.', function($message) use ($request) {
                $message->to($request->email)
                    ->subject('Test Email from CHLESS');
            });

            return response()->json([
                'message' => 'Test email sent successfully',
                'success' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send test email: ' . $e->getMessage(),
                'success' => false
            ], 422);
        }
    }
}
