import { Head } from '@inertiajs/react'
import RegisterForm from '@/components/auth/register-form'

// 新規登録。ページは見出しとフォームを並べるだけ。
export default function Register() {
  return (
    <div>
      <Head title="新規登録">
        <meta
          head-key="description"
          name="description"
          content="アカウントを作成します。仕入・売却を記録すると、古物台帳（様式15号）が同時にできあがります。"
        />
      </Head>
      <div className="mb-8 text-center">
        <h1 className="text-2xl font-semibold text-[#1b1b18]">新規登録</h1>
        <p className="mt-1 text-sm text-gray-600">アカウントを作成して記録を始めましょう。</p>
      </div>

      <RegisterForm />
    </div>
  )
}
