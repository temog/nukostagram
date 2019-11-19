<?php
require_once(__DIR__ . '/../lib/Init.php');
require_once(__DIR__ . '/../lib/GooglePhotosLibraryApi.php');

$albumId = GooglePhotosLibraryApi::getAlbumIdByName("nukostagram");
var_dump($albumId);
