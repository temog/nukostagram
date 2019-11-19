# ぬこすたぐらむ

pwa の勉強と Google Photos API の検証で作った超絶やっつけアプリ

## system

- php 7.3
- mongodb


## install

### composer

```
composer install
```

### .env 作成


```
WEBPUSH_PUBLIC_KEY=
WEBPUSH_PRIVATE_KEY=
GOOGLE_PHOTO_CLIENT_ID=
GOOGLE_PHOTO_CLIENT_SECRET=
VAPID_MAILTO=YourEmail
```
- webpush key は https://web-push-codelab.glitch.me/ から取得する
- Google Photos は [このあたり参照](https://developers.google.com/photos/library/guides/get-started)


## cron

```
*/20 * * * * /usr/bin/php /var/www/nukostagram/task/importImage.php > /dev/null 2>&1
```

