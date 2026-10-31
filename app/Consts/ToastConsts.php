<?php

namespace App\Consts;

/**
 * トースト（Inertia::flash の toast）の種類。
 * 画面側は resources/js/lib/toast.tsx で見た目を出し分ける。
 */
class ToastConsts
{
    /** 完了の案内（墨色の帯） */
    public const SUCCESS = 'success';

    /** 失敗の案内（赤の帯。権限なし・対象なし・サーバーエラー・ページの期限切れ） */
    public const ERROR = 'error';
}
