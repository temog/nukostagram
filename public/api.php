<?php
require(__DIR__  . '/../lib/Init.php');

// 主に web push 用 のファイル
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

require(__DIR__ . '/../lib/Image.php');

// public key, private key は https://web-push-codelab.glitch.me/ から取得する
define('WEBPUSH_PUBLIC_KEY', getenv('WEBPUSH_PUBLIC_KEY'));
define('WEBPUSH_PRIVATE_KEY', getenv('WEBPUSH_PRIVATE_KEY'));
define('DATA_DIR', __DIR__ . '/../data/');
define('FILE_SUBSCRIPTIONS', DATA_DIR . 'subscriptions.json');


$action = $_REQUEST['action'] ?? null;

// 公開鍵取得
if ($action == 'getPublicKey') {
    responseJson(true, WEBPUSH_PUBLIC_KEY);
}

// 通知データ保存
if ($action == 'registerSubscription') {
    $subscription = $_REQUEST['subscription'] ?? null;
    registerSubscription($subscription);
    responseJson(true);
}

// 画像取得
if ($action == 'getImage') {
    $limit = (int) $_REQUEST['limit'] ?? 10;
    $page = (int) $_REQUEST['page'] ?? 0;
    $options = [
        'sort' => ['creationTime' => -1],
        'limit' => $limit,
        'skip' => $limit * $page
    ];
    $images = Image::find([], $options);
    responseJson(true, ['images' => $images]);
}


// push test
if ($action == 'pushTest') {
    $title = $_REQUEST['title'] ?? 'デフォルト テスト タイトル';
    $body = $_REQUEST['body'] ?? 'デフォルト テスト メッセージ';
    push($title, $body);
    responseJson(true);
}

function registerSubscription($subscription)
{
    if (! $subscription) {
        return;
    }

    $file = FILE_SUBSCRIPTIONS;
    $data = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
    }

    $sub = json_decode($subscription);

    // endpoint が同じデータがあったら差し替える
    $exists = false;
    foreach ($data as $key => $s) {
        if ($s['endpoint'] == $sub->endpoint) {
            $data[$key]['p256dh'] = $sub->keys->p256dh;
            $data[$key]['auth'] = $sub->keys->auth;
            $exists = true;
        }
    }

    if (! $exists) {
        $data[] = [
            'endpoint' => $sub->endpoint,
            'p256dh' => $sub->keys->p256dh,
            'auth' => $sub->keys->auth,
        ];
    }

    file_put_contents($file, json_encode($data));
}

function push($title, $body)
{
    $notifications = [];
    $file = FILE_SUBSCRIPTIONS;
    if (! file_exists($file)) {
        return;
    }

    $payload = json_encode(['title' => $title, 'body' => $body]);

    $subscriptions = json_decode(file_get_contents($file));
    foreach ($subscriptions as $sub) {
        $notifications[] = [
            'subscription' => Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->p256dh,
                'authToken' => $sub->auth,
            ]),
            'payload' => $payload,
        ];
    }

    $webPush = new WebPush(getVapid());
    foreach ($notifications as $notification) {
        $webPush->sendNotification(
            $notification['subscription'],
            $notification['payload']
        );
    }

    $result = $webPush->flush();

    // エラーあり
    if ($result !== true) {
        $errorEndpoint = [];
        foreach ($result as $r) {
            // 割と強引にエラーになった endpoint を抜き出す
            $error = explode('`', $r['message']);
            list($method, $endpoint) = explode(" ", $error[1]);
            $errorEndpoint[] = $endpoint;
        }
        removeErrorEndpoint($errorEndpoint);
    }
}

function removeErrorEndpoint($endpoint)
{
    $file = FILE_SUBSCRIPTIONS;
    $subscriptions = json_decode(file_get_contents($file), true);
    foreach ($subscriptions as $key => $sub) {
        if (in_array($sub['endpoint'], $endpoint)) {
            array_splice($subscriptions, $key, 1);
        }
    }

    file_put_contents($file, json_encode($subscriptions));
}

function getVapid()
{
    return [
        'VAPID' => [
            'subject' => 'mailto:temoog@gmail.com',
            'publicKey' => WEBPUSH_PUBLIC_KEY,
            'privateKey' => WEBPUSH_PRIVATE_KEY,
        ]
    ];
}

function responseJson($status, $data = null)
{
    header("Access-control-allow-origin: *");
    header("Content-Type: application/json; charset=utf-8");

    $resp = [
        'status' => $status ? 'success' : 'error',
        'data' => $data,
    ];
    echo json_encode($resp);
    exit;
}
