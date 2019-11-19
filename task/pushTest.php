<?php
require_once(__DIR__ . '/../lib/Init.php');
require(__DIR__ . '/../lib/WebPushNukostagram.php');

WebPushNukostagram::push(
    'newImage',
    'テストだよ',
    'タップでぬこすたが開きます (ΦωΦ)'
);
