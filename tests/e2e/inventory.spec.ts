import { test, expect } from '@playwright/test'
import {
  PNG_FILE,
  acceptNextConfirm,
  addCounterparty,
  dialog,
  field,
  inventoryRow,
  registerAndLogin,
  registerPurchase,
  sellPurchase,
  tinker,
  uniqueItemName,
  expectDialogClosed,
  expectDialogOpen,
} from './support'

// 在庫（仕入の登録・編集・下書き・売却・削除・検索）。PC 表示。

test.beforeEach(async ({ page }) => {
  await registerAndLogin(page)
})

test('最初は空の案内が出る', async ({ page }) => {
  await expect(page.getByText('まだ登録がありません。')).toBeVisible()
  await expect(page.getByText('0 件', { exact: true })).toBeVisible()
})

test('絞り込み・検索で0件なら、条件に当てはまらないと案内する', async ({ page }) => {
  const itemName = uniqueItemName('売却のみ')
  await registerPurchase(page, { itemName, price: 3000 })
  await sellPurchase(page, itemName, 5000)

  // 在庫中は0件だが、仕入はあるので「まだ登録がありません」とは出さない
  await expect(page.getByText('この条件に当てはまる仕入はありません。')).toBeVisible()
  await expect(page.getByText('まだ登録がありません。')).toHaveCount(0)

  // 検索で0件のときも同じ
  await page.getByRole('button', { name: 'すべて', exact: true }).click()
  await expect(inventoryRow(page, itemName)).toHaveCount(1)
  await page.getByPlaceholder('品目・特徴・相手方で検索').fill('当てはまらない検索語')
  await expect(page.getByText('この条件に当てはまる仕入はありません。')).toBeVisible()
  await expect(inventoryRow(page, itemName)).toHaveCount(0)
})

test('仕入をモーダルで連続登録できる（相手方・確認方法は次の1件へ引き継ぐ）', async ({ page }) => {
  const firstItem = uniqueItemName('カメラ')
  const secondItem = uniqueItemName('レンズ')

  await page.getByRole('banner').getByRole('button', { name: '仕入' }).click()
  const modal = dialog(page, '仕入を登録')
  await expectDialogOpen(modal)

  await field(modal, '品目').fill(firstItem)
  await field(modal, '仕入金額（円）').fill('12000')
  await field(modal, '特徴（型番・シリアル・状態）').fill('シリアル 123456')
  await addCounterparty(page, modal, '佐藤商店')
  await expect(modal.getByText('佐藤商店').first()).toBeVisible()
  await field(modal, '確認方法').selectOption({ label: '運転免許証の提示' })
  await modal.getByRole('button', { name: '保存して次へ' }).click()
  await expect(page.getByText('登録しました。続けて入力できます。')).toBeVisible()

  // 品目・金額は空に戻り、相手方・確認方法は残る
  await expect(field(modal, '品目')).toHaveValue('')
  await expect(field(modal, '仕入金額（円）')).toHaveValue('')
  await expect(field(modal, '確認方法')).toHaveValue('1')
  await expect(modal.getByText('佐藤商店').first()).toBeVisible()

  // 2件目
  await field(modal, '品目').fill(secondItem)
  await field(modal, '仕入金額（円）').fill('8000')
  await modal.getByRole('button', { name: '保存して次へ' }).click()
  await expect(field(modal, '品目')).toHaveValue('')

  await modal.getByRole('button', { name: '閉じる' }).click()
  await expectDialogClosed(modal)

  // 一覧に2件（在庫中）
  await expect(page.getByText('2 件', { exact: true })).toBeVisible()
  const firstRow = inventoryRow(page, firstItem)
  await expect(firstRow).toContainText('¥12,000')
  await expect(firstRow).toContainText('佐藤商店')
  await expect(firstRow).toContainText('在庫中')
  await expect(firstRow).toContainText('買受け')
  await expect(inventoryRow(page, secondItem)).toContainText('¥8,000')
})

