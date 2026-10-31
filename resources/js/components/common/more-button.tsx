interface MoreButtonProps {
  loading: boolean
  hasMore: boolean
  onLoad: () => void
}

// もっと見る（在庫・台帳の一覧で共用）。続きがある時だけ出す。
export default function MoreButton({ loading, hasMore, onLoad }: MoreButtonProps) {
  if (hasMore === true) {
    // ok.
  } else {
    return null
  }

  return (
    <div className="mt-6 text-center">
      <button
        type="button"
        disabled={loading === true}
        className="rounded-md border border-gray-300 px-5 py-2 text-sm text-[#1b1b18] hover:border-black disabled:cursor-not-allowed disabled:opacity-60"
        onClick={onLoad}
      >
        {loading === true ? '読み込み中…' : 'もっと見る'}
      </button>
    </div>
  )
}
