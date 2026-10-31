#!/usr/bin/env bash
# E2E 用にアプリを起動する（Playwright の webServer から呼ばれる。環境変数は tests/e2e/env.ts が渡す）。
# 毎回 DB を作り直し、ビルド済みアセット（public/build）で配信する。
set -euo pipefail

cd "$(dirname "$0")/../.."

if [ -f public/hot ]; then
  echo "public/hot があります。Vite の dev サーバーを止めてから実行してください。" >&2
  exit 1
fi
if [ ! -f public/build/manifest.json ]; then
  echo "public/build がありません。先に npm run build を実行してください。" >&2
  exit 1
fi

# DB と storage を初期化する（前回の実行結果を持ち越さない）
: "${E2E_ROOT:?E2E_ROOT が未設定です}"
rm -rf "$E2E_ROOT"
mkdir -p "$E2E_ROOT" \
  "$LARAVEL_STORAGE_PATH/app/private" \
  "$LARAVEL_STORAGE_PATH/framework/cache/data" \
  "$LARAVEL_STORAGE_PATH/framework/sessions" \
  "$LARAVEL_STORAGE_PATH/framework/views" \
  "$LARAVEL_STORAGE_PATH/logs"
touch "$DB_DATABASE"
"$E2E_PHP" artisan migrate:fresh --force --no-interaction > /dev/null

# Laravel の server.php は getcwd() を公開ディレクトリとして扱うため public で起動する
cd public
exec "$E2E_PHP" -S "127.0.0.1:$E2E_PORT" ../tests/e2e/server.php