test('必須項目が足りないと保存できず、エラーが出る', async ({ page }) => {
  await page.getByRole('banner').getByRole('button', { name: '仕入' }).click()
  const modal = dialog(page, '仕入を登録')
  await modal.getByRole('button', { name: '保存して次へ' }).click()

  await expect(modal.getByText('The item name field is required.')).toBeVisible()
  await expect(modal.getByText('The price field is required.')).toBeVisible()
  await expect(modal.getByText('The counterparty name field is required.')).toBeVisible()
  await expect(modal.getByText('The verification method field is required.')).toBeVisible()
  await expectDialogOpen(modal)
})

test('下書き保存して、あとから完成させられる', async ({ page }) => {
  const itemName = uniqueItemName('ギター')

  await page.getByRole('banner').getByRole('button', { name: '仕入' }).click()
  const modal = dialog(page, '仕入を登録')
  await field(modal, '仕入金額（円）').fill('30000')
  await modal.getByRole('button', { name: '下書き保存' }).click()
  await expect(page.getByText('下書きを保存しました。')).toBeVisible()
  await modal.getByRole('button', { name: '閉じる' }).click()

  // 状態フィルタ「下書き」で見える
  await page.getByRole('button', { name: '下書き', exact: true }).click()
  const draftRow = page.getByRole('row').filter({ hasText: '（未入力）' })
  await expect(draftRow).toHaveCount(1)
  await expect(draftRow).toContainText('下書き')

  // 完成させる
  await draftRow.getByRole('button', { name: '完成させる' }).click()
  const editModal = dialog(page, '仕入を編集')
  await expectDialogOpen(editModal)
  await expect(field(editModal, '仕入金額（円）')).toHaveValue('30000')
  await field(editModal, '品目').fill(itemName)
  await addCounterparty(page, editModal, '鈴木')
  await field(editModal, '確認方法').selectOption({ label: 'マイナンバーカードの提示' })
  await editModal.getByRole('button', { name: '保存する' }).click()
  await expect(page.getByText('保存しました。')).toBeVisible()
  await expectDialogClosed(editModal)

  // 下書きから消え、在庫中に出る
  await expect(page.getByRole('row').filter({ hasText: '（未入力）' })).toHaveCount(0)
  await page.getByRole('button', { name: '在庫中', exact: true }).click()
  await expect(inventoryRow(page, itemName)).toContainText('在庫中')
})

test('登録済みの仕入を編集できる', async ({ page }) => {
  const itemName = uniqueItemName('時計')
  await registerPurchase(page, { itemName, price: 5000 })

  await inventoryRow(page, itemName).getByRole('button', { name: '編集' }).click()
  const modal = dialog(page, '仕入を編集')
  await expect(field(modal, '品目')).toHaveValue(itemName)
  await field(modal, '仕入金額（円）').fill('5500')
  await field(modal, '数量').fill('2')
  await field(modal, '区分').selectOption({ label: '委託' })
  await modal.getByRole('button', { name: '保存する' }).click()
  await expectDialogClosed(modal)

  const row = inventoryRow(page, itemName)
  await expect(row).toContainText('¥5,500')
  await expect(row).toContainText('委託')
})

test('編集中に仕入が消えていたら、理由を伝えて入力を残したままにする', async ({ page }) => {
  const itemName = uniqueItemName('消えた')
  await registerPurchase(page, { itemName, price: 5000 })

  await inventoryRow(page, itemName).getByRole('button', { name: '編集' }).click()
  const modal = dialog(page, '仕入を編集')
  await field(modal, '仕入金額（円）').fill('5500')
  // 別の端末で削除された
  tinker(`App\\Models\\Purchase::where('item_name', '${itemName}')->delete();`)
  await modal.getByRole('button', { name: '保存する' }).click()

  // 失敗は成功扱いにしない＝モーダルは閉じず、入力も残る
  const errorToast = page.getByRole('button', { name: '対象の仕入が見つかりません。' })
  await expect(errorToast).toBeVisible()
  // 失敗のトーストは赤（完了の墨色と見分けられる）
  await expect(errorToast).toHaveCSS('background-color', 'rgb(245, 48, 3)')
  await expectDialogOpen(modal)
  await expect(field(modal, '仕入金額（円）')).toHaveValue('5500')
})

