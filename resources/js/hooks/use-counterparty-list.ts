import { router, usePage } from '@inertiajs/react'
import { useEffect } from 'react'
import type { Counterparty } from '@/types'

// 相手方チップの履歴を返す。
// 在庫画面はページの props で常に届く。それ以外の画面では届いていないので、部分リロードで取りに行く
// （保存のたびに画面が読み直されて消えた場合も、ここで取り直す）。
export function useCounterpartyList(): Counterparty[] {
  const { counterpartyList } = usePage().props

  useEffect(() => {
    if (counterpartyList === undefined) {
      // async：他の画面遷移・保存を打ち消さない
      router.reload({ only: ['counterpartyList'], async: true })
    } else {
      // no action.
    }
  }, [counterpartyList])

  return counterpartyList ?? []
}
