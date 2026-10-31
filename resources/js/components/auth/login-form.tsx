import type { FormDataErrors } from '@inertiajs/core'
import { Form, Link } from '@inertiajs/react'
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthController'
import InputError from '@/components/common/input-error'
import { getInputErrorList } from '@/lib/form-errors'
import { register } from '@/routes'

// 認証に失敗したときのサーバーの文言（この時だけ総合メッセージにも同じ文言を出す）
const LOGIN_FAILED_MESSAGE = 'メールアドレスまたはパスワードが違います。'

// 入力欄の値（サーバーと同じ項目名）
type LoginInput = {
  email: string
  password: string
  remember: boolean
}

// ログインフォーム。成功すると、ログイン前に開こうとした画面（なければ在庫）へ移る。
export default function LoginForm() {
  //
  return (
    <div>
      <Form<LoginInput> {...AuthController.login.form()} resetOnError={['password']}>
        {({ processing, errors }) => (
          <>
            {getGeneralErrorMessage(errors) !== null && (
              <p className="mb-4 rounded-md bg-[#fff2f2] px-3 py-2 text-sm text-[#f53003]">
                {getGeneralErrorMessage(errors)}
              </p>
            )}

            <div className="space-y-4">
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
                  パスワード
                </label>
                <input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  required
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-300 focus:outline-none"
                />
                <InputError message={errors.password} />
              </div>

              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  name="remember"
                  value="1"
                  defaultChecked
                  className="h-4 w-4 rounded border-gray-300"
                />
                次回から自動ログイン
              </label>

              <button
                type="submit"
                disabled={processing === true}
                className="w-full rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60"
              >
                {processing === true ? 'ログイン中…' : 'ログイン'}
              </button>
            </div>
          </>
        )}
      </Form>

      {/* 新規登録への導線 */}
      <div className="mt-6 text-center text-sm">
        <hr className="my-5 border-gray-200" />
        <p className="text-gray-600">アカウントをお持ちでない方</p>
        <Link
          href={register.url()}
          className="mt-3 inline-block rounded-md border border-gray-300 px-5 py-2 text-[#1b1b18] hover:border-black"
        >
          新規登録
        </Link>
      </div>
    </div>
  )
}

// フォーム上部の総合メッセージ（入力欄のエラーがあるときだけ出す。無ければ null）
function getGeneralErrorMessage(errors: FormDataErrors<LoginInput>): string | null {
  //
  const inputErrorCount = getInputErrorList(errors).length
  if (inputErrorCount > 0) {
    // 認証に失敗したときはその文言、それ以外（入力漏れ等）は確認を促す
    if (errors.email === LOGIN_FAILED_MESSAGE) {
      return LOGIN_FAILED_MESSAGE
    } else {
      return '入力内容を確認してください。'
    }
  } else {
    return null
  }
}
