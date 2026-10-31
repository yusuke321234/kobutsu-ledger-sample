import { test, expect } from '@playwright/test'

// ゲスト（未ログイン）の挙動。

test('未ログインでホームにアクセスするとログイン画面へ誘導される', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveURL(/\/login/)
  await expect(page.getByRole('button', { name: 'ログイン', exact: true })).toBeVisible()
  // 新規登録への導線がある
  await expect(page.getByRole('link', { name: '新規登録' })).toBeVisible()
})

test('画面ごとにタイトルと説明が付き、画面を移ると入れ替わる', async ({ page }) => {
  const description = page.locator('meta[name="description"]')

  await page.goto('/login')
  await expect(page).toHaveTitle('ログイン | コブチョウ')
  await expect(description).toHaveCount(1)
  await expect(description).toHaveAttribute('content', /ログインします/)

  await page.getByRole('link', { name: '新規登録' }).click()
  await expect(page).toHaveTitle('新規登録 | コブチョウ')
  await expect(description).toHaveCount(1)
  await expect(description).toHaveAttribute('content', /アカウントを作成します/)
})

test('ログイン画面から新規登録画面へ移動できる', async ({ page }) => {
  await page.goto('/login')
  await page.getByRole('link', { name: '新規登録' }).click()
  await expect(page).toHaveURL(/\/register$/)
  await expect(page.getByRole('heading', { name: '新規登録' })).toBeVisible()
  await expect(page.getByRole('button', { name: '登録する' })).toBeEnabled()
})

test('存在しないページは 404 表示になる', async ({ page }) => {
  await page.goto('/no-such-page')
  await expect(page.getByText('ページが見つかりませんでした。')).toBeVisible()
})
