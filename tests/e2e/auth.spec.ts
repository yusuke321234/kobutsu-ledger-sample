import { test, expect, type Page } from '@playwright/test'
import {
  dialog,
  expectInventory,
  field,
  login,
  logout,
  openMenu,
  registerAndLogin,
  tinker,
  uniqueEmail,
  uniqueItemName,
} from './support'

// 認証（登録・ログイン・ログアウト・ガード）。

test('新規登録すると自動でログインし、在庫画面が開く', async ({ page }) => {
  await registerAndLogin(page)

  await expect(page).toHaveURL(/\/$/)
})

test('登録済みのメールアドレスでは登録できない', async ({ page, context }) => {
  const user = await registerAndLogin(page)
  await context.clearCookies()

  await page.goto('/register')
  await page.locator('#family-name').fill('重複')
  await page.locator('#given-name').fill('ユーザー')
  await page.locator('#email').fill(user.email)
  await page.locator('#password').fill('password123')
  await page.getByRole('button', { name: '登録する' }).click()

  await expect(page.getByText('The email has already been taken.')).toBeVisible()
  await expect(page).toHaveURL(/\/register/)
})

test('ログアウトしてから、同じアカウントでログインし直せる', async ({ page }) => {
  const user = await registerAndLogin(page)

  await logout(page)
  // ログアウト後はログイン必須の画面に入れない
  await page.goto('/ledger')
  await expect(page).toHaveURL(/\/login/)

  await login(page, user)
  // ログイン前に開こうとした画面へ戻る
  await expect(page).toHaveURL(/\/ledger$/)
  await expect(page.getByRole('heading', { name: '古物台帳' })).toBeVisible()
})

test('パスワードが違うとログインできない', async ({ page }) => {
  const user = await registerAndLogin(page)
  await logout(page)

  await login(page, { ...user, password: 'wrong-password' })
  await expect(page.getByText('メールアドレスまたはパスワードが違います。').first()).toBeVisible()
  await expect(page).toHaveURL(/\/login/)
})

test('未登録のメールアドレスではログインできない', async ({ page }) => {
  await page.goto('/login')
  await login(page, {
    email: uniqueEmail(),
    password: 'password123',
    familyName: '',
    givenName: '',
  })
  await expect(page.getByText('メールアドレスまたはパスワードが違います。').first()).toBeVisible()
})

test('ログイン済みでログイン画面・登録画面を開くとホームへ戻る', async ({ page }) => {
  await registerAndLogin(page)

  await page.goto('/login')
  await expectInventory(page)
  await expect(page).toHaveURL(/\/$/)

  await page.goto('/register')
  await expectInventory(page)
})

test('ヘッダーのメニューで各画面へ移動できる', async ({ page }) => {
  await registerAndLogin(page)

  await openMenu(page, '台帳')
  await expect(page).toHaveURL(/\/ledger$/)
  await expect(page.getByRole('heading', { name: '古物台帳' })).toBeVisible()

  await openMenu(page, '設定')
  await expect(page).toHaveURL(/\/settings$/)
  await expect(page.getByRole('heading', { name: '設定' })).toBeVisible()

  await openMenu(page, '在庫')
  await expect(page).toHaveURL(/\/$/)
  await expectInventory(page)

  // ロゴでホームへ戻れる
  await openMenu(page, '台帳')
  await page.getByRole('banner').getByRole('link', { name: 'コブチョウ' }).click()
  await expectInventory(page)
})

test('ヘッダーのメニューからログアウトできる', async ({ page }) => {
  await registerAndLogin(page)

  // メニューの一番下（区切り線の下）にログアウトがある
  await page.getByRole('button', { name: 'メニュー' }).click()
  await page.getByRole('menuitem', { name: 'ログアウト', exact: true }).click()
  await expect(page).toHaveURL(/\/login$/)

  // ログアウト後はログインが必要な画面を開けない
  await page.goto('/ledger')
  await expect(page).toHaveURL(/\/login$/)
})

test('ヘッダーのメニューで、今いる画面の項目が太字になる', async ({ page }) => {
  await registerAndLogin(page)

  // 在庫（ホーム）は完全一致で判定する
  await expectActiveMenuItem(page, '在庫')

  // 台帳は配下も含めて判定する（絞り込みのクエリが付いていても台帳）。在庫は太字にしない
  await page.goto('/ledger?from=2026-01-01&to=2026-12-31')
  await expect(page.getByRole('heading', { level: 1, name: '古物台帳' })).toBeVisible()
  await expectActiveMenuItem(page, '台帳')

  await page.goto('/settings')
  await expect(page.getByRole('heading', { level: 1, name: '設定' })).toBeVisible()
  await expectActiveMenuItem(page, '設定')
})

test('画面を開いたままセッションが切れたら、保存されずにログイン画面へ移る', async ({
  page,
  context,
}) => {
  await registerAndLogin(page)
  const itemName = uniqueItemName('期限切れ')
  await page.getByRole('banner').getByRole('button', { name: '仕入' }).click()
  const modal = dialog(page, '仕入を登録')
  await field(modal, '品目').fill(itemName)
  await field(modal, '仕入金額（円）').fill('3000')

  // セッション切れ（Cookie が無い状態）で保存する
  await context.clearCookies()
  await modal.getByRole('button', { name: '保存して次へ' }).click()

  await expect(page).toHaveURL(/\/login$/)
  await expect(page.getByText('再度ログインしてください。')).toBeVisible()
  await expect(page.getByText('登録しました。続けて入力できます。')).toHaveCount(0)
  expect(tinker(`echo App\\Models\\Purchase::where('item_name', '${itemName}')->count();`)).toBe(
    '0',
  )
})

// メニューを開き、指定した項目だけが太字（今いる画面）になっていることを確かめて閉じる
async function expectActiveMenuItem(page: Page, activeLabel: string): Promise<void> {
  await page.getByRole('button', { name: 'メニュー' }).click()
  for (const label of ['在庫', '台帳', '設定']) {
    const item = page.getByRole('menuitem', { name: label, exact: true })
    if (label === activeLabel) {
      await expect(item).toHaveClass(/font-semibold/)
    } else {
      await expect(item).not.toHaveClass(/font-semibold/)
    }
  }
  await page.keyboard.press('Escape')
  await expect(page.getByRole('menuitem')).toHaveCount(0)
}
