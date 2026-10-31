import { test, expect } from '@playwright/test'
import {
  field,
  openMenu,
  registerAndLogin,
  registerPurchase,
  sellPurchase,
  todayString,
  uniqueItemName,
  fetchLink,
} from './support'

// 古物台帳（仕入・売却から自動生成。期間・検索・様式15号PDF/CSV）。

test('仕入と売却が、受入・払出の行として台帳に出る', async ({ page }) => {
  await registerAndLogin(page)
  const soldItem = uniqueItemName('台帳カメラ')
  const stockItem = uniqueItemName('台帳レンズ')
  await registerPurchase(page, { itemName: soldItem, price: 20000, counterpartyName: '田中' })
  await registerPurchase(page, { itemName: stockItem, price: 7000, counterpartyName: '中村' })
  await sellPurchase(page, soldItem, 26000)

  await openMenu(page, '台帳')
  await expect(page.getByText('仕入・売却の記録から自動生成')).toBeVisible()
  await expect(page.getByText('3 件', { exact: true })).toBeVisible()

  const receiveRow = page.getByRole('row').filter({ hasText: soldItem }).filter({ hasText: '受入' })
  await expect(receiveRow).toContainText('受入（買受け）')
  await expect(receiveRow).toContainText('田中')
  await expect(receiveRow).toContainText('兵庫県神戸市中央区1-1・会社員・40歳')
  await expect(receiveRow).toContainText('運転免許証の提示')
  await expect(receiveRow).toContainText('¥20,000')
  await expect(receiveRow).toContainText(todayString())

  const payoutRow = page.getByRole('row').filter({ hasText: soldItem }).filter({ hasText: '払出' })
  await expect(payoutRow).toContainText('払出（メルカリ）')
  await expect(payoutRow).toContainText('¥26,000')

  await expect(page.getByRole('row').filter({ hasText: stockItem })).toHaveCount(1)
})

test('期間プリセット・任意期間・検索で絞り込める', async ({ page }) => {
  await registerAndLogin(page)
  const firstItem = uniqueItemName('絞込A')
  const secondItem = uniqueItemName('絞込B')
  await registerPurchase(page, { itemName: firstItem, price: 1000 })
  await registerPurchase(page, { itemName: secondItem, price: 2000 })
  await openMenu(page, '台帳')
  await expect(page.getByText('2 件', { exact: true })).toBeVisible()

  // 去年には無い
  await page.getByRole('button', { name: '去年', exact: true }).click()
  await expect(page.getByText('0 件', { exact: true })).toBeVisible()
  await expect(page.getByText('この期間の記録はありません。')).toBeVisible()

  // 今月・今年には有る
  await page.getByRole('button', { name: '今月', exact: true }).click()
  await expect(page.getByText('2 件', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: '今年', exact: true }).click()
  await expect(page.getByText('2 件', { exact: true })).toBeVisible()

  // 日付を直接入れると任意期間
  await page.getByLabel('期間開始').fill('2000-01-01')
  await page.getByLabel('期間開始').dispatchEvent('change')
  await expect(page.getByText('任意期間')).toBeVisible()
  await page.getByLabel('期間終了').fill('2000-12-31')
  await page.getByLabel('期間終了').dispatchEvent('change')
  await expect(page.getByText('0 件', { exact: true })).toBeVisible()

  await page.getByRole('button', { name: 'すべて', exact: true }).click()
  await expect(page.getByText('2 件', { exact: true })).toBeVisible()

  // 検索
  await page.getByPlaceholder('品目・特徴・相手方で検索').fill(firstItem)
  await expect(page.getByText('1 件', { exact: true })).toBeVisible()
  await expect(page.getByRole('row').filter({ hasText: firstItem })).toHaveCount(1)
  await expect(page.getByRole('row').filter({ hasText: secondItem })).toHaveCount(0)
})

