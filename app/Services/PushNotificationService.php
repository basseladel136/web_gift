<?php

namespace App\Services;

use App\Models\DeviceToken;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService
{
    private WebPush $webPush;

    public function __construct()
    {
        $auth = [
            'VAPID' => [
                'subject'    => config('services.vapid.subject'),
                'publicKey'  => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ];

        $this->webPush = new WebPush($auth);
    }

    /**
     * Send notification to a single user.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('user_id', $userId)->get();

        foreach ($tokens as $token) {
            $this->send($token, $title, $body, $data);
        }

        $this->flush();
    }

    /**
     * Send notification to all users.
     */
    public function sendToAll(string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::all();

        foreach ($tokens as $token) {
            $this->send($token, $title, $body, $data);
        }

        $this->flush();
    }

    /**
     * Queue a single notification.
     */
    private function send(DeviceToken $token, string $title, string $body, array $data = []): void
    {
        $subscription = Subscription::create([
            'endpoint'  => $token->endpoint,
            'keys'      => [
                'p256dh' => $token->public_key,
                'auth'   => $token->auth_token,
            ],
        ]);

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'data'  => $data,
        ]);

        $this->webPush->queueNotification($subscription, $payload);
    }

    /**
     * Send all queued notifications and remove expired subscriptions.
     */
    private function flush(): void
    {
        foreach ($this->webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                // Remove expired or invalid subscriptions
                DeviceToken::where('endpoint', $report->getRequest()->getUri()->__toString())
                    ->delete();
            }
        }
    }
}