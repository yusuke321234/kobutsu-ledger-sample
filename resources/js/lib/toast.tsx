import { toast } from 'sonner'
import { cn } from '@/lib/utils'

// トーストの種類（サーバーの ToastConsts と一致）
export type ToastType = 'success' | 'error'

// トーストが自動で閉じるまでの時間（ms）。タップでの即時クローズも別途有効。
const TOAST_DURATION_MS = 4000

// トーストを1件出す。完了は墨色の帯、失敗は赤の帯。タップすると閉じる。
// モーダルを開いている間は body がタップを受けないため、帯自身でタップを受ける（pointer-events-auto）。
export function showToast(message: string, type: ToastType = 'success'): void {
  toast.custom(
    (toastId) => (
      <button
        type="button"
        className={cn(
          'pointer-events-auto w-full max-w-sm rounded-lg px-4 py-3 text-left text-sm text-white shadow-lg',
          getToastClass(type),
        )}
        onClick={() => toast.dismiss(toastId)}
      >
        {message}
      </button>
    ),
    { duration: TOAST_DURATION_MS, position: getToastPosition() },
  )
}

// 出す位置。ふだんは移行前の Vue 版と同じ画面下の中央。
// モーダルを開いている間は上に出す（下だとモーダルの保存ボタンに重なり、押した直後のマウスの下に出て
// 消えなくなる＝sonner はマウスが乗っている間は閉じない）。
function getToastPosition(): 'top-center' | 'bottom-center' {
  const openDialog = document.querySelector('[role="dialog"][data-state="open"]')
  if (openDialog !== null) {
    return 'top-center'
  } else {
    return 'bottom-center'
  }
}

// 種類ごとの帯の色（完了＝墨色、失敗＝赤）
function getToastClass(type: ToastType): string {
  if (type === 'error') {
    return 'bg-[#f53003]'
  } else {
    return 'bg-gray-900'
  }
}
