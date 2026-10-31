<?php

namespace App\Consts;

/**
 * 画面へ一度だけ渡すフラッシュデータ（Inertia::flash）のキー。
 * 画面側は resources/js/types/global.d.ts の flashDataType で同じキーを受ける。
 */
class FlashConsts
{
    /** トーストに出す文言（全画面共通で表示する） */
    public const TOAST_DATA = 'toastData';
}
