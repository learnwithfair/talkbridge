<?php
namespace RahatulRabbi\TalkBridge\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;

    public function __construct(
        public array   $tokens,
        public ?int    $userId,
        public string  $title,
        public string  $body,
        public array   $data       = [],
        public ?string $type       = null,
        public bool    $isDatabase = true
    ) {}

    public function handle(): void
    {
        if (empty($this->tokens)) return;

        $provider = config('talkbridge.push_notifications.provider', 'none');

        if ($provider === 'none') return;

        if (in_array($provider, ['fcm', 'both'], true)) {
            $this->sendFcm();
        }

        if (in_array($provider, ['web', 'both'], true)) {
            $this->sendWebPush();
        }
    }

    protected function sendFcm(): void
    {
        if (! class_exists(\Kreait\Firebase\Contract\Messaging::class)) {
            Log::warning('TalkBridge: kreait/laravel-firebase not installed. FCM skipped.');
            return;
        }

        try {
            $messaging = app(\Kreait\Firebase\Contract\Messaging::class);

            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($this->title, $this->body))
                ->withData(array_merge($this->data, ['click_action' => 'FLUTTER_NOTIFICATION_CLICK']))
                ->withAndroidConfig(\Kreait\Firebase\Messaging\AndroidConfig::fromArray(['priority' => 'high']))
                ->withApnsConfig(\Kreait\Firebase\Messaging\ApnsConfig::fromArray([
                    'headers' => ['apns-priority' => '10'],
                    'payload' => ['aps' => ['sound' => 'default']],
                ]));

            $report = $messaging->sendMulticast($message, $this->tokens);

            foreach ($report->failures()->getItems() as $failure) {
                Log::warning('TalkBridge FCM failure: ' . $failure->error()->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error('TalkBridge SendPushNotificationJob (FCM): ' . $e->getMessage());
            throw $e;
        }
    }

    protected function sendWebPush(): void
    {
        if (! class_exists(\Minishlink\WebPush\WebPush::class)) {
            Log::warning('TalkBridge: minishlink/web-push not installed. Web push skipped.');
            return;
        }

        try {
            $config   = config('talkbridge.push_notifications.web_push');
            $auth     = [
                'VAPID' => [
                    'subject'    => $config['vapid_subject'],
                    'publicKey'  => $config['vapid_public_key'],
                    'privateKey' => $config['vapid_private_key'],
                ],
            ];

            $webPush = new \Minishlink\WebPush\WebPush($auth);
            $payload = json_encode([
                'title' => $this->title,
                'body'  => $this->body,
                'data'  => $this->data,
            ]);

            // $this->tokens are web push subscription JSON strings for web push
            foreach ($this->tokens as $subscriptionJson) {
                $subscription = \Minishlink\WebPush\Subscription::create(
                    json_decode($subscriptionJson, true)
                );
                $webPush->queueNotification($subscription, $payload);
            }

            foreach ($webPush->flush() as $report) {
                if (! $report->isSuccess()) {
                    Log::warning('TalkBridge Web Push failure: ' . $report->getReason());
                }
            }
        } catch (\Throwable $e) {
            Log::error('TalkBridge SendPushNotificationJob (WebPush): ' . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('TalkBridge SendPushNotificationJob permanently failed', [
            'user_id' => $this->userId,
            'error'   => $exception->getMessage(),
        ]);
    }
}
