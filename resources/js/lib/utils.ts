import type { InertiaLinkProps } from '@inertiajs/react'
import { clsx, type ClassValue } from 'clsx'
import { twMerge } from 'tailwind-merge'

// Tailwind のクラスを条件付きで組み立て、重複・衝突を後勝ちで解決する（shadcn/ui と共通）
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs))
}

// リンク先（文字列の URL、または Wayfinder のルート `{ url, method }`）を文字列の URL にする（公式と同じ）
export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
  if (typeof url === 'string') {
    return url
  } else {
    return url.url
  }
}
