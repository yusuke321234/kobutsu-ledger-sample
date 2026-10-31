import { Head } from '@inertiajs/react'
import LedgerBoard, { type LedgerBoardProps } from '@/components/ledger/ledger-board'

// 古物台帳・出力。ページは見出しとボードを並べるだけ。
export default function Ledger(props: LedgerBoardProps) {
  return (
    <div>
      <Head title="古物台帳">
        <meta
          head-key="description"
          name="description"
          content="仕入・売却の記録から自動で作られる古物台帳です。期間を指定して、様式15号のPDF・CSVを出力できます。"
        />
      </Head>
      <h1 className="px-4 pt-6 text-xl font-semibold text-[#1b1b18]">古物台帳</h1>
      <LedgerBoard {...props} />
    </div>
  )
}

// 表が横に長いので本文幅を広く取る
Ledger.layout = { width: 'wide' }
