import { test, expect } from '@playwright/test'
import { field, openMenu, registerAndLogin, tinker } from './support'

// 設定（氏名・営業所情報）。

test('氏名を変更できる（キャンセルもできる）', async ({ page }) => {
  await registerAndLogin(page)
  await openMenu(page, '設定')
  await expect(page.getByText('結城 テスト')).toBeVisible()

  // キャンセル
  await page.getByRole('button', { name: '変更' }).first().click()
  await page.getByPlaceholder('姓').fill('キャンセル')
  await page.getByRole('button', { name: 'キャンセル' }).click()
  await expect(page.getByText('結城 テスト')).toBeVisible()

  // 保存
  await page.getByRole('button', { name: '変更' }).first().click()
  await expect(page.getByPlaceholder('姓')).toHaveValue('結城')
  await page.getByPlaceholder('姓').fill('古物')
  await page.getByPlaceholder('名').fill('太郎')
  await page.getByRole('button', { name: '保存', exact: true }).click()
  await expect(page.getByText('氏名を変更しました。')).toBeVisible()
  await expect(page.getByText('古物 太郎')).toBeVisible()

  // 読み込み直しても変わっている
  await page.reload()
  await expect(page.getByText('古物 太郎')).toBeVisible()
})

test('氏名を空にすると保存できない', async ({ page }) => {
  await registerAndLogin(page)
  await openMenu(page, '設定')
  await page.getByRole('button', { name: '変更' }).first().click()
  await page.getByPlaceholder('姓').fill(' ')
  await page.getByRole('button', { name: '保存', exact: true }).click()
  await expect(page.getByText('The family name field is required.')).toBeVisible()
})

test('営業所情報を登録でき、氏名は変わらない', async ({ page }) => {
  const user = await registerAndLogin(page)
  await openMenu(page, '設定')
  await expect(page.getByText('未設定')).toHaveCount(2)

  await page.getByRole('button', { name: '変更' }).nth(1).click()
  // 許可証のどこを見て入れるかの案内（営業所名は許可証に載っていない）
  await expect(page.getByText('許可証には載っていません。')).toBeVisible()
  await expect(page.getByText('許可証の表紙の内側にある')).toBeVisible()
  await field(page, '営業所名').fill('コブチョウ商店')
  await field(page, '許可番号').fill('兵庫県公安委員会 第123456789012号')
  await page.getByRole('button', { name: '保存', exact: true }).click()
  await expect(page.getByText('営業所情報を保存しました。')).toBeVisible()
  await expect(page.getByText('コブチョウ商店')).toBeVisible()
  await expect(page.getByText('兵庫県公安委員会 第123456789012号')).toBeVisible()
  await expect(page.getByText('結城 テスト')).toBeVisible()

  const saved = tinker(
    `$u = App\\Models\\User::where('email', '${user.email}')->first(); echo $u->office_name . '|' . $u->license_number . '|' . $u->family_name;`,
  )
  expect(saved).toBe('コブチョウ商店|兵庫県公安委員会 第123456789012号|結城')

  // 空にすると未設定に戻る
  await page.getByRole('button', { name: '変更' }).nth(1).click()
  await field(page, '営業所名').fill('')
  await field(page, '許可番号').fill('')
  await page.getByRole('button', { name: '保存', exact: true }).click()
  await expect(page.getByText('営業所情報を保存しました。')).toBeVisible()
  await expect(page.getByText('未設定')).toHaveCount(2)
})
