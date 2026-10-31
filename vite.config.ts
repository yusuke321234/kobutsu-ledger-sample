import { execSync } from 'node:child_process'
import { fileURLToPath, URL } from 'node:url'
import inertia from '@inertiajs/vite'
import { wayfinder } from '@laravel/vite-plugin-wayfinder'
import babel from '@rolldown/plugin-babel'
import tailwindcss from '@tailwindcss/vite'
import react, { reactCompilerPreset } from '@vitejs/plugin-react'
import laravel from 'laravel-vite-plugin'
import { bunny } from 'laravel-vite-plugin/fonts'
import { defineConfig, lazyPlugins } from 'vite-plus'

// Wayfinder（Laravel のルート・コントローラから TS の関数を生成する）が呼ぶ PHP。
// php8.3 という名前のコマンドがあればそちらを使う（既定の php が古い環境向け。WAYFINDER_PHP で上書きできる）
function resolvePhpBinary(): string {
  if (process.env.WAYFINDER_PHP !== undefined) {
    return process.env.WAYFINDER_PHP
  } else {
    try {
      execSync('command -v php8.3', { stdio: 'ignore' })
      return 'php8.3'
    } catch {
      return 'php'
    }
  }
}

// ビルド（Vite+）と Lint/整形（Oxlint/Oxfmt）の設定。Laravel 公式 React スターターキットの構成に合わせる。
export default defineConfig({
  plugins: lazyPlugins(() => [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.tsx'],
      refresh: true,
      fonts: [
        bunny('Instrument Sans', {
          weights: [400, 500, 600],
        }),
      ],
    }),
    // SSR は使わない（config/inertia.php）
    inertia({ ssr: false }),
    react(),
    // React Compiler（useMemo / useCallback を手で書かなくても再描画を最適化する）
    babel({
      presets: [reactCompilerPreset()],
    }),
    tailwindcss(),
    wayfinder({
      formVariants: true,
      command: `${resolvePhpBinary()} artisan wayfinder:generate`,
    }),
  ]),
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  server: {
    watch: {
      ignored: ['**/storage/framework/views/**', '**/.e2e/**', '**/vendor/**'],
    },
  },
  lint: {
    plugins: ['eslint', 'typescript', 'unicorn', 'oxc', 'react', 'import'],
    ignorePatterns: [
      'vendor/**',
      'node_modules/**',
      'public/**',
      'storage/**',
      'bootstrap/ssr/**',
      '.e2e/**',
      'resources/js/actions/**',
      'resources/js/routes/**',
      'resources/js/wayfinder/**',
      'resources/js/components/ui/*',
    ],
    rules: {
      'react/rules-of-hooks': 'error',
      'react/exhaustive-deps': 'error',
      'react/jsx-key': 'error',
    },
    options: {
      denyWarnings: true,
      typeAware: true,
    },
  },
  // 整形は TS/TSX のみ（Markdown・JSON・YAML・CSS は .editorconfig の 4 スペースのまま）
  fmt: {
    printWidth: 100,
    tabWidth: 2,
    singleQuote: true,
    semi: false,
    ignorePatterns: [
      '**/*.md',
      '**/*.json',
      '**/*.yaml',
      '**/*.yml',
      '**/*.css',
      '.github/**',
      'vendor/**',
      'public/**',
      'storage/**',
      '.e2e/**',
      'resources/js/actions/**',
      'resources/js/routes/**',
      'resources/js/wayfinder/**',
      'resources/js/components/ui/*',
      'resources/views/**',
    ],
    sortTailwindcss: {
      functions: ['clsx', 'cn', 'cva'],
      entryPoint: 'resources/css/app.css',
    },
  },
})