test('PC のクイック追加で、Enter で連続追加できる', async ({ page }) => {
  const firstItem = uniqueItemName('本')
  const secondItem = uniqueItemName('雑誌')
  const quickAdd = page
    .locator('div')
    .filter({ has: page.getByPlaceholder('品目（例：Nikon F3 ボディ）') })
    .filter({ hasText: '取引日' })
    .last()

  await addCounterparty(page, quickAdd, '古書店')
  await field(quickAdd, '確認方法').selectOption({ label: '古物商・店舗からの仕入' })

  const itemInput = quickAdd.getByPlaceholder('品目（例：Nikon F3 ボディ）')
  await itemInput.fill(firstItem)
  await quickAdd.getByLabel('仕入金額').fill('1500')
  await itemInput.press('Enter')
  await expect(page.getByText('追加しました。')).toBeVisible()
  await expect(inventoryRow(page, firstItem)).toContainText('古書店')

  // 品目・金額は空になり、相手方は引き継がれる
  await expect(itemInput).toHaveValue('')
  await itemInput.fill(secondItem)
  await quickAdd.getByLabel('仕入金額').fill('700')
  await quickAdd.getByRole('button', { name: '追加', exact: true }).click()
  await expect(inventoryRow(page, secondItem)).toContainText('古書店')
  await expect(inventoryRow(page, secondItem)).toContainText('¥700')

  // 過去の相手方がチップとして出る（モーダル側にも）
  await page.getByRole('banner').getByRole('button', { name: '仕入' }).click()
  await expect(dialog(page, '仕入を登録').getByRole('button', { name: '古書店' })).toBeVisible()
})

test('クイック追加で必須項目が足りないとエラーが出る', async ({ page }) => {
  const itemInput = page.getByPlaceholder('品目（例：Nikon F3 ボディ）')
  await itemInput.fill(uniqueItemName('不足'))
  await itemInput.press('Enter')
  await expect(page.getByText(/field is required/).first()).toBeVisible()
  await expect(page.getByText('0 件', { exact: true })).toBeVisible()
})

test('「売れた」で売却を記録すると粗利が出て、取り消すと在庫に戻る', async ({ page }) => {
  const itemName = uniqueItemName('ゲーム機')
  await registerPurchase(page, { itemName, price: 10000 })

  await inventoryRow(page, itemName).getByRole('button', { name: '売れた' }).click()
  const modal = dialog(page, '売れた')
  await expect(modal).toContainText(itemName)
  await expect(modal).toContainText('仕入 ¥10,000')

  // 売価を入れた瞬間に粗利が出る
  await field(modal, '売却金額（円）').fill('13000')
  await expect(modal.getByText('¥3,000')).toBeVisible()
  await field(modal, '販路').selectOption({ label: 'ヤフオク' })
  await field(modal, '販売手数料（円）').fill('1300')
  await field(modal, '送料（円）').fill('800')
  await modal.getByRole('button', { name: '売却を記録する' }).click()
  await expect(page.getByText('売却を記録しました。粗利 ¥3,000')).toBeVisible()
  await expectDialogClosed(modal)

  // 在庫中から消え、売却済に出る
  await expect(inventoryRow(page, itemName)).toHaveCount(0)
  await page.getByRole('button', { name: '売却済', exact: true }).click()
  const soldRow = inventoryRow(page, itemName)
  await expect(soldRow).toContainText('売却済')
  await expect(soldRow).toContainText('ヤフオク ¥13,000')
  await expect(soldRow).toContainText('粗利 ¥3,000')
  await expect(soldRow.getByRole('button', { name: '売れた' })).toHaveCount(0)

  // 売却取消
  acceptNextConfirm(page)
  await soldRow.getByRole('button', { name: '売却取消' }).click()
  await expect(page.getByText('売却を取り消しました。')).toBeVisible()
  await expect(inventoryRow(page, itemName)).toHaveCount(0)
  await page.getByRole('button', { name: '在庫中', exact: true }).click()
  await expect(inventoryRow(page, itemName)).toContainText('在庫中')
})

