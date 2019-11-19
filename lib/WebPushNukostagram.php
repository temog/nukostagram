<?php
require_once(__DIR__  . '/Init.php');
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class WebPushNukostagram
{
    const FILE_SUBSCRIPTIONS = __DIR__ . '/../data/subscriptions.json';

    public static function push($action, $title, $body, $targetEndpoint = null)
    {
        $notifications = [];
        $file = self::FILE_SUBSCRIPTIONS;
        if (! file_exists($file)) {
            return;
        }

        $payload = json_encode(['action' => $action, 'title' => $title, 'body' => $body]);

        $subscriptions = json_decode(file_get_contents($file));
        foreach ($subscriptions as $sub) {

            // endpoint 指定あり
            if ($targetEndpoint && $sub->endpoint != $targetEndpoint) {
                continue;
            }

            $notifications[] = [
                'subscription' => Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                ]),
                'payload' => $payload,
            ];
        }

        $webPush = new WebPush(self::getVapid());
        foreach ($notifications as $notification) {
            $webPush->sendNotification(
                $notification['subscription'],
                $notification['payload']
            );
        }

        $result = $webPush->flush();

        // エラーあり?
        if ($result !== true) {
            $errorEndpoint = [];
            foreach ($result as $r) {
                $success = $r['success'] ?? null;
                if ($success) {
                    continue;
                }
                // 割と強引にエラーになった endpoint を抜き出す
                $error = explode('`', $r['message']);
                list($method, $endpoint) = explode(" ", $error[1]);
                $errorEndpoint[] = $endpoint;
            }
            self::removeErrorEndpoint($errorEndpoint);
        }
    }

    public static function removeErrorEndpoint($endpoint)
    {
        $file = self::FILE_SUBSCRIPTIONS;
        $subscriptions = json_decode(file_get_contents($file), true);
        foreach ($subscriptions as $key => $sub) {
            if (in_array($sub['endpoint'], $endpoint)) {
                array_splice($subscriptions, $key, 1);
            }
        }

        file_put_contents($file, json_encode($subscriptions));
    }

    public static function getVapid()
    {
        return [
            'VAPID' => [
                'subject' => 'mailto:temoog@gmail.com',
                'publicKey' => getenv('WEBPUSH_PUBLIC_KEY'),
                'privateKey' => getenv('WEBPUSH_PRIVATE_KEY'),
            ]
        ];
    }
}
