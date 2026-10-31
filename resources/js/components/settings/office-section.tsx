import { useForm } from '@inertiajs/react'
import { useState } from 'react'
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthController'
import InputError from '@/components/common/input-error'
import SettingsSection from '@/components/settings/settings-section'
import type { User } from '@/types'

const INPUT_CLASS =
  'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none'

interface OfficeSectionProps {
  userData: User
}

// 営業所情報：様式15号PDFのヘッダに印字される
export default function OfficeSection({ userData }: OfficeSectionProps) {
  return (
    <SettingsSection
      title="営業所情報"
      description="古物台帳（様式15号）のPDFヘッダに印字されます。"
    >
      <OfficeEditor userData={userData} />
    </SettingsSection>
  )
}

// 営業所情報（表示 ⇔ 編集）。未設定でも記録・出力はできる。
function OfficeEditor({ userData }: { userData: User }) {
  //
  const [editing, setEditing] = useState(false)
  const form = useForm({
    office_name: userData.office_name ?? '',
    license_number: userData.license_number ?? '',
  })

  //
  function startEdit(): void {
    form.setData({
      office_name: userData.office_name ?? '',
      license_number: userData.license_number ?? '',
    })
    form.clearErrors()
    setEditing(true)
  }

  //
  function save(): void {
    form.put(AuthController.updateOffice.url(), {
      preserveScroll: true,
      onSuccess: () => setEditing(false),
    })
  }

  //
  if (editing === false) {
    return (
      <div className="rounded-md border border-gray-200">
        <div className="divide-y divide-gray-200">
          <div className="flex items-center justify-between px-4 py-3">
            <span className="text-sm text-gray-500">営業所名</span>
            <span className="flex items-center gap-3">
              {userData.office_name !== null ? (
                <span className="text-sm text-[#1b1b18]">{userData.office_name}</span>
              ) : (
                <span className="text-sm text-gray-400">未設定</span>
              )}
              <button
                type="button"
                className="text-sm text-gray-500 hover:text-gray-800"
                onClick={startEdit}
              >
                変更
              </button>
            </span>
          </div>
          <div className="flex items-center justify-between px-4 py-3">
            <span className="text-sm text-gray-500">許可番号</span>
            {userData.license_number !== null ? (
              <span className="text-sm text-[#1b1b18]">{userData.license_number}</span>
            ) : (
              <span className="text-sm text-gray-400">未設定</span>
            )}
          </div>
        </div>
      </div>
    )
  } else {
    return (
      <div className="rounded-md border border-gray-200">
        <div className="px-4 py-3">
          <div>
            <label htmlFor="office-name" className="block text-sm text-gray-500">
              営業所名
            </label>
            <input
              id="office-name"
              type="text"
              placeholder="例：コブチョウ商店"
              value={form.data.office_name}
              onChange={(event) => form.setData('office_name', event.target.value)}
              className={`mt-1 ${INPUT_CLASS}`}
            />
            <p className="mt-1 text-xs text-gray-500">
              許可証には載っていません。許可申請書の営業所の「名称」に書いたものを入れます（屋号がなければ氏名で可）。
            </p>
            <InputError message={form.errors.office_name} className="text-xs" />
          </div>
          <div className="mt-3">
            <label htmlFor="license-number" className="block text-sm text-gray-500">
              許可番号
            </label>
            <input
              id="license-number"
              type="text"
              placeholder="例：兵庫県公安委員会 第123456789012号"
              value={form.data.license_number}
              onChange={(event) => form.setData('license_number', event.target.value)}
              className={`mt-1 ${INPUT_CLASS}`}
            />
            <p className="mt-1 text-xs text-gray-500">
              許可証の表紙の内側にある「○○公安委員会」と「第○○○号」をそのまま入れます。
            </p>
            <InputError message={form.errors.license_number} className="text-xs" />
          </div>
          <div className="mt-3 flex gap-2">
            <button
              type="button"
              className="flex-1 rounded-md border border-gray-300 py-2 text-sm hover:border-black"
              onClick={() => setEditing(false)}
            >
              キャンセル
            </button>
            <button
              type="button"
              disabled={form.processing === true}
              className="flex-1 rounded-md bg-[#1b1b18] py-2 text-sm font-medium text-white hover:bg-black disabled:opacity-60"
              onClick={save}
            >
              {form.processing === true ? '保存中…' : '保存'}
            </button>
          </div>
        </div>
      </div>
    )
  }
}
