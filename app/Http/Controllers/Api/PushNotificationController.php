<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Push Notifications
 *
 * APIs for managing push notification subscriptions.
 */
class PushNotificationController extends Controller
{
    public function __construct(private PushNotificationService $push) {}

    /**
     * Get VAPID public key
     *
     * Returns the VAPID public key needed by the frontend
     * to subscribe to push notifications.
     * No authentication required.
     *
     * @response 200 {
     *   "public_key": "BDUru1Ct9b6Eyzit9FK4..."
     * }
     */
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'public_key' => config('services.vapid.public_key'),
        ]);
    }

    /**
     * Save push subscription
     *
     * Saves the browser push subscription for the authenticated user.
     * Call this after the user grants notification permission in the browser.
     *
     * @authenticated
     *
     * @bodyParam endpoint string required Browser push endpoint URL. Example: https://fcm.googleapis.com/fcm/send/xxx
     * @bodyParam public_key string required Subscription public key (p256dh). Example: BNcRdreALRFXTkOOUHK1...
     * @bodyParam auth_token string required Subscription auth token. Example: tBHItJI5svbpez7KI4CCXg
     * @bodyParam platform string optional Platform type: web, android, ios. Default: web. Example: web
     *
     * @response 200 {
     *   "message": "Subscription saved.",
     *   "public_key": "BDUru1Ct9b6Eyzit9FK4..."
     * }
     */
    public function saveToken(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint'   => ['required', 'string'],
            'public_key' => ['required', 'string'],
            'auth_token' => ['required', 'string'],
            'platform'   => ['nullable', 'in:web,android,ios'],
        ]);

        DeviceToken::updateOrCreate(
            ['endpoint' => $request->endpoint],
            [
                'user_id'    => $request->user()->id,
                'public_key' => $request->public_key,
                'auth_token' => $request->auth_token,
                'platform'   => $request->platform ?? 'web',
            ]
        );

        return response()->json([
            'message'    => __('messages.token_saved'),
            'public_key' => config('services.vapid.public_key'),
        ]);
    }

    /**
     * Remove push subscription
     *
     * Removes the push subscription for the authenticated user.
     * Call this on logout to stop receiving notifications.
     *
     * @authenticated
     *
     * @bodyParam endpoint string required The browser push endpoint to remove. Example: https://fcm.googleapis.com/fcm/send/xxx
     *
     * @response 200 { "message": "Subscription removed." }
     */
    public function removeToken(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        DeviceToken::where('user_id', $request->user()->id)
            ->where('endpoint', $request->endpoint)
            ->delete();

        return response()->json([
            'message' => __('messages.token_removed'),
        ]);
    }

    /**
     * Broadcast notification (Admin)
     *
     * Sends a push notification to all subscribed users.
     *
     * @authenticated
     *
     * @bodyParam title string required Notification title. Example: New Offer!
     * @bodyParam body string required Notification body. Example: Get 20% off all watches today!
     *
     * @response 200 { "message": "Notification sent to all users." }
     * @response 422 { "message": "The title field is required." }
     */
    public function broadcast(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'body'  => ['required', 'string', 'max:500'],
        ]);

        $this->push->sendToAll(
            $request->title,
            $request->body,
            ['type' => 'broadcast']
        );

        return response()->json([
            'message' => __('messages.notification_sent'),
        ]);
    }
}