import { test, expect } from '@playwright/test'
import {
  addCounterparty,
  dialog,
  expectDialogClosed,
  expectDialogOpen,
  field,
  openMenu,
  registerAndLogin,
  uniqueItemName,
} from './support'

// スマホ表示（在庫・台帳はカード表示。クイック追加は出さず、登録ボタンからモーダル）。

test('スマホでは登録ボタンからモーダルで仕入を登録し、カードで「売れた」を押せる', async ({
  page,
}) => {
  await registerAndLogin(page)
  const itemName = uniqueItemName('スマホ')

  await expect(page.getByPlaceholder('品目（例：Nikon F3 ボディ）')).toBeHidden()
  await page.getByRole('button', { name: '仕入を登録' }).click()
  const modal = dialog(page, '仕入を登録')
  await field(modal, '品目').fill(itemName)
  await field(modal, '仕入金額（円）').fill('3000')
  await addCounterparty(page, modal, '現場の人')
  await field(modal, '確認方法').selectOption({ label: '運転免許証の提示' })
  await modal.getByRole('button', { name: '保存して次へ' }).click()
  // トーストは保存ボタンに重なって出る。タップするとトーストだけ閉じ、下のボタンは押されずモーダルも残る
  const toast = page.getByRole('button', { name: '登録しました。続けて入力できます。' })
  await expect(toast).toBeVisible()
  await toast.click()
  await expect(toast).toHaveCount(0)
  await expectDialogOpen(modal)
  await expect(field(modal, '品目')).toHaveValue('')
  await modal.getByRole('button', { name: '閉じる' }).click()
  await expectDialogClosed(modal)

  // 表は出さずカードで出す
  await expect(page.getByRole('table')).toBeHidden()
  const card = page.getByRole('listitem').filter({ hasText: itemName })
  await expect(card).toContainText('在庫中')
  await expect(card).toContainText('¥3,000（×1）')

  await card.getByRole('button', { name: '売れた' }).click()
  const saleModal = dialog(page, '売れた')
  await field(saleModal, '売却金額（円）').fill('4500')
  await saleModal.getByRole('button', { name: '売却を記録する' }).click()
  await expect(page.getByText('売却を記録しました。粗利 ¥1,500')).toBeVisible()
  await expectDialogClosed(saleModal)

  await page.getByRole('button', { name: '売却済', exact: true }).click()
  await expect(page.getByRole('listitem').filter({ hasText: itemName })).toContainText(
    /粗利\s*¥1,500/,
  )

  // 台帳もカード
  await openMenu(page, '台帳')
  await expect(page.getByRole('listitem').filter({ hasText: itemName })).toHaveCount(2)
})
