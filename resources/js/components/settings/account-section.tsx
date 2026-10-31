import { useForm } from '@inertiajs/react'
import { useState } from 'react'
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthController'
import InputError from '@/components/common/input-error'
import SettingsSection from '@/components/settings/settings-section'
import type { User } from '@/types'

const INPUT_CLASS =
  'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none'

interface AccountSectionProps {
  userData: User
}

// アカウント（氏名は変更可、メールアドレスは表示のみ）
export default function AccountSection({ userData }: AccountSectionProps) {
  return (
    <SettingsSection title="アカウント">
      <div className="divide-y divide-gray-200 rounded-md border border-gray-200">
        <NameEditor userData={userData} />
        <div className="flex items-center justify-between px-4 py-3">
          <span className="text-sm text-gray-500">メール</span>
          <span className="text-sm text-[#1b1b18]">{userData.email}</span>
        </div>
      </div>
    </SettingsSection>
  )
}

// 氏名（表示 ⇔ 編集）。[変更] で編集モードに入り、保存で更新する。
function NameEditor({ userData }: { userData: User }) {
  //
  const [editing, setEditing] = useState(false)
  const form = useForm({
    family_name: userData.family_name,
    given_name: userData.given_name,
  })

  //
  function startEdit(): void {
    form.setData({
      family_name: userData.family_name,
      given_name: userData.given_name,
    })
    form.clearErrors()
    setEditing(true)
  }

  //
  function save(): void {
    form.put(AuthController.updateName.url(), {
      preserveScroll: true,
      onSuccess: () => setEditing(false),
    })
  }

  //
  if (editing === false) {
    return (
      <div className="flex items-center justify-between px-4 py-3">
        <span className="text-sm text-gray-500">氏名</span>
        <span className="flex items-center gap-3">
          <span className="text-sm text-[#1b1b18]">
            {userData.family_name} {userData.given_name}
          </span>
          <button
            type="button"
            className="text-sm text-gray-500 hover:text-gray-800"
            onClick={startEdit}
          >
            変更
          </button>
        </span>
      </div>
    )
  } else {
    return (
      <div className="px-4 py-3">
        <p className="mb-2 text-sm text-gray-500">氏名</p>
        <div className="flex gap-2">
          <div className="flex-1">
            <input
              type="text"
              placeholder="姓"
              aria-label="姓"
              required
              value={form.data.family_name}
              onChange={(event) => form.setData('family_name', event.target.value)}
              className={INPUT_CLASS}
            />
            <InputError message={form.errors.family_name} className="text-xs" />
          </div>
          <div className="flex-1">
            <input
              type="text"
              placeholder="名"
              aria-label="名"
              required
              value={form.data.given_name}
              onChange={(event) => form.setData('given_name', event.target.value)}
              className={INPUT_CLASS}
            />
            <InputError message={form.errors.given_name} className="text-xs" />
          </div>
        </div>
        <div className="mt-2 flex gap-2">
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
    )
  }
}
