<?php

namespace App\Http\Requests\Purchase;

/**
 * 仕入（受入）の更新リクエスト。インライン編集・下書きの「完成させる」に使う。
 * 検証ルールとメッセージは登録と同じ（is_draft=false へ確定する際に様式15号の必須項目を要求する）ので、
 * 登録のリクエストをそのまま使う。売却済みの仕入に対する制限（下書きに戻せない・仕入日の前後）は、
 * 保存されている売却と比べる必要があるため PurchaseService で確かめる。
 */
class UpdatePurchaseRequest extends RegisterPurchaseRequest
{
}
