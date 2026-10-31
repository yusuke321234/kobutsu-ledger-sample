import { defineConfig, devices } from '@playwright/test'
import { E2E_BASE_URL, E2E_ENV } from './tests/e2e/env'

// E2E（実ブラウザでの画面テスト）設定。
// webServer が専用の DB・storage（.e2e/）でアプリを起動する。事前に `npm run build` が必要。
// 実行は `npm run e2e`。DB を共有するため workers=1（テストごとに新規ユーザーを作って分離する）。
export default defineConfig({
  testDir: './tests/e2e',
  timeout: 30000,
  fullyParallel: false,
  workers: 1,
  retries: getRetryCount(),
  reporter: 'list',
  use: {
    baseURL: E2E_BASE_URL,
    headless: true,
    locale: 'ja-JP',
    timezoneId: 'Asia/Tokyo',
    trace: 'retain-on-failure',
    // コンテナ実行向け（root/サンドボックス無効環境でも Chromium を起動できるように）
    launchOptions: { args: ['--no-sandbox'] },
  },
  projects: [
    {
      name: 'desktop',
      testIgnore: /mobile\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'mobile',
      testMatch: /mobile\.spec\.ts/,
      use: { ...devices['Pixel 7'] },
    },
  ],
  webServer: {
    command: 'bash tests/e2e/serve.sh',
    url: `${E2E_BASE_URL}/up`,
    env: E2E_ENV,
    reuseExistingServer: false,
    timeout: 120000,
    stdout: 'ignore',
    stderr: 'pipe',
  },
})

// CI（GitHub Actions）だけ、失敗したテストを1回やり直す。仮想マシンが手元より遅く、
// 時間切れで一時的に落ちることがあるため。手元では0回（不安定なテストに気づけるように）
function getRetryCount(): number {
  if (process.env.CI === 'true') {
    return 1
  } else {
    return 0
  }
}
