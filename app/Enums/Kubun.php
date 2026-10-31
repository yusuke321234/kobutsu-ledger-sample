<?php

namespace App\Enums;

/**
 * 受入れ（仕入）の区別（古物営業法施行規則 別記様式第15号 備考1）。
 * 法令上、受入れの「区別」は **買受け又は委託** の2つ（交換・返品は受入れの区別ではない）。
 * 払出しの区別（売却／委託に基づく引渡し／返還）は sales 側で LedgerService が付ける。
 * 数字の区分コードを string で管理（DB は int、プログラム/API では string）。
 */
enum Kubun: string
{
    case BUY = '1';
    case CONSIGN = '2';

    /**
     * 表示名（日本語）を返す
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::BUY => '買受け',
            self::CONSIGN => '委託',
        };
    }
}
