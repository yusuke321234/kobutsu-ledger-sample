import { ArrowLeftIcon } from '@heroicons/react/24/outline'

// 戻るボタン（詳細系画面の左上）。履歴を1つ戻る。
export default function BackButton() {
  return (
    <button
      type="button"
      className="mb-4 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800"
      onClick={() => window.history.back()}
    >
      <ArrowLeftIcon className="h-4 w-4" aria-hidden="true" />
      戻る
    </button>
  )
}
