<?php
require_once(__DIR__ . '/../lib/Init.php');
require_once(__DIR__ . '/../lib/Image.php');
require_once(__DIR__ . '/../lib/GooglePhotosLibraryApi.php');
require_once(__DIR__ . '/../lib/WebPushNukostagram.php');

$albumId = GooglePhotosLibraryApi::getAlbumIdByName("nukostagram");

// 更新番号をインクリメント
Image::incRenewNumber();

// 画像取得
$count = Image::importAlbumImage($albumId);

// 存在しなかった画像データを消し込む
Image::removeNotExistsImage();

// 新しい画像を通知
if ($count) {
    WebPushNukostagram::push(
        'newImage',
        $count . '件の新しい画像が追加されました',
        'タップで新しい画像を表示します'
    );
}
