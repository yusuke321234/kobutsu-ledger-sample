import { router } from '@inertiajs/react'
import { useState } from 'react'
import AuthController from '@/actions/App/Http/Controllers/Auth/AuthController'

// ログアウト（見出しは付けず、ほかのまとまりより少し離して置く）
export default function LogoutSection() {
  //
  const [loggingOut, setLoggingOut] = useState(false)

  //
  function logout(): void {
    router.post(
      AuthController.logout.url(),
      {},
      {
        onStart: () => setLoggingOut(true),
        onFinish: () => setLoggingOut(false),
      },
    )
  }

  //
  return (
    <section className="mt-8">
      <button
        type="button"
        disabled={loggingOut === true}
        className="w-full rounded-md border border-gray-300 px-4 py-2 text-sm text-[#1b1b18] hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
        onClick={logout}
      >
        {loggingOut === true ? 'ログアウト中…' : 'ログアウト'}
      </button>
    </section>
  )
}
