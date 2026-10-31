import type { InertiaLinkProps } from '@inertiajs/react'
import { usePage } from '@inertiajs/react'
import { toUrl } from '@/lib/utils'

// リンク先（文字列の URL、または Wayfinder のルート）
type Href = NonNullable<InertiaLinkProps['href']>

export type UseCurrentUrlReturn = {
  // 今いる画面のパス（クエリを除く。例：/ledger）
  currentUrl: string
  // リンク先が今の画面か（startsWith=true なら配下の画面も含める）
  isCurrentUrl: (urlToCheck: Href, currentUrl?: string, startsWith?: boolean) => boolean
  // リンク先が今の画面か、その配下の画面か
  isCurrentOrParentUrl: (urlToCheck: Href, currentUrl?: string) => boolean
  // リンク先が今の画面なら ifTrue、違えば ifFalse を返す
  whenCurrentUrl: <TIfTrue, TIfFalse = null>(
    urlToCheck: Href,
    ifTrue: TIfTrue,
    ifFalse?: TIfFalse,
  ) => TIfTrue | TIfFalse
}

// 今いる画面の URL と、リンク先が今の画面かどうかの判定（公式スターターキットの use-current-url と同じ使い方）。
export function useCurrentUrl(): UseCurrentUrlReturn {
  const page = usePage()
  const currentUrlPath = getPathname(page.url)

  function isCurrentUrl(
    urlToCheck: Href,
    currentUrl?: string,
    startsWith: boolean = false,
  ): boolean {
    const urlToCompare = currentUrl ?? currentUrlPath
    const pathToCheck = getPathname(toUrl(urlToCheck))
    if (startsWith === true) {
      return urlToCompare.startsWith(pathToCheck)
    } else {
      return pathToCheck === urlToCompare
    }
  }

  function isCurrentOrParentUrl(urlToCheck: Href, currentUrl?: string): boolean {
    return isCurrentUrl(urlToCheck, currentUrl, true)
  }

  function whenCurrentUrl<TIfTrue, TIfFalse = null>(
    urlToCheck: Href,
    ifTrue: TIfTrue,
    ifFalse: TIfFalse = null as TIfFalse,
  ): TIfTrue | TIfFalse {
    const isCurrent = isCurrentUrl(urlToCheck)
    if (isCurrent === true) {
      return ifTrue
    } else {
      return ifFalse
    }
  }

  return {
    currentUrl: currentUrlPath,
    isCurrentUrl,
    isCurrentOrParentUrl,
    whenCurrentUrl,
  }
}

// URL（/ledger?from=… や https://…/ledger）からパスだけを取り出す
function getPathname(url: string): string {
  return new URL(url, 'http://localhost').pathname
}
