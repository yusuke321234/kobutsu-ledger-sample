<?php

// E2E 専用ルーター（php -S）。
// ビルトインサーバーでは環境変数が getenv() にしか見えないため、Laravel が $_SERVER / $_ENV から読む
// LARAVEL_STORAGE_PATH を移し替えてから、Laravel 標準のルーターに委ねる。
$storagePath = getenv('LARAVEL_STORAGE_PATH');
if ($storagePath !== false) {
    $_ENV['LARAVEL_STORAGE_PATH'] = $storagePath;
    $_SERVER['LARAVEL_STORAGE_PATH'] = $storagePath;
} else {
    // no action.
}

// 静的ファイルは server.php が false を返す（＝ビルトインサーバーにそのまま配信させる）ので、戻り値を必ず返す
return require __DIR__ . '/../../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
