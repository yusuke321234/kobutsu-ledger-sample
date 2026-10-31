import type { ReactNode } from 'react'
import AppHeader from '@/components/common/app-header'
import PurchaseFormModal from '@/components/purchase/purchase-form-modal'
import SaleFormModal from '@/components/sale/sale-form-modal'
import { useFlashToast } from '@/hooks/use-flash-toast'
import { UiProvider } from '@/hooks/use-ui'
import { cn } from '@/lib/utils'

interface AppLayoutProps {
  // 本文幅。カード・表の一覧系は 1200px、読み物/フォーム系は間延びを避けて 950px（ページの layout で指定）
  width?: 'wide' | 'normal'
  children: ReactNode
}

// 認証後の標準レイアウト。追従ヘッダー + 本文 + 全画面共通のモーダル。
// Inertia の永続レイアウトなので、ページを移ってもモーダルの状態は保たれる。
export default function AppLayout({ width = 'normal', children }: AppLayoutProps) {
  //
  useFlashToast()

  //
  return (
    <UiProvider>
      <div className="min-h-screen bg-[#FDFDFC]">
        <AppHeader />

        {/* PC の本文幅：カード一覧は 1200px、読み物/フォームは 950px。モバイルは w-full のまま。 */}
        <main
          className={cn(
            'mx-auto w-full pb-10',
            width === 'wide' ? 'max-w-[1200px]' : 'max-w-[950px]',
          )}
        >
          {children}
        </main>
      </div>

      {/* 仕入登録モーダル。ヘッダー「＋仕入」・在庫の登録/編集から開く */}
      <PurchaseFormModal />

      {/* 売却モーダル。在庫の「売れた」から開く */}
      <SaleFormModal />
    </UiProvider>
  )
}
