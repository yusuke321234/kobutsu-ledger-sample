import { router, useForm } from '@inertiajs/react'
import { useEffect, useId, useRef, useState } from 'react'
import PurchaseController from '@/actions/App/Http/Controllers/Purchase/PurchaseController'
import PurchasePhotoController from '@/actions/App/Http/Controllers/Purchase/PurchasePhotoController'
import BaseModal from '@/components/common/base-modal'
import InputError from '@/components/common/input-error'
import CounterpartyChips from '@/components/purchase/counterparty-chips'
import {
  EXCEPTION_ITEM_LIST,
  EXEMPTION_THRESHOLD,
  KUBUN,
  KUBUN_OPTION_LIST,
  matchExceptionCategory,
  VERIFICATION_METHOD,
  VERIFICATION_METHOD_OPTION_LIST,
} from '@/consts/purchase'
import { useCounterpartyList } from '@/hooks/use-counterparty-list'
import { useUi } from '@/hooks/use-ui'
import {
  getTodayString,
  toCodeOrNull,
  toInputText,
  toNumberOrNull,
  toQuantity,
  toTextOrNull,
} from '@/lib/format'
import { showToast } from '@/lib/toast'
import type { Counterparty, Purchase, PurchasePhoto } from '@/types'

// 入力中の値（数値欄は入力のまま文字列で持ち、送信時に数値へ直す）
type PurchaseInput = {
  transaction_date: string
  kubun: string
  item_name: string
  quantity: string
  features: string
  counterparty_name: string | null
  counterparty_address: string | null
  counterparty_occupation: string | null
  counterparty_age: string | null
  verification_method: string
  price: string
  is_exempt: boolean
  // 選んだ写真（保存時に一緒に送る。1つの仕入につき5枚まで）
  photo: File | null
}

const INPUT_CLASS =
  'mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none'

// 仕入登録フォーム。全画面共通の「＋仕入」。作成・編集・下書きの完成を兼ねる。
// 作成モードは連続登録（保存後に取引日/相手方/確認方法を引き継いで次の1点）。
export default function PurchaseFormModal() {
  const { purchaseForm, closePurchaseForm } = useUi()

  return (
    <BaseModal
      open={purchaseForm.open === true}
      title={getFormTitle(purchaseForm.target)}
      onClose={closePurchaseForm}
    >
      {/* 開くたびにフォームを作り直す（編集対象の反映・前回入力のクリア） */}
      <PurchaseForm
        key={purchaseForm.sessionKey}
        target={purchaseForm.target}
        onClose={closePurchaseForm}
      />
    </BaseModal>
  )
}

// モーダルの見出し（編集対象があれば編集、なければ新規登録）
function getFormTitle(target: Purchase | null): string {
  if (target !== null) {
    return '仕入を編集'
  } else {
    return '仕入を登録'
  }
}

// 保存済みの写真（編集時のみ）
function getSavedPhotoList(target: Purchase | null): PurchasePhoto[] {
  if (target !== null) {
    return target.photo_list
  } else {
    return []
  }
}

function getInitialInput(target: Purchase | null): PurchaseInput {
  if (target !== null) {
    return {
      transaction_date: target.transaction_date ?? getTodayString(),
      kubun: target.kubun,
      item_name: target.item_name ?? '',
      quantity: String(target.quantity),
      features: target.features ?? '',
      counterparty_name: target.counterparty_name,
      counterparty_address: target.counterparty_address,
      counterparty_occupation: target.counterparty_occupation,
      counterparty_age: target.counterparty_age,
      verification_method: target.verification_method ?? '',
      price: toInputText(target.price),
      is_exempt: target.is_exempt,
      photo: null,
    }
  } else {
    return {
      transaction_date: getTodayString(),
      kubun: KUBUN.BUY,
      item_name: '',
      quantity: '1',
      features: '',
      counterparty_name: null,
      counterparty_address: null,
      counterparty_occupation: null,
      counterparty_age: null,
      verification_method: '',
      price: '',
      is_exempt: false,
      photo: null,
    }
  }
}

// 免除の切り替え。免除にすると確認方法は「確認免除」に固定、外すと選び直し（様式15号の確認方法欄）
function applyExempt(data: PurchaseInput, isExempt: boolean): PurchaseInput {
  if (isExempt === true) {
    return { ...data, is_exempt: isExempt, verification_method: VERIFICATION_METHOD.EXEMPT }
  } else {
    // 免除を外したとき、確認方法が「確認免除」のままなら選び直してもらう
    if (data.verification_method === VERIFICATION_METHOD.EXEMPT) {
      return { ...data, is_exempt: isExempt, verification_method: '' }
    } else {
      return { ...data, is_exempt: isExempt }
    }
  }
}

