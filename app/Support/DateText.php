<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * DB から取り出した日付・日時（Model の toArray() で ISO 8601 の文字列になっている）を、画面や帳票に渡す形にそろえる。
 * null はそのまま null を返す（未入力の項目を、そのまま「未入力」として渡すため）。
 */
class DateText
{
    /**
     * 'Y-m-d' に整形する
     *
     * @param string|null $value
     * @return string|null
     */
    public static function toDateString(?string $value): ?string
    {
        $result = null;
        if ($value !== null) {
            $result = Carbon::parse($value)->toDateString();
        } else {
            // no action.
        }

        //
        return $result;
    }



    /**
     * 'Y-m-d H:i:s' に整形する
     *
     * @param string|null $value
     * @return string|null
     */
    public static function toDateTimeString(?string $value): ?string
    {
        $result = null;
        if ($value !== null) {
            $result = Carbon::parse($value)->toDateTimeString();
        } else {
            // no action.
        }

        //
        return $result;
    }
}
