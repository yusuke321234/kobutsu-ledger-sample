<?php

namespace App\Consts;

/**
 * 画面へ返すエラー（form.errors）のうち、入力欄に属さないもののキー。
 * 画面側は resources/js/consts/error.ts の同じ値で受ける。
 */
class ErrorConsts
{
    /** 操作そのものが失敗した（権限なし・対象なし・サーバーエラー・ページの期限切れ）。文言はトーストで伝える */
    public const REQUEST = 'request';
}
