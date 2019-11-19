<?php
require_once(__DIR__  . '/Init.php');
require_once(__DIR__ . '/Mongo.php');
require_once(__DIR__ . '/GooglePhotosLibraryApi.php');

/*
    google api から取得した画像情報を突っ込むやつ

    # データ構造
    - id           photo library の 画像ID
    - baseUrl      画像URLそのもの
    - creationTime 画像の撮影日
    - width        画像横幅
    - height       画像縦幅
    - renewNumber   photo libraryから画像取得するたびにインクリメントする番号 (消えた画像の削除に使う)

    # index
    db.image.createIndex({id:1}, {background:true, unique:true})
    db.image.createIndex({creationTime:1}, {background:true})

*/
class Image extends M
{
    protected static $collection = 'image';

    const RENEW_NUMBER = __DIR__ . '/../data/renewNumber.json';

    // 指定アルバムの全画像を取得して保存する
    public static function importAlbumImage($albumId, $nextPageToken = null)
    {
        $items = GooglePhotosLibraryApi::mediaItemsSearch($albumId, 100, $nextPageToken);
        if (! $items) {
            return 0;
        }

        $newImage = 0;
        foreach ($items->mediaItems as $item) {
            echo $item->id . "\n";

            if (! self::set(
                $item->id,
                $item->baseUrl,
                $item->mediaMetadata->creationTime,
                $item->mediaMetadata->width,
                $item->mediaMetadata->height

            )) {
                self::error(['upsert failed', $item]);
            }

            $newImage += self::$upsertedCount;
        }

        // nextPageToken があったら次へ
        if (isset($items->nextPageToken)) {
            $newImage += self::importAlbumImage($albumId, $items->nextPageToken);
        }

        return $newImage;
    }

    public static function get($id)
    {
        $where = ['id' => $id];
        return parent::findFirst($where);
    }

    public static function find($where = [], $options = [])
    {
        $data = parent::find($where, $options);
        if (! $data) {
            return false;
        }

        $result = [];
        foreach ($data as $d) {
            $result[] = (array) $d;
        }

        return $result;
    }

    public static function set($id, $baseUrl, $creationTime, $width, $height)
    {
        $where = ['id' => $id];
        $data = [
            'id' => $id,
            'baseUrl' => $baseUrl,
            'creationTime' => strtotime($creationTime),
            'width' => (int) $width,
            'height' => (int) $height,
            'renewNumber' => self::getRenewNumber(),
        ];
        return parent::upsert($where, $data);
    }

    public static function incRenewNumber()
    {
        $file = self::RENEW_NUMBER;
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        } else {
            $data = [
                'number' => 0,
            ];
        }

        $data['number']++;

        $data['updated_at'] = date("Y-m-d H:i:s");
        file_put_contents($file, json_encode($data));
    }

    public static function getRenewNumber()
    {
        $data = [
            'number' => 0,
            'updated_at' => null,
        ];
        $file = self::RENEW_NUMBER;
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }

        return $data['number'];
    }

    // 更新番号をチェックして一致しないデータは消えているので削除する
    public static function removeNotExistsImage()
    {
        $where = [
            'renewNumber' => ['$ne' => self::getRenewNumber()],
        ];
        $data = self::find($where);
        if (! $data) {
            return;
        }

        foreach ($data as $d) {
            self::delete(['id' => $d['id']]);
        }
    }

    public static function error($message)
    {
        $file = __DIR__ . '/../log/error_' . date("Y-m-d") . '.log';
        $log = date("Y-m-d H:i:s") . ' ' . print_r($message, true) . "\n";
        file_put_contents($file, $log, FILE_APPEND | LOCK_EX);
    }
}

