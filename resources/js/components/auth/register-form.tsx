import { Form, Link } from '@inertiajs/react'
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthController'
import InputError from '@/components/common/input-error'
import { getInputErrorList } from '@/lib/form-errors'
import { login } from '@/routes'

// 入力欄の値（サーバーと同じ項目名）
type RegisterInput = {
  family_name: string
  given_name: string
  email: string
  password: string
}

// 新規登録フォーム。姓・名・メール・パスワード。
// メール確認はしないので、登録成功＝自動ログイン → 在庫へ。
export default function RegisterForm() {
  return (
    <div>
      <Form<RegisterInput> {...AuthController.register.form()}>
        {({ processing, errors }) => (
          <>
            {getInputErrorList(errors).length > 0 && (
              <p className="mb-4 rounded-md bg-[#fff2f2] px-3 py-2 text-sm text-[#f53003]">
                入力内容を確認してください。
              </p>
            )}

            <div className="space-y-4">
              <div className="flex gap-3">
                <div className="flex-1">
                  <label
                    htmlFor="family-name"
                    className="mb-1 block text-sm font-medium text-gray-700"
                  >
                    姓
                  </label>
                  <input
                    id="family-name"
                    name="family_name"
                    type="text"
                    autoComplete="family-name"
                    required
                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
                  />
                  <InputError message={errors.family_name} />
                </div>
                <div className="flex-1">
                  <label
                    htmlFor="given-name"
                    className="mb-1 block text-sm font-medium text-gray-700"
                  >
                    名
                  </label>
                  <input
                    id="given-name"
                    name="given_name"
                    type="text"
                    autoComplete="given-name"
                    required
                    className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
                  />
                  <InputError message={errors.given_name} />
                </div>
              </div>

              <div>
                <label htmlFor="email" className="mb-1 block text-sm font-medium text-gray-700">
                  メールアドレス
                </label>
                <input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  required
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
                />
                <InputError message={errors.email} />
              </div>

              <div>
                <label htmlFor="password" className="mb-1 block text-sm font-medium text-gray-700">
                  パスワード（8文字以上）
                </label>
                <input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="new-password"
                  required
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
                />
                <InputError message={errors.password} />
              </div>

              <button
                type="submit"
                disabled={processing === true}
                className="w-full rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60"
              >
                {processing === true ? '登録中…' : '登録する'}
              </button>
            </div>
          </>
        )}
      </Form>

      <div className="mt-6 space-y-3 text-center text-sm">
        <p className="text-gray-600">アカウントをお持ちの方</p>
        <Link
          href={login.url()}
          className="inline-block rounded-md border border-gray-300 px-5 py-2 text-[#1b1b18] hover:border-black"
        >
          ログイン
        </Link>
      </div>
    </div>
  )
}
