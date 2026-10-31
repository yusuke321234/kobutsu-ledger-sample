import { Link, router, usePage } from '@inertiajs/react'
import { useEffect, useEffectEvent, useState } from 'react'
import LedgerController from '@/actions/App/Http/Controllers/Ledger/LedgerController'
import MoreButton from '@/components/common/more-button'
import {
  PERIOD_PRESET_LIST,
  RECORD_TYPE_LABEL,
  resolvePeriod,
  type PeriodPresetValue,
} from '@/consts/ledger'
import { KUBUN_LABEL, SALES_CHANNEL_LABEL, VERIFICATION_METHOD_LABEL } from '@/consts/purchase'
import { formatYen } from '@/lib/format'
import { cn } from '@/lib/utils'
import { settings } from '@/routes'
import type { LedgerFilters, LedgerRow, User } from '@/types'

// 検索を打鍵ごとに送らないための待ち時間（ms）
const SEARCH_DEBOUNCE_MS = 300

export interface LedgerBoardProps {
  filterData: LedgerFilters
  pageSize: number
  ledgerList: LedgerRow[]
  totalCount: number
}

// 古物台帳ボード。受入＋払出の時系列1表を、期間絞り込み・検索・出力付きで表示する。
// 台帳は保存せずサーバー側で記録から都度組み立てられる。
export default function LedgerBoard({
  filterData,
  pageSize,
  ledgerList,
  totalCount,
}: LedgerBoardProps) {
  const { auth } = usePage().props
  const currentFrom = filterData.from ?? ''
  const currentTo = filterData.to ?? ''
  const [periodFrom, setPeriodFrom] = useState(currentFrom)
  const [periodTo, setPeriodTo] = useState(currentTo)
  const [searchWord, setSearchWord] = useState(filterData.q)
  // 日付を直接いじったら、たまたまプリセットと同じ期間でも「任意期間」として扱う
  const [customPeriod, setCustomPeriod] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)
  const [filtering, setFiltering] = useState(false)

  // 様式15号PDFの見出しに印字する営業所情報のうち、未設定のもの
  const missingOfficeItemList = getMissingOfficeItemList(auth.user_data)
  let periodPreset = detectPreset(currentFrom, currentTo)
  if (customPeriod === true && periodPreset !== 'all') {
    periodPreset = 'custom'
  } else {
    // no action.
  }

  function visit(nextFrom: string, nextTo: string, q: string): void {
    router.get(LedgerController.getList.url(), getFilterQuery(nextFrom, nextTo, q), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['filterData', 'ledgerList', 'totalCount'],
      // 絞り込みを変えたら「もっと見る」で継ぎ足した分を捨てる
      reset: ['ledgerList'],
      onStart: () => setFiltering(true),
      onFinish: () => setFiltering(false),
    })
  }

  function selectPreset(value: PeriodPresetValue): void {
    const periodData = resolvePeriod(value)
    setPeriodFrom(periodData.from ?? '')
    setPeriodTo(periodData.to ?? '')
    setCustomPeriod(false)
    visit(periodData.from ?? '', periodData.to ?? '', searchWord.trim())
  }

  function changePeriodFrom(value: string): void {
    setPeriodFrom(value)
    setCustomPeriod(true)
    visit(value, periodTo, searchWord.trim())
  }

  function changePeriodTo(value: string): void {
    setPeriodTo(value)
    setCustomPeriod(true)
    visit(periodFrom, value, searchWord.trim())
  }

  // 検索は打鍵ごとに送らず、入力が止まってから送る
  const search = useEffectEvent((q: string) => visit(periodFrom, periodTo, q))
  useEffect(() => {
    const q = searchWord.trim()
    if (q !== filterData.q) {
      const timer = setTimeout(() => search(q), SEARCH_DEBOUNCE_MS)
      return () => clearTimeout(timer)
    } else {
      return undefined
    }
  }, [searchWord, filterData.q])

  // 「もっと見る」：次のページを取って表の後ろに継ぎ足す（URL は変えない）
  function loadMore(): void {
    const nextPage = Math.floor(ledgerList.length / pageSize) + 1
    router.reload({
      data: { current_page: nextPage },
      only: ['ledgerList', 'totalCount'],
      preserveUrl: true,
      onStart: () => setLoadingMore(true),
      onFinish: () => setLoadingMore(false),
    })
  }

  // 出力（様式15号PDF / CSV）は画面で選んだ期間・検索をそのまま引き継ぐ（ログイン中のセッションで取得できる）
  const exportQuery = { query: getFilterQuery(periodFrom, periodTo, searchWord.trim()) }

  return (
    <div className="px-4 pb-16">
      {/* 台帳が自動生成である旨 */}
      <p className="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">
        この台帳は、あなたが登録した
        <strong className="font-medium text-[#1b1b18]">仕入・売却の記録から自動生成</strong>
        されています。 台帳のために別途入力する必要はありません。
      </p>

      {/* 期間プリセット + 任意期間 + 検索 */}
      <div className="mt-4 flex flex-col gap-3">
        <div className="flex flex-wrap items-center gap-2">
          {PERIOD_PRESET_LIST.map((presetData) => (
            <button
              key={presetData.value}
              type="button"
              className={
                periodPreset === presetData.value
                  ? 'rounded-full bg-[#1b1b18] px-3 py-1 text-sm text-white'
                  : 'rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600 hover:bg-gray-200'
              }
              onClick={() => selectPreset(presetData.value)}
            >
              {presetData.label}
            </button>
          ))}
          {periodPreset === 'custom' && <span className="text-xs text-gray-500">任意期間</span>}
        </div>

        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center gap-2">
            <input
              type="date"
              aria-label="期間開始"
              value={periodFrom}
              onChange={(event) => changePeriodFrom(event.target.value)}
              className="rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-300 focus:outline-none"
            />
            <span className="text-sm text-gray-400">〜</span>
            <input
              type="date"
              aria-label="期間終了"
              value={periodTo}
              onChange={(event) => changePeriodTo(event.target.value)}
              className="rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-300 focus:outline-none"
            />
          </div>
          <input
            type="search"
            placeholder="品目・特徴・相手方で検索"
            aria-label="検索"
            value={searchWord}
            onChange={(event) => setSearchWord(event.target.value)}
            className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none sm:max-w-xs"
          />
        </div>
      </div>

      {/* 出力（様式15号PDF / CSV） */}
      <div className="mt-4 rounded-md border border-gray-200 bg-white p-3">
        <div className="flex flex-wrap items-center gap-2">
          <a
            href={LedgerController.exportPdf.url(exportQuery)}
            target="_blank"
            rel="noopener"
            className="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-semibold text-white hover:bg-black"
          >
            様式15号PDFを開く
          </a>
          <a
            href={LedgerController.exportCsv.url(exportQuery)}
            className="rounded-md border border-gray-300 px-4 py-2 text-sm text-[#1b1b18] hover:border-black"
          >
            CSVをダウンロード
          </a>
        </div>
        <p className="mt-2 text-xs text-gray-500">
          警察の立入時は、この期間指定のままPDFを開いて
          <strong className="font-medium text-[#1b1b18]">そのまま印刷</strong>
          できます。
        </p>

        {/* 営業所名・許可番号が未設定のときだけ案内する（許可が下りる前でも記録・出力はできるので、登録時の必須にはしない） */}
        {missingOfficeItemList.length > 0 && (
          <p className="mt-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            {missingOfficeItemList.join('・')}
            が未設定です。設定すると、様式15号PDFの見出しに印字されます。
            <Link href={settings.url()} className="ml-1 font-medium underline">
              設定する
            </Link>
          </p>
        )}
      </div>

      <p className="mt-3 text-sm text-gray-500">{totalCount} 件</p>

      {/* PC：様式15号の記載項目を1表に（横に長いので表だけ横スクロールさせる） */}
      <div className="mt-4 hidden overflow-x-auto md:block">
        <table className="w-full min-w-[900px] border-collapse text-sm">
          <thead>
            <tr className="border-b border-gray-200 text-left text-xs text-gray-500">
              <th className="py-2 pr-3 font-medium whitespace-nowrap">取引年月日</th>
              <th className="py-2 pr-3 font-medium whitespace-nowrap">区分</th>
              <th className="py-2 pr-3 font-medium">品目</th>
              <th className="py-2 pr-3 font-medium">数量</th>
              <th className="py-2 pr-3 font-medium">特徴</th>
              <th className="py-2 pr-3 font-medium">相手方</th>
              <th className="py-2 pr-3 font-medium">確認方法</th>
              <th className="py-2 text-right font-medium whitespace-nowrap">金額</th>
            </tr>
          </thead>
          <tbody>
            {ledgerList.map((rowData) => (
              <tr key={rowData.id} className="border-b border-gray-100 align-top">
                <td className="py-2 pr-3 whitespace-nowrap text-gray-700">
                  {rowData.transaction_date ?? '—'}
                </td>
                <td className="py-2 pr-3 whitespace-nowrap">
                  <span
                    className={cn('rounded-full px-2 py-0.5 text-xs', getRecordTypeClass(rowData))}
                  >
                    {getKubunText(rowData)}
                  </span>
                </td>
                <td className="py-2 pr-3 text-[#1b1b18]">{rowData.item_name ?? '—'}</td>
                <td className="py-2 pr-3 text-gray-700">{rowData.quantity}</td>
                <td className="py-2 pr-3 text-gray-600">{rowData.features ?? '—'}</td>
                <td className="py-2 pr-3 text-gray-700">
                  <p>{rowData.counterparty_name ?? '—'}</p>
                  {getCounterpartyDetail(rowData) !== null && (
                    <p className="text-xs text-gray-500">{getCounterpartyDetail(rowData)}</p>
                  )}
                </td>
                <td className="py-2 pr-3 text-gray-600">{getVerificationText(rowData)}</td>
                <td className="py-2 text-right whitespace-nowrap text-gray-800">
                  {formatYen(rowData.amount)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* モバイル：カード */}
      <ul className="mt-4 space-y-3 md:hidden">
        {ledgerList.map((rowData) => (
          <li key={rowData.id} className="rounded-lg border border-gray-200 bg-white p-4">
            <div className="flex items-start justify-between gap-2">
              <p className="font-medium text-[#1b1b18]">{rowData.item_name ?? '—'}</p>
              <span
                className={cn(
                  'shrink-0 rounded-full px-2 py-0.5 text-xs',
                  getRecordTypeClass(rowData),
                )}
              >
                {getKubunText(rowData)}
              </span>
            </div>
            <dl className="mt-2 space-y-1 text-sm text-gray-600">
              <div className="flex justify-between">
                <dt>取引年月日</dt>
                <dd>{rowData.transaction_date ?? '—'}</dd>
              </div>
              <div className="flex justify-between">
                <dt>数量</dt>
                <dd>{rowData.quantity}</dd>
              </div>
              {rowData.features !== null && rowData.features !== '' && (
                <div className="flex justify-between gap-4">
                  <dt className="shrink-0">特徴</dt>
                  <dd className="text-right">{rowData.features}</dd>
                </div>
              )}
              <div className="flex justify-between gap-4">
                <dt className="shrink-0">相手方</dt>
                <dd className="text-right">
                  {rowData.counterparty_name ?? '—'}
                  {getCounterpartyDetail(rowData) !== null && (
                    <span className="block text-xs text-gray-500">
                      {getCounterpartyDetail(rowData)}
                    </span>
                  )}
                </dd>
              </div>
              <div className="flex justify-between">
                <dt>確認方法</dt>
                <dd>{getVerificationText(rowData)}</dd>
              </div>
              <div className="flex justify-between">
                <dt>金額</dt>
                <dd className="text-gray-800">{formatYen(rowData.amount)}</dd>
              </div>
            </dl>
          </li>
        ))}
      </ul>

      {ledgerList.length === 0 && filtering === false && (
        <div className="py-16 text-center text-gray-500">
          <p className="text-sm">この期間の記録はありません。</p>
          <p className="mt-1 text-xs">仕入を登録すると、ここに台帳が自動でできあがります。</p>
        </div>
      )}

      <MoreButton
        loading={loadingMore === true}
        hasMore={ledgerList.length < totalCount}
        onLoad={loadMore}
      />
    </div>
  )
}

// 期間（from/to）から、どのプリセットに当たるかを求める（どれでもなければ任意期間）
function detectPreset(from: string, to: string): PeriodPresetValue {
  if (from === '' && to === '') {
    return 'all'
  } else {
    const matched = PERIOD_PRESET_LIST.find((presetData) => {
      const periodData = resolvePeriod(presetData.value)
      return periodData.from === from && periodData.to === to
    })
    return matched?.value ?? 'custom'
  }
}

// URL のクエリ（出力リンクにもそのまま付ける。空は載せない）
function getFilterQuery(from: string, to: string, q: string): Record<string, string> {
  const query: Record<string, string> = {}
  if (from !== '') {
    query.from = from
  } else {
    // no action.
  }
  if (to !== '') {
    query.to = to
  } else {
    // no action.
  }
  if (q !== '') {
    query.q = q
  } else {
    // no action.
  }
  return query
}

// 区分の表示：受入は Kubun（買受け/委託）、払出は販路を添える。
function getKubunText(rowData: LedgerRow): string {
  const base = RECORD_TYPE_LABEL[rowData.record_type]
  if (rowData.record_type === 'receive') {
    // 受入：買受け / 委託
    if (rowData.kubun !== null) {
      return `${base}（${KUBUN_LABEL[rowData.kubun]}）`
    } else {
      return base
    }
  } else {
    // 払出：販路
    if (rowData.channel !== null) {
      return `${base}（${SALES_CHANNEL_LABEL[rowData.channel]}）`
    } else {
      return base
    }
  }
}

function getRecordTypeClass(rowData: LedgerRow): string {
  if (rowData.record_type === 'receive') {
    return 'bg-blue-50 text-blue-700'
  } else {
    return 'bg-orange-50 text-orange-700'
  }
}

function getVerificationText(rowData: LedgerRow): string {
  if (rowData.verification_method !== null) {
    return VERIFICATION_METHOD_LABEL[rowData.verification_method]
  } else {
    return '—'
  }
}

// 相手方の補足（住所・職業・年齢）を1行にまとめる。
function getCounterpartyDetail(rowData: LedgerRow): string | null {
  const partList: string[] = []
  if (rowData.counterparty_address !== null) {
    partList.push(rowData.counterparty_address)
  } else {
    // no action.
  }
  if (rowData.counterparty_occupation !== null) {
    partList.push(rowData.counterparty_occupation)
  } else {
    // no action.
  }
  if (rowData.counterparty_age !== null) {
    partList.push(`${rowData.counterparty_age}歳`)
  } else {
    // no action.
  }

  //
  if (partList.length > 0) {
    return partList.join('・')
  } else {
    return null
  }
}

// 様式15号PDFの見出しに印字する営業所情報のうち、未設定のものを返す（未ログインなら空）
function getMissingOfficeItemList(user: User | null): string[] {
  const missingList: string[] = []
  if (user !== null) {
    // ok.
  } else {
    return missingList
  }

  //
  if (user.office_name === null || user.office_name === '') {
    missingList.push('営業所名')
  } else {
    // no action.
  }
  if (user.license_number === null || user.license_number === '') {
    missingList.push('許可番号')
  } else {
    // no action.
  }
  return missingList
}
