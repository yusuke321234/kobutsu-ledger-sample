<?php

namespace App\Support;

use App\Consts\ToastConsts;

/**
 * トーストのフラッシュデータ（種類 + 文言）を組み立てる。
 *
 * 使い方：Inertia::flash(FlashConsts::TOAST_DATA, Toast::success('削除しました。'));
 */
class Toast
{
    /**
     * 完了の案内を返す
     *
     * @param string $message
     * @return array
     */
    public static function success(string $message): array
    {
        //
        return [
            'type' => ToastConsts::SUCCESS,
            'message' => $message,
        ];
    }



    /**
     * 失敗の案内を返す
     *
     * @param string $message
     * @return array
     */
    public static function error(string $message): array
    {
        //
        return [
            'type' => ToastConsts::ERROR,
            'message' => $message,
        ];
    }
}
