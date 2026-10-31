import type { ReactNode } from 'react'

interface SettingsSectionProps {
  title: string
  // 見出しの下に出す補足（例：営業所情報が PDF のどこに印字されるか）
  description?: string
  children: ReactNode
}

// 設定画面の1つのまとまり（見出し＋補足＋中身）。公式スターターキットの HeadingSmall にあたる。
export default function SettingsSection({ title, description, children }: SettingsSectionProps) {
  return (
    <section className="mt-6">
      <h2 className="mb-2 text-sm font-medium text-gray-700">{title}</h2>
      {description !== undefined && <p className="mb-2 text-xs text-gray-500">{description}</p>}
      {children}
    </section>
  )
}
