import { execFileSync } from 'node:child_process'
import { type APIResponse, type Locator, type Page, expect } from '@playwright/test'
import { APP_ROOT, E2E_BASE_URL, E2E_ENV, PHP_BIN } from './env'

// 各 E2E を自己完結させるためのヘルパー。テストごとに新規ユーザーを作り、データを分離する。
// 画面の実装（Vue / React）に依存しないよう、見える文言・役割・ラベル構造だけで要素を探す。

export interface E2eUser {
  email: string
  password: string
  familyName: string
  givenName: string
}

// 1x1 の PNG（写真アップロード用。サーバーは中身の MIME を見るので本物の PNG にする）
export const PNG_FILE = {
  name: 'photo.png',
  mimeType: 'image/png',
  buffer: Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
    'base64',
  ),
}

export function uniqueEmail(): string {
  return `e2e_${Date.now()}_${Math.floor(Math.random() * 100000)}@example.com`
}

// 一意な品目名（同じユーザー内でも行を取り違えないように）
export function uniqueItemName(prefix: string): string {
  return `${prefix}-${Date.now().toString(36)}${Math.floor(Math.random() * 1000)}`
}

export function todayString(): string {
  const now = new Date()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${now.getFullYear()}-${month}-${day}`
}

// 新規登録してログイン状態にする（登録＝自動ログイン → 在庫画面）
export async function registerAndLogin(page: Page): Promise<E2eUser> {
  const user: E2eUser = {
    email: uniqueEmail(),
    password: 'password123',
    familyName: '結城',
    givenName: 'テスト',
  }

  await page.goto('/register')
  await page.locator('#family-name').fill(user.familyName)
  await page.locator('#given-name').fill(user.givenName)
  await page.locator('#email').fill(user.email)
  await page.locator('#password').fill(user.password)
  await page.getByRole('button', { name: '登録する' }).click()

  await expectInventory(page)
  return user
}

export async function login(page: Page, user: E2eUser): Promise<void> {
  await page.locator('#email').fill(user.email)
  await page.locator('#password').fill(user.password)
  await page.getByRole('button', { name: 'ログイン', exact: true }).click()
}

export async function logout(page: Page): Promise<void> {
  await openMenu(page, '設定')
  await page.getByRole('button', { name: 'ログアウト' }).click()
  await expect(page).toHaveURL(/\/login$/)
}

// 在庫（ホーム）が表示されるまで待つ
export async function expectInventory(page: Page): Promise<void> {
  await expect(page.getByRole('heading', { name: '在庫', exact: true })).toBeVisible()
}

// メニュー項目 → 遷移先の URL と見出し
const MENU_DESTINATION: Record<string, { url: RegExp; heading: string }> = {
  在庫: { url: /\/$/, heading: '在庫' },
  台帳: { url: /\/ledger$/, heading: '古物台帳' },
  設定: { url: /\/settings$/, heading: '設定' },
}

// ヘッダーのハンバーガーメニューから画面を開き、遷移が終わるまで待つ
export async function openMenu(page: Page, label: string): Promise<void> {
  const destination = MENU_DESTINATION[label]
  await expect(page.getByRole('menuitem')).toHaveCount(0)
  await page.getByRole('button', { name: 'メニュー' }).click()
  const item = page.getByRole('menuitem', { name: label, exact: true })
  await expect(item).toBeVisible()
  await item.click()
  await expect(page).toHaveURL(destination.url)
  await expect(
    page.getByRole('heading', { level: 1, name: destination.heading, exact: true }),
  ).toBeVisible()
  // 遷移したらメニューは閉じている（移行前の Vue 版は開いたまま残る不具合があった）
  await expect(page.getByRole('menuitem')).toHaveCount(0)
}

// 見出しラベルの直後にある入力欄を返す（<div><label>品目</label><input/></div> の構造を前提）
export function field(scope: Page | Locator, label: string): Locator {
  const page = 'page' in scope ? scope.page() : scope
  const labelLocator = page.locator('label', {
    hasText: new RegExp(`^\\s*${escapeRegExp(label)}\\s*$`),
  })
  return scope
    .locator('div')
    .filter({ has: labelLocator })
    .last()
    .locator('input, select, textarea')
    .first()
}

function escapeRegExp(text: string): string {
  return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

export function dialog(page: Page, title: string): Locator {
  return page.getByRole('dialog', { name: title })
}

// モーダルが開いている（見出しが見えている）ことを確かめる。
// ダイアログの外枠は実装によって大きさ 0 のことがあるので、見出しで判定する
export async function expectDialogOpen(modal: Locator): Promise<void> {
  await expect(modal.getByRole('heading').first()).toBeVisible()
}

// モーダルが閉じた（DOM から消えた）ことを確かめる
export async function expectDialogClosed(modal: Locator): Promise<void> {
  await expect(modal).toHaveCount(0)
}

// window.confirm を承認する（次の1回だけ）
export function acceptNextConfirm(page: Page): void {
  page.once('dialog', (dialogData) => {
    void dialogData.accept()
  })
}

// 仕入をモーダルから1件登録する（相手方は新規チップで作る）
export async function registerPurchase(
  page: Page,
  data: { itemName: string; price: number; counterpartyName?: string },
): Promise<void> {
  await page.getByRole('banner').getByRole('button', { name: '仕入' }).click()
  const modal = dialog(page, '仕入を登録')
  await expectDialogOpen(modal)

  await field(modal, '品目').fill(data.itemName)
  await field(modal, '仕入金額（円）').fill(String(data.price))
  await addCounterparty(page, modal, data.counterpartyName ?? '山田商店')
  await field(modal, '確認方法').selectOption({ label: '運転免許証の提示' })
  await modal.getByRole('button', { name: '保存して次へ' }).click()
  await expect(page.getByText('登録しました。続けて入力できます。')).toBeVisible()

  await modal.getByRole('button', { name: '閉じる' }).click()
  await expectDialogClosed(modal)
}

// 相手方チップの「＋新規」から相手方を追加する
export async function addCounterparty(page: Page, scope: Locator, name: string): Promise<void> {
  await scope.getByRole('button', { name: '新規' }).click()
  const popover = dialog(page, '相手方を追加')
  await expectDialogOpen(popover)
  await field(popover, '氏名・店舗名').fill(name)
  await field(popover, '住所').fill('兵庫県神戸市中央区1-1')
  await field(popover, '職業').fill('会社員')
  await field(popover, '年齢').fill('40')
  await popover.getByRole('button', { name: 'この相手方を使う' }).click()
  await expectDialogClosed(popover)
}

// 在庫一覧（PC の表）の行
export function inventoryRow(page: Page, itemName: string): Locator {
  return page.getByRole('row').filter({ hasText: itemName })
}

// 在庫の「売れた」から売却を記録する
export async function sellPurchase(page: Page, itemName: string, salePrice: number): Promise<void> {
  await inventoryRow(page, itemName).getByRole('button', { name: '売れた' }).click()
  const modal = dialog(page, '売れた')
  await expectDialogOpen(modal)
  await field(modal, '売却金額（円）').fill(String(salePrice))
  await modal.getByRole('button', { name: '売却を記録する' }).click()
  await expectDialogClosed(modal)
}

// 画面のリンク先（PDF・CSV 等）をログイン中のまま取得する（ブラウザでリンクを押したときと同じく Referer を付ける）
export async function fetchLink(page: Page, href: string | null): Promise<APIResponse> {
  expect(href).not.toBeNull()
  return page.request.get(href ?? '', { headers: { Referer: `${E2E_BASE_URL}/` } })
}

// artisan tinker でコードを実行し、標準出力を返す（E2E 用の DB に対して）
export function tinker(code: string): string {
  const output = execFileSync(PHP_BIN, ['artisan', 'tinker', '--execute', code], {
    cwd: APP_ROOT,
    env: { ...process.env, ...E2E_ENV },
    encoding: 'utf8',
  })
  return output.trim()
}
