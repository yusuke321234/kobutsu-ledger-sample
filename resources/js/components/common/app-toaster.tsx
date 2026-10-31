import { router } from '@inertiajs/react'
import { useEffect, type CSSProperties } from 'react'
import { Toaster } from 'sonner'
import { showToast } from '@/lib/toast'

const NETWORK_ERROR_MESSAGE = '通信に失敗しました。時間をおいて再度お試しください。'

// トーストの置き場。ログイン前の画面を含む全画面で使う（アプリの最上位に1つだけ置く）。
// 通信そのものの失敗もここでトーストにする。サーバーからのフラッシュ（toast）は各レイアウトが受ける。
export default function AppToaster() {
  useEffect(() => {
    // サーバーに届かなかった（オフライン等）
    const removeNetworkErrorListener = router.on('networkError', () => {
      showToast(NETWORK_ERROR_MESSAGE)
    })

    // 画面ではない応答（想定外のエラー）。開発中は Inertia の詳細モーダルを出し、本番はトーストに留める
    const removeHttpExceptionListener = router.on('httpException', (event) => {
      if (import.meta.env.DEV === true) {
        // no action.
      } else {
        event.preventDefault()
        showToast(NETWORK_ERROR_MESSAGE)
      }
    })

    return () => {
      removeNetworkErrorListener()
      removeHttpExceptionListener()
    }
  }, [])

  // 移行前の Vue 版と同じく、画面下の中央（下から 80px）に 8px 間隔で縦に積む。
  // 幅も Vue 版と同じ：PC は 384px、スマホ（600px 未満）は左右 16px を空けた全幅
  return (
    <Toaster
      position="bottom-center"
      offset={{ top: 16, bottom: 80 }}
      mobileOffset={{ top: 16, right: 16, bottom: 80, left: 16 }}
      gap={8}
      expand
      visibleToasts={5}
      style={{ '--width': '384px' } as CSSProperties}
      toastOptions={{ unstyled: true, className: 'block w-full' }}
    />
  )
}
