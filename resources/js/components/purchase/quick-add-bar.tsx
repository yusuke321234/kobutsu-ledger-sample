import { useForm } from '@inertiajs/react'
import { useId, useRef, type FormEvent } from 'react'
import PurchaseController from '@/actions/App/Http/Controllers/Purchase/PurchaseController'
import CounterpartyChips from '@/components/purchase/counterparty-chips'
import { KUBUN, VERIFICATION_METHOD_OPTION_LIST } from '@/consts/purchase'
import {
  getTodayString,
  toCodeOrNull,
  toNumberOrNull,
  toQuantity,
  toTextOrNull,
} from '@/lib/format'
import { getInputErrorList } from '@/lib/form-errors'
import { showToast } from '@/lib/toast'
import type { Counterparty } from '@/types'

// 入力欄の値
type QuickAddInput = {
  // 引き継ぎコンテキスト
  transaction_date: string
  verification_method: string
  counterparty_name: string | null
  counterparty_address: string | null
  counterparty_occupation: string | null
  counterparty_age: string | null
  // クイック追加行
  item_name: string
  quantity: string
  price: string
}

interface QuickAddBarProps {
  counterpartyList: Counterparty[]
}

// PC クイック追加。上段＝引き継ぎコンテキスト（取引日・相手方・確認方法）、
// 下段＝クイック追加行（品目・数量・金額）。Enter で「保存して次行」、取引日/相手方/確認方法は引き継ぐ。
export default function QuickAddBar({ counterpartyList }: QuickAddBarProps) {
  const idPrefix = useId()
  const itemNameInputRef = useRef<HTMLInputElement>(null)
  const form = useForm<QuickAddInput>({
    transaction_date: getTodayString(),
    verification_method: '',
    counterparty_name: null,
    counterparty_address: null,
    counterparty_occupation: null,
    counterparty_age: null,
    item_name: '',
    quantity: '1',
    price: '',
  })

  // 項目ごとのエラーは出し分けず、最初の1件だけを行の下に出す
  const firstError = getInputErrorList(form.errors)[0] ?? ''

  function selectCounterparty(counterpartyData: Counterparty): void {
    form.setData((current) => ({
      ...current,
      counterparty_name: counterpartyData.counterparty_name,
      counterparty_address: counterpartyData.counterparty_address,
      counterparty_occupation: counterpartyData.counterparty_occupation,
      counterparty_age: counterpartyData.counterparty_age,
      verification_method: counterpartyData.verification_method ?? current.verification_method,
    }))
  }

  function clearCounterparty(): void {
    form.setData((current) => ({
      ...current,
      counterparty_name: null,
      counterparty_address: null,
      counterparty_occupation: null,
      counterparty_age: null,
    }))
  }

  function submit(event: FormEvent<HTMLFormElement>): void {
    event.preventDefault()
    form.transform((data) => ({
      transaction_date: data.transaction_date,
      kubun: KUBUN.BUY,
      item_name: toTextOrNull(data.item_name),
      quantity: toQuantity(data.quantity),
      features: null,
      counterparty_name: data.counterparty_name,
      counterparty_address: data.counterparty_address,
      counterparty_occupation: data.counterparty_occupation,
      counterparty_age: data.counterparty_age,
      verification_method: toCodeOrNull(data.verification_method),
      price: toNumberOrNull(data.price),
      is_exempt: false,
      is_draft: false,
    }))
    form.post(PurchaseController.register.url(), {
      preserveScroll: true,
      onSuccess: () => {
        showToast('追加しました。')
        // 取引日・相手方・確認方法は引き継ぎ、品目・数量・金額だけ空にして次行へ
        form.setData((current) => ({
          ...current,
          item_name: '',
          quantity: '1',
          price: '',
        }))
        itemNameInputRef.current?.focus()
      },
    })
  }

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4">
      {/* 引き継ぎコンテキスト */}
      <div className="flex flex-wrap items-end gap-4">
        <div>
          <label htmlFor={`${idPrefix}-date`} className="block text-xs text-gray-500">
            取引日
          </label>
          <input
            id={`${idPrefix}-date`}
            type="date"
            value={form.data.transaction_date}
            onChange={(event) => form.setData('transaction_date', event.target.value)}
            className="mt-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-300 focus:outline-none"
          />
        </div>
        <div>
          <label htmlFor={`${idPrefix}-verification`} className="block text-xs text-gray-500">
            確認方法
          </label>
          <select
            id={`${idPrefix}-verification`}
            value={form.data.verification_method}
            onChange={(event) => form.setData('verification_method', event.target.value)}
            className="mt-1 rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-300 focus:outline-none"
          >
            <option value="">未選択</option>
            {VERIFICATION_METHOD_OPTION_LIST.map((optionData) => (
              <option key={optionData.value} value={optionData.value}>
                {optionData.label}
              </option>
            ))}
          </select>
        </div>
        <div className="min-w-0 flex-1">
          <p className="block text-xs text-gray-500">相手方</p>
          <div className="mt-1">
            <CounterpartyChips
              counterpartyList={counterpartyList}
              activeName={form.data.counterparty_name}
              onSelect={selectCounterparty}
              onClear={clearCounterparty}
            />
          </div>
        </div>
      </div>

      {/* クイック追加行（Enter=追加して次行） */}
      <form className="mt-4 flex flex-wrap items-center gap-2" onSubmit={submit}>
        <input
          ref={itemNameInputRef}
          type="text"
          placeholder="品目（例：Nikon F3 ボディ）"
          aria-label="品目"
          value={form.data.item_name}
          onChange={(event) => form.setData('item_name', event.target.value)}
          className="min-w-0 flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
        />
        <input
          type="number"
          min="1"
          aria-label="数量"
          value={form.data.quantity}
          onChange={(event) => form.setData('quantity', event.target.value)}
          className="w-16 rounded-md border border-gray-300 px-2 py-2 text-sm focus:border-blue-300 focus:outline-none"
        />
        <input
          type="number"
          min="0"
          placeholder="金額"
          aria-label="仕入金額"
          value={form.data.price}
          onChange={(event) => form.setData('price', event.target.value)}
          className="w-28 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
        />
        <button
          type="submit"
          disabled={form.processing === true}
          className="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-semibold text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60"
        >
          追加
        </button>
      </form>
      {firstError !== '' && <p className="mt-2 text-sm text-[#f53003]">{firstError}</p>}
    </div>
  )
}
