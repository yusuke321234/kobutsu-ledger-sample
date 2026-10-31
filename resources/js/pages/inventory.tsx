import { Head } from '@inertiajs/react'
import InventoryBoard, { type InventoryBoardProps } from '@/components/purchase/inventory-board'

// 在庫（ホーム）。ページは見出しとボードを並べるだけ。
export default function Inventory(props: InventoryBoardProps) {
  return (
    <div>
      <Head title="在庫">
        <meta
          head-key="description"
          name="description"
          content="仕入の記録と在庫の一覧です。売れた商品は売却を記録すると、古物台帳の払出と粗利に反映されます。"
        />
      </Head>
      <h1 className="px-4 pt-6 text-xl font-semibold text-[#1b1b18]">在庫</h1>
      <InventoryBoard {...props} />
    </div>
  )
}

// 一覧系なので本文幅を広く取る
Inventory.layout = { width: 'wide' }