test('売却済みの仕入は削除できず、売却を取り消すと削除できる', async ({ page }) => {
  const itemName = uniqueItemName('売却済削除')
  await registerPurchase(page, { itemName, price: 3000 })
  await sellPurchase(page, itemName, 5000)

  // 売却済みの行には「削除」が出ない（売却取消だけ）
  await page.getByRole('button', { name: '売却済', exact: true }).click()
  const soldRow = inventoryRow(page, itemName)
  await expect(soldRow.getByRole('button', { name: '売却取消' })).toBeVisible()
  await expect(soldRow.getByRole('button', { name: '削除' })).toHaveCount(0)

  // 売却を取り消すと、在庫中の行で削除できる
  acceptNextConfirm(page)
  await soldRow.getByRole('button', { name: '売却取消' }).click()
  await expect(page.getByText('売却を取り消しました。')).toBeVisible()
  await page.getByRole('button', { name: '在庫中', exact: true }).click()
  acceptNextConfirm(page)
  await inventoryRow(page, itemName).getByRole('button', { name: '削除' }).click()
  await expect(page.getByText('削除しました。')).toBeVisible()
  await expect(inventoryRow(page, itemName)).toHaveCount(0)
})

test('売価が赤字なら粗利がマイナスで出る', async ({ page }) => {
  const itemName = uniqueItemName('赤字')
  await registerPurchase(page, { itemName, price: 5000 })
  await sellPurchase(page, itemName, 4000)
  await expect(page.getByText('売却を記録しました。粗利 ¥-1,000')).toBeVisible()
})

test('仕入を削除できる', async ({ page }) => {
  const itemName = uniqueItemName('削除')
  await registerPurchase(page, { itemName, price: 3000 })

  // キャンセルすると消えない
  page.once('dialog', (dialogData) => {
    void dialogData.dismiss()
  })
  await inventoryRow(page, itemName).getByRole('button', { name: '削除' }).click()
  await expect(inventoryRow(page, itemName)).toHaveCount(1)

  acceptNextConfirm(page)
  await inventoryRow(page, itemName).getByRole('button', { name: '削除' }).click()
  await expect(page.getByText('削除しました。')).toBeVisible()
  await expect(inventoryRow(page, itemName)).toHaveCount(0)
  await page.getByRole('button', { name: 'すべて', exact: true }).click()
  await expect(inventoryRow(page, itemName)).toHaveCount(0)
})

test('品目・相手方で検索できる', async ({ page }) => {
  const cameraItem = uniqueItemName('検索カメラ')
  const bookItem = uniqueItemName('検索ブック')
  await registerPurchase(page, { itemName: cameraItem, price: 1000, counterpartyName: '青木' })
  await registerPurchase(page, { itemName: bookItem, price: 2000, counterpartyName: '赤井' })
  await expect(page.getByText('2 件', { exact: true })).toBeVisible()

  const search = page.getByPlaceholder('品目・特徴・相手方で検索')
  await search.fill('検索カメラ')
  await expect(page.getByText('1 件', { exact: true })).toBeVisible()
  await expect(inventoryRow(page, cameraItem)).toHaveCount(1)
  await expect(inventoryRow(page, bookItem)).toHaveCount(0)

  await search.fill('赤井')
  await expect(inventoryRow(page, bookItem)).toHaveCount(1)
  await expect(inventoryRow(page, cameraItem)).toHaveCount(0)

  await search.fill('')
  await expect(page.getByText('2 件', { exact: true })).toBeVisible()
})

test('20件を超えると「もっと見る」で続きを読み込める', async ({ page }) => {
  const prefix = uniqueItemName('まとめ')
  const itemInput = page.getByPlaceholder('品目（例：Nikon F3 ボディ）')
  const quickAdd = page
    .locator('div')
    .filter({ has: itemInput })
    .filter({ hasText: '取引日' })
    .last()
  await field(quickAdd, '確認方法').selectOption({ label: '確認免除（1万円未満等）' })

  for (let i = 1; i <= 21; i++) {
    await itemInput.fill(`${prefix}-${i}`)
    await quickAdd.getByLabel('仕入金額').fill('100')
    await itemInput.press('Enter')
    await expect(itemInput).toHaveValue('')
  }

  await expect(page.getByText('21 件', { exact: true })).toBeVisible()
  await expect(page.getByRole('row').filter({ hasText: prefix })).toHaveCount(20)
  await page.getByRole('button', { name: 'もっと見る' }).click()
  await expect(page.getByRole('row').filter({ hasText: prefix })).toHaveCount(21)
  await expect(page.getByRole('button', { name: 'もっと見る' })).toHaveCount(0)
})

