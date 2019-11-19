<?php
require_once(__DIR__  . '/Init.php');

/*
    Google さんのapi用クラス

    めんどくさいので、初回だけ data/GooglePhotosLibraryApi_token.json を自前で作ること
    {"token":"アクセストークン","refresh_token":"リフレッシュトークン"}
*/
class GooglePhotosLibraryApi
{
    const TOKEN = __DIR__ . '/../data/' . __CLASS__ . '_token.json';

    /*
        アルバム名からアルバムIDを取得する
        なんか勝手にアルバムIDが変更されるのでその対策・・・(あほか)
    */
    public static function getAlbumIdByName($albumName)
    {
        $data = self::albums();
        foreach ($data->albums as $album) {
            if ($album->title == $albumName) {
                return $album->id;
            }
        }

        return false;
    }

    // アルバム一覧取得
    public static function albums()
    {
        $url = 'https://photoslibrary.googleapis.com/v1/albums';

        $token = self::getAccessToken();
        $response = self::get($url, [], ['Authorization: Bearer ' . $token]);

        $resp = json_decode($response);

        // token expire
        if (isset($resp->error) && $resp->error->code == 401) {
            if (! self::refreshToken()) {
                return false;
            }

            return self::albums();
        }

        return $resp;
    }

    /*
        指定アルバムから画像一覧を取得

        @param string $albumId アルバムのID。album取得apiでわかる
        @param int $pageSize 1回で何件とってくるか。最大500 だったが、2018-08-30 あたりに100に減らされたっぽい
        @param string $nextPageToken 指定すると前回取得からあとの画像がとれる。ようするにskip
        @return array|bool array(Googleから取得したjson) false(処理エラー)
    */
    public static function mediaItemsSearch($albumId, $pageSize = 100, $nextPageToken = null)
    {
        $token = self::getAccessToken();
        $url = 'https://photoslibrary.googleapis.com/v1/mediaItems:search';
        $header = [
            'Authorization: Bearer ' . $token,
        ];
        $param = [
            'albumId' => $albumId,
            'pageSize' => $pageSize,
        ];
        if ($nextPageToken) {
            $param['pageToken'] = $nextPageToken;
        }

        $response = self::post($url, $param, $header, true);
        self::log([
            'method' => __METHOD__,
            'request' => [$url, $header, $param],
            'response' => $response
        ]);
        $result = self::commonResponseCheck($response);
        if ($result === false) {
            return false;
        } elseif ($result == 'retry') {
            return self::mediaItemsSearch($albumId, $pageSize, $nextPageToken);
        }

        return $result;
    }

    /*
        共通レスポンスチェック

        @param string response文字列
        @return array|bool|string array(json decode した配列 処理継続してOK) false(処理中断) retry(もう一度実施)
    */
    public static function commonResponseCheck($response)
    {
        $resp = json_decode($response);
        if (! $resp) {
            return false;
        }

        // token expire
        if (isset($resp->error) && $resp->error->code == 401) {
            if (! self::refreshToken()) {
                return false;
            }

            return 'retry';
        } elseif (isset($resp->error)) {
            return false;
        }

        return $resp;
    }

    public static function getAccessToken()
    {
        $token = json_decode(file_get_contents(self::TOKEN));
        return $token->token;
    }

    public static function refreshToken()
    {
        $token = json_decode(file_get_contents(self::TOKEN));

        $url = 'https://www.googleapis.com/oauth2/v4/token';
        $param = [
            'refresh_token' => $token->refresh_token,
            'client_id' => getenv('GOOGLE_PHOTO_CLIENT_ID'),
            'client_secret' => getenv('GOOGLE_PHOTO_CLIENT_SECRET'),
            'grant_type' => 'refresh_token',
        ];

        $response = self::post($url, $param);
        $resp = json_decode($response);
        if (! isset($resp->access_token)) {
            return false;
        }

        $token->token = $resp->access_token;
        file_put_contents(self::TOKEN, json_encode($token));
        return true;
    }

    public function post($url, $param, $header = [], $json = false, $cookie = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($json) {
            $header[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($param));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));
        }

        if (count($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        }

        $response = curl_exec($ch);
        $errNo = curl_errno($ch);
        $error = curl_error($ch);

        if (CURLE_OK !== $errNo) {
            Logger::error([__METHOD__, 'error no: ' . $errNo, $error]);
        }

        curl_close($ch);


        return $response;
    }

    public function get($url, $param = [], $header = [], $basicIdPassword = null)
    {
        $url = count($param)?
            $url . '?' . http_build_query($param) : $url;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if (count($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        if ($basicIdPassword) {
            curl_setopt($ch, CURLOPT_USERPWD, $basicIdPassword);
        }

        $response = curl_exec($ch);
        $errNo = curl_errno($ch);
        $error = curl_error($ch);

        if (CURLE_OK !== $errNo) {
            Logger::error([__METHOD__, 'error no: ' . $errNo, $error]);
        }

        curl_close($ch);

        return $response;
    }

    private static function log($log)
    {
        $file = __DIR__ . '/../log/' . date("Y-m-d") . '_' . __CLASS__ . '.log';
        file_put_contents($file, date("Y-m-d H:i:s") . ' ' . print_r($log, true) . "\n", FILE_APPEND | LOCK_EX);
    }
}
