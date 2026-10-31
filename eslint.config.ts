import tseslint from 'typescript-eslint'

// 命名規約のチェック専用（npm run lint:naming）。ほかの Lint は Oxlint（vite.config.ts の lint）が受け持つ。
// Oxlint には変数名の形を縛るルールがないため、typescript-eslint の naming-convention だけを使う。
// 規約の正は docs/conventions/09-frontend-style.md（命名）。ここに書くのは機械で線を引けるものだけ。
export default tseslint.config(
  {
    ignores: [
      // Wayfinder の生成物・shadcn/ui（手を入れない）
      'resources/js/actions/**',
      'resources/js/routes/**',
      'resources/js/wayfinder/**',
      'resources/js/components/ui/**',
    ],
  },
  {
    files: ['resources/js/**/*.{ts,tsx}'],
    languageOptions: {
      parser: tseslint.parser,
      // 配列かどうかを型で見分けるため、型情報を使う
      parserOptions: {
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
      },
    },
    plugins: {
      '@typescript-eslint': tseslint.plugin,
    },
    rules: {
      '@typescript-eslint/naming-convention': [
        'error',
        // 規約で決めていない名前は縛らない
        {
          selector: 'default',
          format: null,
        },
        // 配列の変数・引数は名前の最後を List にする（purchaseList。ファイル直下の定数は LEGAL_LINK_LIST）
        // 例外：shadcn/ui の cn(...inputs) は標準の名前のまま（09-frontend-style）
        {
          selector: ['variable', 'parameter'],
          types: ['array'],
          format: null,
          suffix: ['List', '_LIST'],
          filter: {
            regex: '^inputs$',
            match: false,
          },
        },
        // 自分で定義する関数に on* / handle* を付けない（on* は部品が受け取る props の名前だけ）
        {
          selector: 'function',
          format: null,
          custom: {
            regex: '^(on|handle)[A-Z]',
            match: false,
          },
        },
        {
          selector: 'variable',
          types: ['function'],
          format: null,
          custom: {
            regex: '^(on|handle)[A-Z]',
            match: false,
          },
        },
        // 型の名前は PascalCase の名詞。UserData のように Data で終わらせない（Data は変数の名前に付ける）
        {
          selector: 'typeLike',
          format: ['PascalCase'],
          custom: {
            regex: 'Data$',
            match: false,
          },
        },
        // ファイル直下の定数（設定値・固定の一覧）は大文字。関数・部品は対象外
        {
          selector: 'variable',
          modifiers: ['const', 'global'],
          format: ['UPPER_CASE'],
        },
        {
          selector: 'variable',
          modifiers: ['const', 'global'],
          types: ['function'],
          format: null,
        },
      ],
    },
  },
)