interface PurchaseFormProps {
  target: Purchase | null
  onClose: () => void
}

function PurchaseForm({ target, onClose }: PurchaseFormProps) {
  const idPrefix = useId()
  const form = useForm<PurchaseInput>(getInitialInput(target))
  const counterpartyList = useCounterpartyList()
  const itemNameInputRef = useRef<HTMLInputElement>(null)
  const photoInputRef = useRef<HTMLInputElement>(null)

  // 写真：保存済み（編集時）と、選択中のプレビュー
  const [existingPhotoList, setExistingPhotoList] = useState<PurchasePhoto[]>(() =>
    getSavedPhotoList(target),
  )
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)
  const previewUrlRef = useRef<string | null>(null)

  // 閉じた時にプレビューの URL を解放する（開きっぱなしにするとメモリを掴んだままになる）
  useEffect(() => {
    return () => {
      if (previewUrlRef.current !== null) {
        URL.revokeObjectURL(previewUrlRef.current)
      } else {
        // no action.
      }
    }
  }, [])

  // 1万円未満は確認・記帳が任意（例外品目を除く）。まだ免除にしていなければ免除チェックを案内する。
  const price = toNumberOrNull(form.data.price)
  let showExemptionHint = false
  if (price !== null && price < EXEMPTION_THRESHOLD && form.data.is_exempt === false) {
    showExemptionHint = true
  } else {
    // no action.
  }
  // 品名が例外品目のキーワードに当たったカテゴリ（免除中の補助警告。取りこぼし前提の気づき補助）
  const matchedExceptionCategory = matchExceptionCategory(form.data.item_name)

  function selectPhoto(file: File | null): void {
    if (previewUrlRef.current !== null) {
      URL.revokeObjectURL(previewUrlRef.current)
    } else {
      // no action.
    }

    let nextPreviewUrl: string | null = null
    if (file !== null) {
      nextPreviewUrl = URL.createObjectURL(file)
    } else {
      // no action.
    }
    previewUrlRef.current = nextPreviewUrl
    setPreviewUrl(nextPreviewUrl)
    form.setData('photo', file)

    // 同じファイルを選び直しても change が起きるように、ファイル欄を空にしておく
    if (photoInputRef.current !== null) {
      photoInputRef.current.value = ''
    } else {
      // no action.
    }
  }

  // 保存済みの写真を消す（行もファイルも消える）。
  function removeExistingPhoto(photoData: PurchasePhoto): void {
    router.delete(PurchasePhotoController.delete.url(photoData.id), {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        setExistingPhotoList((currentList) =>
          currentList.filter((itemData) => itemData.id !== photoData.id),
        )
      },
    })
  }

  function selectCounterparty(counterpartyData: Counterparty): void {
    form.setData((current) => {
      // 相手方に紐づく確認方法があれば引き継ぐ（免除中は触らない）
      let verificationMethod = current.verification_method
      if (counterpartyData.verification_method !== null && current.is_exempt === false) {
        verificationMethod = counterpartyData.verification_method
      } else {
        // no action.
      }

      return {
        ...current,
        counterparty_name: counterpartyData.counterparty_name,
        counterparty_address: counterpartyData.counterparty_address,
        counterparty_occupation: counterpartyData.counterparty_occupation,
        counterparty_age: counterpartyData.counterparty_age,
        verification_method: verificationMethod,
      }
    })
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

  function submit(asDraft: boolean): void {
    // 送信値に整える（キーはサーバーの項目名と同じ snake_case。変換はしない）
    form.transform((data) => {
      const payload = {
        transaction_date: data.transaction_date,
        kubun: data.kubun,
        item_name: toTextOrNull(data.item_name),
        quantity: toQuantity(data.quantity),
        features: toTextOrNull(data.features),
        counterparty_name: data.counterparty_name,
        counterparty_address: data.counterparty_address,
        counterparty_occupation: data.counterparty_occupation,
        counterparty_age: data.counterparty_age,
        verification_method: toCodeOrNull(data.verification_method),
        price: toNumberOrNull(data.price),
        is_exempt: data.is_exempt,
        is_draft: asDraft,
        photo: data.photo,
      }
      // 写真付きの更新は multipart の POST で送り、_method で PUT として扱わせる
      if (target !== null) {
        if (data.photo !== null) {
          return { ...payload, _method: 'put' }
        } else {
          return payload
        }
      } else {
        return payload
      }
    })

    if (target !== null) {
      const updateUrl = PurchaseController.update.url(target.id)
      const options = { preserveScroll: true, onSuccess: onClose }
      if (form.data.photo !== null) {
        form.post(updateUrl, options)
      } else {
        form.put(updateUrl, options)
      }
    } else {
      form.post(PurchaseController.register.url(), {
        preserveScroll: true,
        onSuccess: () => {
          let message = '登録しました。続けて入力できます。'
          if (asDraft === true) {
            message = '下書きを保存しました。'
          } else {
            // no action.
          }
          showToast(message)
          // 連続登録：相手方・取引日・確認方法は残し、品目まわりだけ空にして次の1点へ
          selectPhoto(null)
          form.setData((current) =>
            applyExempt(
              {
                ...current,
                item_name: '',
                quantity: '1',
                features: '',
                price: '',
                photo: null,
              },
              false,
            ),
          )
          itemNameInputRef.current?.focus()
        },
      })
    }
  }

  // 保存ボタンの文言（編集は保存して閉じる、登録は続けて次の1点へ）
  let saveLabel = '保存中…'
  if (form.processing === false) {
    if (target !== null) {
      saveLabel = '保存する'
    } else {
      saveLabel = '保存して次へ'
    }
  } else {
    // no action.
  }

  return (
    <>
      <div className="mt-4 max-h-[75vh] space-y-4 overflow-y-auto pr-1">
        <div className="flex gap-3">
          <div className="flex-1">
            <label htmlFor={`${idPrefix}-date`} className="block text-sm text-gray-700">
              取引日
            </label>
            <input
              id={`${idPrefix}-date`}
              type="date"
              value={form.data.transaction_date}
              onChange={(event) => form.setData('transaction_date', event.target.value)}
              className={INPUT_CLASS}
            />
            <InputError message={form.errors.transaction_date} />
          </div>
          <div className="w-28">
            <label htmlFor={`${idPrefix}-kubun`} className="block text-sm text-gray-700">
              区分
            </label>
            <select
              id={`${idPrefix}-kubun`}
              value={form.data.kubun}
              onChange={(event) => form.setData('kubun', event.target.value)}
              className={INPUT_CLASS}
            >
              {KUBUN_OPTION_LIST.map((optionData) => (
                <option key={optionData.value} value={optionData.value}>
                  {optionData.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* 写真（1つの仕入につき5枚まで）。現場では写真＋金額だけで下書き保存できる */}
        <div>
          <label htmlFor={`${idPrefix}-photo`} className="block text-sm text-gray-700">
            写真
          </label>
          <div className="mt-1 flex flex-wrap items-center gap-2">
            {/* 保存済み */}
            {existingPhotoList.map((photoData) => (
              <div key={photoData.id} className="relative">
                <img
                  src={photoData.url}
                  alt="仕入の写真"
                  className="h-16 w-16 rounded-md border border-gray-200 object-cover"
                />
                <button
                  type="button"
                  aria-label="写真を削除"
                  className="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-800 text-xs text-white hover:bg-black"
                  onClick={() => removeExistingPhoto(photoData)}
                >
                  ×
                </button>
              </div>
            ))}

            {/* 選択中（保存時に一緒に送る） */}
            {previewUrl !== null && (
              <div className="relative">
                <img
                  src={previewUrl}
                  alt="選択した写真"
                  className="h-16 w-16 rounded-md border border-gray-200 object-cover"
                />
                <button
                  type="button"
                  aria-label="選択を取り消す"
                  className="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-gray-800 text-xs text-white hover:bg-black"
                  onClick={() => selectPhoto(null)}
                >
                  ×
                </button>
              </div>
            )}

            <label className="flex h-16 w-16 cursor-pointer items-center justify-center rounded-md border border-dashed border-gray-400 text-xs text-gray-500 hover:border-gray-600">
              ＋写真
              <input
                id={`${idPrefix}-photo`}
                ref={photoInputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(event) => selectPhoto(event.target.files?.[0] ?? null)}
              />
            </label>
          </div>
          <InputError message={form.errors.photo} />
          <p className="mt-1 text-xs text-gray-500">1つの仕入につき5枚まで付けられます。</p>
        </div>

        <div>
          <label htmlFor={`${idPrefix}-item`} className="block text-sm text-gray-700">
            品目
          </label>
          <input
            id={`${idPrefix}-item`}
            ref={itemNameInputRef}
            type="text"
            placeholder="例：Nikon F3 ボディ"
            value={form.data.item_name}
            onChange={(event) => form.setData('item_name', event.target.value)}
            className={INPUT_CLASS}
          />
          <InputError message={form.errors.item_name} />
        </div>

        <div className="flex gap-3">
          <div className="w-24">
            <label htmlFor={`${idPrefix}-quantity`} className="block text-sm text-gray-700">
              数量
            </label>
            <input
              id={`${idPrefix}-quantity`}
              type="number"
              min="1"
              value={form.data.quantity}
              onChange={(event) => form.setData('quantity', event.target.value)}
              className={INPUT_CLASS}
            />
          </div>
          <div className="flex-1">
            <label htmlFor={`${idPrefix}-price`} className="block text-sm text-gray-700">
              仕入金額（円）
            </label>
            <input
              id={`${idPrefix}-price`}
              type="number"
              min="0"
              placeholder="0"
              value={form.data.price}
              onChange={(event) => form.setData('price', event.target.value)}
              className={INPUT_CLASS}
            />
            <InputError message={form.errors.price} />
          </div>
        </div>

        <div>
          <label htmlFor={`${idPrefix}-features`} className="block text-sm text-gray-700">
            特徴（型番・シリアル・状態）
          </label>
          <textarea
            id={`${idPrefix}-features`}
            rows={2}
            value={form.data.features}
            onChange={(event) => form.setData('features', event.target.value)}
            className={INPUT_CLASS}
          />
        </div>

        <div>
          <p className="block text-sm text-gray-700">相手方</p>
          {form.data.counterparty_name !== null && form.data.counterparty_name !== '' && (
            <p className="mt-1 text-sm font-medium text-[#1b1b18]">{form.data.counterparty_name}</p>
          )}
          <div className="mt-2">
            <CounterpartyChips
              counterpartyList={counterpartyList}
              activeName={form.data.counterparty_name}
              onSelect={selectCounterparty}
              onClear={clearCounterparty}
            />
          </div>
          <InputError message={form.errors.counterparty_name} />
        </div>

        <div>
          <label htmlFor={`${idPrefix}-verification`} className="block text-sm text-gray-700">
            確認方法
          </label>
          <select
            id={`${idPrefix}-verification`}
            value={form.data.verification_method}
            disabled={form.data.is_exempt === true}
            onChange={(event) => form.setData('verification_method', event.target.value)}
            className={`${INPUT_CLASS} disabled:bg-gray-100`}
          >
            <option value="">未選択</option>
            {VERIFICATION_METHOD_OPTION_LIST.map((optionData) => (
              <option key={optionData.value} value={optionData.value}>
                {optionData.label}
              </option>
            ))}
          </select>
          <InputError message={form.errors.verification_method} />
        </div>

        <div>
          <label className="flex items-center gap-2 text-sm text-gray-700">
            <input
              type="checkbox"
              checked={form.data.is_exempt === true}
              onChange={(event) =>
                form.setData((current) => applyExempt(current, event.target.checked))
              }
              className="rounded border-gray-300"
            />
            <span>1万円未満などで確認・記帳を免除する</span>
          </label>
          {showExemptionHint === true && (
            <p className="mt-1 text-xs text-gray-500">
              1万円未満の買受は確認・記帳が任意です（書籍・ゲームソフト・CD/DVD・バイク部品・金属類などの例外品目を除く）。免除にすると例外品目の一覧を表示します。
            </p>
          )}

          {/* 免除中は例外品目チェックリストを必ず提示（法定の正）。品名がキーワード一致したら上部で強めに警告。 */}
          {form.data.is_exempt === true && (
            <div className="mt-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900">
              {matchedExceptionCategory !== null && (
                <p className="mb-2 rounded bg-amber-100 px-2 py-1 font-medium text-amber-900">
                  ⚠️「{form.data.item_name}」は例外品目（{matchedExceptionCategory.label}
                  ）の可能性があります。該当する場合は免除を外してください。
                </p>
              )}
              <p className="font-medium">
                次の例外品目は、1万円未満でも記帳・確認が必要です（免除できません）：
              </p>
              <ul className="mt-1 list-disc space-y-0.5 pl-4">
                {EXCEPTION_ITEM_LIST.map((categoryData) => (
                  <li key={categoryData.label}>
                    {categoryData.label}
                    {categoryData.note !== undefined && (
                      <span className="text-amber-700">（{categoryData.note}）</span>
                    )}
                  </li>
                ))}
              </ul>
              <p className="mt-2 text-amber-700">
                上記に該当しない場合のみ免除できます。判断に迷う取引は記帳しておくのが安全です。
              </p>
            </div>
          )}
        </div>
      </div>

      <div className="mt-5 flex flex-col gap-2">
        <button
          type="button"
          disabled={form.processing === true}
          className="w-full rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60"
          onClick={() => submit(false)}
        >
          {saveLabel}
        </button>
        <button
          type="button"
          disabled={form.processing === true}
          className="w-full rounded-md border border-gray-300 px-4 py-2 text-sm text-[#1b1b18] hover:border-black disabled:cursor-not-allowed disabled:opacity-60"
          onClick={() => submit(true)}
        >
          下書き保存
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
