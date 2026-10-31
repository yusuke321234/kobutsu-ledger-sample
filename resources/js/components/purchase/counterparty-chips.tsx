import { PlusIcon } from '@heroicons/react/20/solid'
import { useId, useState } from 'react'
import BaseModal from '@/components/common/base-modal'
import InputError from '@/components/common/input-error'
import { VERIFICATION_METHOD_OPTION_LIST } from '@/consts/purchase'
import { toCodeOrNull, toTextOrNull } from '@/lib/format'
import type { Counterparty } from '@/types'

interface CounterpartyChipsProps {
  counterpartyList: Counterparty[]
  activeName: string | null
  onSelect: (counterpartyData: Counterparty) => void
  onClear: () => void
}

// 相手方チップ。過去の仕入から取得した相手方をチップで1クリック選択。
// 「＋新規」は小さなモーダルで一度だけフル入力（住所/氏名/職業/年齢/確認方法）→ 以後はチップになる。
export default function CounterpartyChips({
  counterpartyList,
  activeName,
  onSelect,
  onClear,
}: CounterpartyChipsProps) {
  const [popoverOpen, setPopoverOpen] = useState(false)
  // 開くたびに入力を空にする（フォームを作り直す）
  const [popoverKey, setPopoverKey] = useState(0)

  function openPopover(): void {
    setPopoverKey((current) => current + 1)
    setPopoverOpen(true)
  }

  function selectNewCounterparty(counterpartyData: Counterparty): void {
    onSelect(counterpartyData)
    setPopoverOpen(false)
  }

  return (
    <div>
      <div className="flex flex-wrap items-center gap-2">
        {counterpartyList.map((counterpartyData) => (
          <button
            key={counterpartyData.counterparty_name}
            type="button"
            className={
              counterpartyData.counterparty_name === activeName
                ? 'rounded-full border border-[#1b1b18] bg-[#1b1b18] px-3 py-1 text-sm text-white'
                : 'rounded-full border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:border-gray-500'
            }
            onClick={() => onSelect(counterpartyData)}
          >
            {counterpartyData.counterparty_name}
          </button>
        ))}

        <button
          type="button"
          className="flex items-center gap-1 rounded-full border border-dashed border-gray-400 px-3 py-1 text-sm text-gray-600 hover:border-gray-600"
          onClick={openPopover}
        >
          <PlusIcon className="h-4 w-4" aria-hidden="true" />
          <span>新規</span>
        </button>

        {activeName !== null && activeName !== '' && (
          <button
            type="button"
            className="text-xs text-gray-500 underline hover:text-gray-700"
            onClick={onClear}
          >
            クリア
          </button>
        )}
      </div>

      {/* 新規相手方の一度だけフル入力（様式15号の相手方4項目 + 確認方法） */}
      <BaseModal
        open={popoverOpen === true}
        title="相手方を追加"
        align="center"
        onClose={() => setPopoverOpen(false)}
      >
        <NewCounterpartyForm key={popoverKey} onSubmit={selectNewCounterparty} />
      </BaseModal>
    </div>
  )
}

// 新規相手方の入力欄（氏名・店舗名だけ必須）。
function NewCounterpartyForm({ onSubmit }: { onSubmit: (counterpartyData: Counterparty) => void }) {
  const idPrefix = useId()
  const [name, setName] = useState('')
  const [address, setAddress] = useState('')
  const [occupation, setOccupation] = useState('')
  const [age, setAge] = useState('')
  const [verificationMethod, setVerificationMethod] = useState('')
  const [nameError, setNameError] = useState('')

  function submit(): void {
    const counterpartyName = toTextOrNull(name)
    if (counterpartyName !== null) {
      onSubmit({
        counterparty_name: counterpartyName,
        counterparty_address: toTextOrNull(address),
        counterparty_occupation: toTextOrNull(occupation),
        counterparty_age: toTextOrNull(age),
        verification_method: toCodeOrNull(verificationMethod),
      })
    } else {
      setNameError('氏名（店舗名）を入力してください。')
    }
  }

  return (
    <div className="mt-4 space-y-3">
      <div>
        <label htmlFor={`${idPrefix}-name`} className="block text-sm text-gray-700">
          氏名・店舗名
        </label>
        <input
          id={`${idPrefix}-name`}
          type="text"
          value={name}
          onChange={(event) => setName(event.target.value)}
          className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
        />
        <InputError message={nameError} />
      </div>

      <div>
        <label htmlFor={`${idPrefix}-address`} className="block text-sm text-gray-700">
          住所
        </label>
        <input
          id={`${idPrefix}-address`}
          type="text"
          value={address}
          onChange={(event) => setAddress(event.target.value)}
          className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
        />
      </div>

      <div className="flex gap-3">
        <div className="flex-1">
          <label htmlFor={`${idPrefix}-occupation`} className="block text-sm text-gray-700">
            職業
          </label>
          <input
            id={`${idPrefix}-occupation`}
            type="text"
            value={occupation}
            onChange={(event) => setOccupation(event.target.value)}
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
          />
        </div>
        <div className="w-24">
          <label htmlFor={`${idPrefix}-age`} className="block text-sm text-gray-700">
            年齢
          </label>
          <input
            id={`${idPrefix}-age`}
            type="text"
            value={age}
            onChange={(event) => setAge(event.target.value)}
            className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
          />
        </div>
      </div>

      <div>
        <label htmlFor={`${idPrefix}-verification`} className="block text-sm text-gray-700">
          確認方法
        </label>
        <select
          id={`${idPrefix}-verification`}
          value={verificationMethod}
          onChange={(event) => setVerificationMethod(event.target.value)}
          className="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
        >
          <option value="">未選択</option>
          {VERIFICATION_METHOD_OPTION_LIST.map((optionData) => (
            <option key={optionData.value} value={optionData.value}>
              {optionData.label}
            </option>
          ))}
        </select>
      </div>

      <button
        type="button"
        className="w-full rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black"
        onClick={submit}
      >
        この相手方を使う
      </button>
    </div>
  )
}
