import { Bars3Icon } from '@heroicons/react/24/outline'
import { PlusIcon } from '@heroicons/react/24/solid'
import { Link } from '@inertiajs/react'
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthController'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useCurrentUrl } from '@/hooks/use-current-url'
import { useUi } from '@/hooks/use-ui'
import { cn } from '@/lib/utils'
import { home, ledger, settings } from '@/routes'

interface NavItem {
  label: string
  href: string
  exact: boolean
}

const navItemList: NavItem[] = [
  { label: '在庫', href: home.url(), exact: true },
  { label: '台帳', href: ledger.url(), exact: false },
  { label: '設定', href: settings.url(), exact: false },
]

// ヘッダー。追従型（sticky）。左にロゴ→ホーム、右に「＋仕入」ボタンとハンバーガーメニューへ集約する。
export default function AppHeader() {
  const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl()
  const { openPurchaseForm } = useUi()

  // 今いる画面か（在庫＝ホームは完全一致、それ以外は配下の画面も含める）
  function isActive(itemData: NavItem): boolean {
    if (itemData.exact === true) {
      return isCurrentUrl(itemData.href)
    } else {
      return isCurrentOrParentUrl(itemData.href)
    }
  }

  return (
    <header className="sticky top-0 z-40 border-b border-gray-200 bg-white/90 backdrop-blur">
      {/* ヘッダーは画面端ぞろえ（フルブラッド）。ロゴを左端・操作を右端に寄せ、端から px-4/sm:px-6 の余白を取る。 */}
      <div className="flex h-14 w-full items-center justify-between px-4 sm:px-6">
        {/* 左: ロゴ → ホーム */}
        <Link href={home.url()} className="shrink-0 text-lg font-semibold text-[#1b1b18]">
          コブチョウ
        </Link>

        {/* 右: ＋仕入 ボタン + ハンバーガーメニュー */}
        <div className="flex items-center gap-2 sm:gap-3">
          <button
            type="button"
            className="flex items-center gap-1 rounded-md bg-[#1b1b18] px-2.5 py-1.5 text-sm font-semibold text-white hover:bg-black"
            onClick={() => openPurchaseForm()}
          >
            <PlusIcon className="h-4 w-4" aria-hidden="true" />
            <span>仕入</span>
          </button>

          <DropdownMenu modal={false}>
            <DropdownMenuTrigger
              className="flex h-9 w-9 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-900 focus:outline-none"
              aria-label="メニュー"
            >
              <Bars3Icon className="h-6 w-6" aria-hidden="true" />
            </DropdownMenuTrigger>
            <DropdownMenuContent
              align="end"
              sideOffset={8}
              className="z-50 w-44 min-w-0 rounded-md border border-gray-200 bg-white p-0 py-1 shadow-lg"
            >
              {navItemList.map((itemData) => (
                <DropdownMenuItem key={itemData.href} asChild className="rounded-none p-0">
                  <Link
                    href={itemData.href}
                    className={cn(
                      'block w-full cursor-pointer px-4 py-2 text-sm focus:bg-gray-100',
                      isActive(itemData) === true
                        ? 'font-semibold text-[#1b1b18]'
                        : 'text-gray-700',
                    )}
                  >
                    {itemData.label}
                  </Link>
                </DropdownMenuItem>
              ))}

              {/* ログアウト（POST で送る。公式スターターキットと同じく Link を button として使う） */}
              <DropdownMenuSeparator className="mx-0 my-1 bg-gray-200" />
              <DropdownMenuItem asChild className="rounded-none p-0">
                <Link
                  href={AuthController.logout()}
                  as="button"
                  className="block w-full cursor-pointer px-4 py-2 text-left text-sm text-gray-700 focus:bg-gray-100"
                >
                  ログアウト
                </Link>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>
  )
}
