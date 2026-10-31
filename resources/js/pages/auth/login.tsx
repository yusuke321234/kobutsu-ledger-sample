import { Head } from '@inertiajs/react'
import LoginForm from '@/components/auth/login-form'

// ログイン。ページはロゴとフォームを並べるだけ（ロジックはコンポーネント側）。
export default function Login() {
  return (
    <div>
      <Head title="ログイン">
        <meta
          head-key="description"
          name="description"
          content="ログインします。仕入・売却を記録すると、古物台帳（様式15号）が同時にできあがります。"
        />
      </Head>
      <div className="mb-8 text-center">
        <h1 className="text-2xl font-semibold text-[#1b1b18]">コブチョウ</h1>
        <p className="mt-1 text-sm text-gray-600">古物商の台帳を、記録のついでに。</p>
      </div>

      <LoginForm />
    </div>
  )
}