test('免除にすると例外品目の一覧が出て、該当しそうな品目は警告される', async ({ page }) => {
  await page.getByRole('banner').getByRole('button', { name: '仕入' }).click()
  const modal = dialog(page, '仕入を登録')

  await field(modal, '仕入金額（円）').fill('500')
  await expect(
    modal.getByText('1万円未満の買受は確認・記帳が任意です', { exact: false }),
  ).toBeVisible()

  await modal.getByRole('checkbox', { name: '1万円未満などで確認・記帳を免除する' }).check()
  await expect(modal.getByText('次の例外品目は、1万円未満でも記帳・確認が必要です')).toBeVisible()
  await expect(field(modal, '確認方法')).toBeDisabled()
  await expect(field(modal, '確認方法')).toHaveValue('9')

  await field(modal, '品目').fill('ゲームソフト マリオ')
  await expect(modal.getByText('例外品目（ゲームソフト）の可能性があります')).toBeVisible()

  // 免除なら相手方なしでも保存できる
  await modal.getByRole('button', { name: '保存して次へ' }).click()
  await expect(page.getByText('登録しました。続けて入力できます。')).toBeVisible()

  // 免除を外すと確認方法は選び直し
  await modal.getByRole('checkbox', { name: '1万円未満などで確認・記帳を免除する' }).uncheck()
  await expect(field(modal, '確認方法')).toBeEnabled()
  await expect(field(modal, '確認方法')).toHaveValue('')
})

test('写真を付けて登録でき、あとから足したり消したりできる', async ({ page }) => {
  const itemName = uniqueItemName('写真')

  await page.getByRole('banner').getByRole('button', { name: '仕入' }).click()
  const modal = dialog(page, '仕入を登録')
  await field(modal, '写真').setInputFiles(PNG_FILE)
  await expect(modal.getByRole('img', { name: '選択した写真' })).toBeVisible()
  await field(modal, '品目').fill(itemName)
  await field(modal, '仕入金額（円）').fill('2000')
  await addCounterparty(page, modal, '写真の人')
  await field(modal, '確認方法').selectOption({ label: '運転免許証の提示' })
  await modal.getByRole('button', { name: '保存して次へ' }).click()
  await expect(page.getByText('登録しました。続けて入力できます。')).toBeVisible()
  // 連続登録なので選択中の写真は消える
  await expect(modal.getByRole('img', { name: '選択した写真' })).toHaveCount(0)
  await modal.getByRole('button', { name: '閉じる' }).click()

  // 編集を開くと保存済みの写真が出る（認証付きで配信される）
  await inventoryRow(page, itemName).getByRole('button', { name: '編集' }).click()
  const editModal = dialog(page, '仕入を編集')
  const savedPhoto = editModal.getByRole('img', { name: '仕入の写真' })
  await expect(savedPhoto).toHaveCount(1)
  await expect.poll(() => savedPhoto.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBe(1)

  // 2枚目を足せる
  await field(editModal, '写真').setInputFiles(PNG_FILE)
  await editModal.getByRole('button', { name: '保存する' }).click()
  await expect(page.getByText('保存しました。')).toBeVisible()

  // 写真は2枚になる。削除できる
  await inventoryRow(page, itemName).getByRole('button', { name: '編集' }).click()
  const againModal = dialog(page, '仕入を編集')
  await expect(againModal.getByRole('img', { name: '仕入の写真' })).toHaveCount(2)
  await againModal.getByRole('button', { name: '写真を削除' }).first().click()
  await expect(againModal.getByRole('img', { name: '仕入の写真' })).toHaveCount(1)
})
