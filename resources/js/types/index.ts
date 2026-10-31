// 画面が受け取るデータの型（Laravel の Service が組み立てる配列と 1:1）。
// データの中身なので、キーはサーバーと同じ snake_case のまま届く（変換しない。props の名前は camelCase。docs/conventions/10-api-conventions.md）。
// ID・区分コードは string、金額・数量は number で扱う（docs/conventions/02-naming-conventions.md）。
//
// 型の中身はドメインごとのファイルに置き、ここは再エクスポートだけにする（import 元は '@/types' で統一）。

export type * from './auth'
export type * from './ledger'
export type * from './purchase'
