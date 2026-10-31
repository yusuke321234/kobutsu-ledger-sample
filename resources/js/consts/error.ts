// 画面へ返るエラー（form.errors）のうち、入力欄に属さないもののキー（ErrorConsts と一致）。

// 操作そのものが失敗した（権限なし・対象なし・サーバーエラー・ページの期限切れ）。
// 文言はトーストで出るので、入力欄のエラーとしては出さない。
export const REQUEST_ERROR_KEY = 'request'
