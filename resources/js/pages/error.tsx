import { Head, Link } from '@inertiajs/react'
import { home } from '@/routes'

interface ErrorPageProps {
  status: number
}

// ステータス別の案内（404 は移行前の Vue 版と同じ文言）
const MESSAGE_BY_STATUS: Record<number, string> = {
  403: 'この画面を表示する権限がありません。',
  404: 'ページが見つかりませんでした。',
  500: 'サーバーでエラーが起きました。時間をおいて再度お試しください。',
  503: 'ただいまメンテナンス中です。しばらくしてから再度お試しください。',
}

// エラー画面（存在しない URL・権限なし・サーバーエラー）。ロジックの無い静的な画面。
export default function ErrorPage({ status }: ErrorPageProps) {
  const message = MESSAGE_BY_STATUS[status] ?? 'エラーが起きました。'

  return (
    <div className="px-6 py-20 text-center">
      <Head title={String(status)}>
        <meta head-key="description" name="description" content={message} />
      </Head>
      <p className="text-3xl font-semibold text-[#1b1b18]">{status}</p>
      <p className="mt-2 text-sm text-gray-600">{message}</p>
      <Link
        href={home.url()}
        className="mt-6 inline-block text-sm text-gray-600 underline underline-offset-4"
      >
        ホームへ戻る
      </Link>
    </div>
  )
}