test('様式15号PDFとCSVを、画面の絞り込みのまま出力できる', async ({ page }) => {
  await registerAndLogin(page)
  const itemName = uniqueItemName('出力')
  await registerPurchase(page, { itemName, price: 4000 })
  await openMenu(page, '台帳')

  await page.getByRole('button', { name: '今年', exact: true }).click()
  await expect(page.getByText('1 件', { exact: true })).toBeVisible()
  const year = new Date().getFullYear()

  const pdfLink = page.getByRole('link', { name: '様式15号PDFを開く' })
  await expect(pdfLink).toHaveAttribute('target', '_blank')
  const pdfHref = await pdfLink.getAttribute('href')
  expect(pdfHref).toContain(`from=${year}-01-01`)
  expect(pdfHref).toContain(`to=${year}-12-31`)
  const pdfResponse = await fetchLink(page, pdfHref)
  expect(pdfResponse.status()).toBe(200)
  expect(pdfResponse.headers()['content-type']).toContain('application/pdf')
  expect(pdfResponse.headers()['content-disposition']).toContain('inline')

  const csvHref = await page.getByRole('link', { name: 'CSVをダウンロード' }).getAttribute('href')
  const csvResponse = await fetchLink(page, csvHref)
  expect(csvResponse.status()).toBe(200)
  expect(csvResponse.headers()['content-type']).toContain('text/csv')
  expect(await csvResponse.text()).toContain(itemName)

  // 検索語も引き継ぐ
  await page.getByPlaceholder('品目・特徴・相手方で検索').fill('該当なしの語')
  await expect(page.getByText('0 件', { exact: true })).toBeVisible()
  const filteredCsvHref = await page
    .getByRole('link', { name: 'CSVをダウンロード' })
    .getAttribute('href')
  const filteredCsv = await fetchLink(page, filteredCsvHref)
  expect(await filteredCsv.text()).not.toContain(itemName)
})

test('他人の台帳は見えない', async ({ page, context }) => {
  await registerAndLogin(page)
  const itemName = uniqueItemName('他人')
  await registerPurchase(page, { itemName, price: 4000 })
  await context.clearCookies()

  await registerAndLogin(page)
  await openMenu(page, '台帳')
  await expect(page.getByText('0 件', { exact: true })).toBeVisible()
  const csvHref = await page.getByRole('link', { name: 'CSVをダウンロード' }).getAttribute('href')
  const csv = await fetchLink(page, csvHref)
  expect(await csv.text()).not.toContain(itemName)
})

test('営業所情報が未設定なら台帳に案内が出て、設定へ移動でき、設定すると消える', async ({
  page,
}) => {
  await registerAndLogin(page)
  await openMenu(page, '台帳')

  // 未設定のうちは、PDF の見出しに印字されることを案内する
  await expect(page.getByText('営業所名・許可番号が未設定です。')).toBeVisible()
  await page.getByRole('link', { name: '設定する', exact: true }).click()
  await expect(page.getByRole('heading', { name: '設定' })).toBeVisible()
  await expect(page.getByRole('heading', { name: '営業所情報' })).toBeVisible()

  // 営業所名だけ入れると、許可番号だけが未設定と案内する
  await page.getByRole('button', { name: '変更' }).nth(1).click()
  await field(page, '営業所名').fill('コブチョウ商店')
  await page.getByRole('button', { name: '保存', exact: true }).click()
  await expect(page.getByText('営業所情報を保存しました。')).toBeVisible()
  await openMenu(page, '台帳')
  await expect(page.getByText('許可番号が未設定です。')).toBeVisible()
  await expect(page.getByText('営業所名・許可番号が未設定です。')).toHaveCount(0)

  // 両方そろうと案内は出ない
  await openMenu(page, '設定')
  await page.getByRole('button', { name: '変更' }).nth(1).click()
  await field(page, '許可番号').fill('兵庫県公安委員会 第123456789012号')
  await page.getByRole('button', { name: '保存', exact: true }).click()
  await expect(page.getByText('営業所情報を保存しました。')).toBeVisible()
  await openMenu(page, '台帳')
  await expect(page.getByText('が未設定です。')).toHaveCount(0)
})
