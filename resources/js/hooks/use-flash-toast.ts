import { router, usePage } from '@inertiajs/react'
import { useEffect, useState } from 'react'
import { showToast } from '@/lib/toast'

// サーバーから届いたトースト（Inertia::flash の toast）を出す。
// 受け取りは Inertia の flash イベント（公式キットと同じ）。初回表示のフラッシュはイベントが起きないので、
// ページの props からも1回だけ拾う。
// 表示を state 経由にしているのは、同じ操作の完了処理（onSuccess でモーダルを閉じる等）が
// 画面に反映されてから出したいため（トーストの位置がモーダルの開閉で変わる）。
export function useFlashToast(): void {
  const page = usePage()
  const [pendingToastData, setPendingToastData] = useState(page.flash.toastData ?? null)

  useEffect(() => {
    return router.on('flash', (event) => {
      const toastData = event.detail.flash.toastData
      if (toastData !== undefined) {
        setPendingToastData(toastData)
      } else {
        // no action.
      }
    })
  }, [])

  useEffect(() => {
    if (pendingToastData !== null) {
      showToast(pendingToastData.message, pendingToastData.type)
    } else {
      // no action.
    }
  }, [pendingToastData])
}
