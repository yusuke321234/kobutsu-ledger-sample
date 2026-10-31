import { REQUEST_ERROR_KEY } from '@/consts/error'

// 入力欄のエラー文言だけを並べて返す（操作そのものの失敗はトーストで伝えるので除く）。
export function getInputErrorList(errors: object): string[] {
  return Object.entries(errors)
    .filter(([key, message]) => key !== REQUEST_ERROR_KEY && typeof message === 'string')
    .map(([, message]) => String(message))
}
