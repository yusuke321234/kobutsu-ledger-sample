import type { HTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

// 受け取る props は公式の InputError と同じ（message 以外は <p> にそのまま渡す）
type InputErrorProps = HTMLAttributes<HTMLParagraphElement> & {
  message?: string
}

// 入力欄の下に出すバリデーションエラー（サーバーから届いた文言をそのまま出す）。
export default function InputError({ message, className, ...props }: InputErrorProps) {
  //
  if (message !== undefined) {
    // ok.
  } else {
    return null
  }

  //
  return (
    <p {...props} className={cn('mt-1 text-sm text-[#f53003]', className)}>
      {message}
    </p>
  )
}
