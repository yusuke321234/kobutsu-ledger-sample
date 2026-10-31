import { execSync } from 'node:child_process'
import { resolve } from 'node:path'

// E2E 専用の実行環境。開発用の DB・storage を汚さないよう、.e2e/ 配下に隔離する。
// Playwright の webServer（serve.sh）と、テストから叩く artisan の両方に同じ値を渡す。
export const E2E_PORT = 8123
export const E2E_BASE_URL = `http://127.0.0.1:${E2E_PORT}`
export const APP_ROOT = resolve(import.meta.dirname, '../..')
export const E2E_ROOT = resolve(APP_ROOT, '.e2e')

// 使う PHP。php8.3 という名前のコマンドがあればそちらを使う（既定の php が古い環境向け。E2E_PHP で上書きできる）
export const PHP_BIN = process.env.E2E_PHP ?? resolvePhpBinary()

function resolvePhpBinary(): string {
  try {
    execSync('command -v php8.3', { stdio: 'ignore' })
    return 'php8.3'
  } catch {
    return 'php'
  }
}

export const E2E_ENV: Record<string, string> = {
  APP_ENV: 'local',
  APP_DEBUG: 'true',
  APP_URL: E2E_BASE_URL,
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: resolve(E2E_ROOT, 'database.sqlite'),
  // セッションはリクエストをまたいで残す必要があるので file。
  // キャッシュは array（php -S はリクエストごとに別プロセス＝レート制限が効かず、連続登録で 429 にならない）
  SESSION_DRIVER: 'file',
  CACHE_STORE: 'array',
  QUEUE_CONNECTION: 'sync',
  MAIL_MAILER: 'log',
  LOG_CHANNEL: 'single',
  LARAVEL_STORAGE_PATH: resolve(E2E_ROOT, 'storage'),
  // php -S を複数ワーカーで動かす（画面が並列にリクエストしても詰まらない）
  PHP_CLI_SERVER_WORKERS: '4',
  E2E_PHP: PHP_BIN,
  E2E_PORT: String(E2E_PORT),
  E2E_ROOT,
}
