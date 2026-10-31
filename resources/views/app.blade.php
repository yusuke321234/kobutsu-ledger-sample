<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- ファビコン（紺の角板＋「古」＝古物商プレート由来。SVGは日本語フォント依存で豆腐化するリスクがあるため使わず PNG/ico で配信） --}}
        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">
        <link rel="icon" href="/favicon-16x16.png" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        {{-- React（Inertia）の画面。ページごとの JS は Inertia が必要な時に読み込む --}}
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        {{-- 既定のタイトルと説明。画面が開くと各ページの <Head> の内容に置き換わる（説明は data-inertia="description" で対応づける） --}}
        <x-inertia::head>
            <title>コブチョウ</title>
            <meta name="description" content="古物商向けの台帳アプリ（公開用のサンプル）。仕入・売却を記録すると、古物台帳（様式15号）が同時にできあがります。" data-inertia="description">
        </x-inertia::head>
    </head>
    <body class="antialiased">
        <x-inertia::app />
    </body>
</html>
