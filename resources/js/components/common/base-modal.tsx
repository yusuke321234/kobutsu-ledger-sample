import * as DialogPrimitive from '@radix-ui/react-dialog'
import type { ReactNode } from 'react'
import { Dialog, DialogOverlay, DialogPortal, DialogTitle } from '@/components/ui/dialog'
import { cn } from '@/lib/utils'

type ModalAlign = 'start' | 'center' | 'end'

interface BaseModalProps {
  open: boolean
  title: string
  onClose: () => void
  // モバイルでの縦位置。'end'（既定＝下端のボトムシート風）/ 'center'（中央）/ 'start'（上端）。
  // キーボードを開く検索モーダル等は 'start' にして、下端・中央でキーボードに被るのを避ける。PC（sm 以上）は常に中央。
  align?: ModalAlign
  // 開いた時に最初にフォーカスする要素を選ぶ（既定は先頭の入力欄）
  onOpenAutoFocus?: (event: Event) => void
  children: ReactNode
}

// モーダル基盤（各モーダルがこれを包む）。shadcn/ui（Radix）の Dialog でオーバーレイ・フォーカストラップ・ESC を担保する。
export default function BaseModal({
  open,
  title,
  onClose,
  align = 'end',
  onOpenAutoFocus,
  children,
}: BaseModalProps) {
  return (
    <Dialog
      open={open === true}
      onOpenChange={(nextOpen) => {
        if (nextOpen === false) {
          onClose()
        } else {
          // no action.
        }
      }}
    >
      <DialogPortal>
        <DialogOverlay className="bg-black/40" />
        <div
          className={cn(
            'fixed inset-0 z-50 flex justify-center p-4 sm:items-center',
            getAlignClass(align),
          )}
        >
          <DialogPrimitive.Content
            aria-describedby={undefined}
            onOpenAutoFocus={onOpenAutoFocus}
            onInteractOutside={keepOpenOnToast}
            className="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl focus:outline-none"
          >
            <DialogTitle className="text-lg leading-7 font-semibold text-[#1b1b18]">
              {title}
            </DialogTitle>
            {children}
          </DialogPrimitive.Content>
        </div>
      </DialogPortal>
    </Dialog>
  )
}

// トーストをタップしてもモーダルは閉じない（トーストはモーダルの外に置かれるため、そのままだと外側のタップ扱いになる）。
function keepOpenOnToast(event: Event): void {
  const target = event.target
  if (target instanceof Element) {
    // ok.
  } else {
    return
  }

  //
  const toaster = target.closest('[data-sonner-toaster]')
  if (toaster !== null) {
    event.preventDefault()
  } else {
    // no action.
  }
}

// モバイルの縦位置クラス（PC は sm:items-center が上書きする）。
function getAlignClass(align: ModalAlign): string {
  if (align === 'center') {
    return 'items-center'
  } else if (align === 'start') {
    return 'items-start'
  } else {
    return 'items-end'
  }
}
