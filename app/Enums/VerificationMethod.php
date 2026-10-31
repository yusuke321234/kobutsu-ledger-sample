<?php

namespace App\Enums;

/**
 * 相手方の確認方法（様式15号「確認方法」）。
 * EXEMPT は 1万円未満等の確認・記帳免除対象（例外品目を除く）の記録用。
 */
enum VerificationMethod: string
{
    case LICENSE = '1';
    case MYNUMBER_CARD = '2';
    case PASSPORT = '3';
    case DEALER = '4';
    case REMOTE_FLEA = '5';
    case OTHER = '8';
    case EXEMPT = '9';

    /**
     * 表示名（日本語）を返す
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::LICENSE => '運転免許証の提示',
            self::MYNUMBER_CARD => 'マイナンバーカードの提示',
            self::PASSPORT => 'パスポートの提示',
            self::DEALER => '古物商・店舗からの仕入',
            self::REMOTE_FLEA => '非対面取引（法定の非対面確認方法）',
            self::OTHER => 'その他の確認方法',
            self::EXEMPT => '確認免除（1万円未満等）',
        };
    }
}
