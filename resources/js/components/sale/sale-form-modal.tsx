import { useForm } from '@inertiajs/react'
import { useId, useRef, type RefObject } from 'react'
import SaleController from '@/actions/App/Http/Controllers/Sale/SaleController'
import BaseModal from '@/components/common/base-modal'
import InputError from '@/components/common/input-error'
import { SALES_CHANNEL, SALES_CHANNEL_OPTION_LIST } from '@/consts/purchase'
import { useUi } from '@/hooks/use-ui'
import { getTodayString, toNumberOrNull, formatYen } from '@/lib/format'
import { excludePurchase } from '@/lib/inventory-optimistic'
import { cn } from '@/lib/utils'
import type { Purchase } from '@/types'

// 入力欄の値
type SaleInput = {
  purchase_id: string
  sale_date: string
  channel: string
  sale_price: string
  fee: string
  shipping_cost: string
}

const INPUT_CLASS =
  'mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none'

// 売却フォーム。在庫の「売れた」ワンタップ→販路・売価→**粗利即表示**。
// 保存すると在庫が売却済になり、台帳の払出行も自動でできる（サーバー側で導出）。
// 売却を記録すると、台帳の払出しの行が自動でできる。
export default function SaleFormModal() {
  const { saleForm, closeSaleForm } = useUi()
  const salePriceInputRef = useRef<HTMLInputElement>(null)

  return (
    <BaseModal
      open={saleForm.open === true}
      title="売れた"
      onClose={closeSaleForm}
      onOpenAutoFocus={(event) => {
        // 開いたらすぐ売価を打てるようにする
        event.preventDefault()
        salePriceInputRef.current?.focus()
      }}
    >
      {saleForm.purchaseData !== null && (
        <SaleForm
          key={saleForm.sessionKey}
          purchaseData={saleForm.purchaseData}
          salePriceInputRef={salePriceInputRef}
          onClose={closeSaleForm}
        />
      )}
    </BaseModal>
  )
}

interface SaleFormProps {
  purchaseData: Purchase
  salePriceInputRef: RefObject<HTMLInputElement | null>
  onClose: () => void
}

function SaleForm({ purchaseData, salePriceInputRef, onClose }: SaleFormProps) {
  const idPrefix = useId()
  const form = useForm<SaleInput>({
    purchase_id: purchaseData.id,
    sale_date: getTodayString(),
    channel: SALES_CHANNEL.MERCARI,
    sale_price: '',
    fee: '',
    shipping_cost: '',
  })

  // 単品の素朴粗利（売価−仕入）。入力しながら即座に見せる。
  const salePrice = toNumberOrNull(form.data.sale_price)
  let rawGrossProfit: number | null = null
  if (purchaseData.price !== null && salePrice !== null) {
    rawGrossProfit = salePrice - purchaseData.price
  } else {
    // no action.
  }

  function submit(): void {
    form.transform((data) => ({
      purchase_id: data.purchase_id,
      sale_date: data.sale_date,
      channel: data.channel,
      sale_price: toNumberOrNull(data.sale_price),
      fee: toNumberOrNull(data.fee),
      shipping_cost: toNumberOrNull(data.shipping_cost),
    }))
    form.post(SaleController.register.url(), {
      preserveScroll: true,
      // 「在庫中」を表示中なら、返事を待たずに一覧から外す（失敗したら元に戻る）
      optimistic: (props) => excludePurchase(props, purchaseData.id, 'in_stock'),
      // 完了の案内（粗利つき）はサーバーのフラッシュで出る
      onSuccess: onClose,
    })
  }

  return (
    <>
      <div className="mt-4 space-y-4">
        {/* 何を売ったか */}
        <div className="rounded-md bg-gray-50 px-3 py-2">
          <p className="text-sm font-medium text-[#1b1b18]">{purchaseData.item_name ?? '—'}</p>
          <p className="mt-0.5 text-xs text-gray-500">仕入 {formatYen(purchaseData.price)}</p>
        </div>

        <div className="flex gap-3">
          <div className="flex-1">
            <label htmlFor={`${idPrefix}-date`} className="block text-sm text-gray-700">
              売却日
            </label>
            <input
              id={`${idPrefix}-date`}
              type="date"
              value={form.data.sale_date}
              onChange={(event) => form.setData('sale_date', event.target.value)}
              className={INPUT_CLASS}
            />
            <InputError message={form.errors.sale_date} />
          </div>
          <div className="flex-1">
            <label htmlFor={`${idPrefix}-channel`} className="block text-sm text-gray-700">
              販路
            </label>
            <select
              id={`${idPrefix}-channel`}
              value={form.data.channel}
              onChange={(event) => form.setData('channel', event.target.value)}
              className={INPUT_CLASS}
            >
              {SALES_CHANNEL_OPTION_LIST.map((optionData) => (
                <option key={optionData.value} value={optionData.value}>
                  {optionData.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div>
          <label htmlFor={`${idPrefix}-price`} className="block text-sm text-gray-700">
            売却金額（円）
          </label>
          <input
            id={`${idPrefix}-price`}
            ref={salePriceInputRef}
            type="number"
            min="0"
            placeholder="0"
            value={form.data.sale_price}
            onChange={(event) => form.setData('sale_price', event.target.value)}
            className={INPUT_CLASS}
          />
          <InputError message={form.errors.sale_price} />
        </div>

        {/* 粗利即表示（売価を入れた瞬間に出る） */}
        <div className="rounded-md border border-gray-200 px-3 py-2">
          <div className="flex items-center justify-between">
            <span className="text-sm text-gray-600">粗利（売価 − 仕入）</span>
            <span
              className={cn(
                'text-lg font-semibold',
                rawGrossProfit !== null && rawGrossProfit < 0 ? 'text-[#f53003]' : 'text-[#1b1b18]',
              )}
            >
              {formatYen(rawGrossProfit)}
            </span>
          </div>
        </div>

        <div className="flex gap-3">
          <div className="flex-1">
            <label htmlFor={`${idPrefix}-fee`} className="block text-sm text-gray-700">
              販売手数料（円）
            </label>
            <input
              id={`${idPrefix}-fee`}
              type="number"
              min="0"
              placeholder="0"
              value={form.data.fee}
              onChange={(event) => form.setData('fee', event.target.value)}
              className={INPUT_CLASS}
            />
          </div>
          <div className="flex-1">
            <label htmlFor={`${idPrefix}-shipping`} className="block text-sm text-gray-700">
              送料（円）
            </label>
            <input
              id={`${idPrefix}-shipping`}
              type="number"
              min="0"
              placeholder="0"
              value={form.data.shipping_cost}
              onChange={(event) => form.setData('shipping_cost', event.target.value)}
              className={INPUT_CLASS}
            />
          </div>
        </div>
        <InputError message={form.errors.purchase_id} className="mt-0" />
      </div>

      <div className="mt-5 flex flex-col gap-2">
        <button
          type="button"
          disabled={form.processing === true}
          className="w-full rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60"
          onClick={submit}
        >
          {form.processing === true ? '保存中…' : '売却を記録する'}
        </button>
        <button
          type="button"
          className="w-full px-4 py-1 text-sm text-gray-500 hover:text-gray-700"
          onClick={onClose}
        >
          閉じる
        </button>
      </div>
    </>
  )
}
