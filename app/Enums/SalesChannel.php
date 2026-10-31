<?php

namespace App\Enums;

/**
 * 販路（売却チャネル）。粗利計算・販路別レポートで使用。
 */
enum SalesChannel: string
{
    case MERCARI = '1';
    case YAHOO_AUCTION = '2';
    case YAHOO_FLEA = '3';
    case AMAZON = '4';
    case RAKUMA = '5';
    case IN_PERSON = '6';
    case OTHER = '9';

    /**
     * 表示名（日本語）を返す
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::MERCARI => 'メルカリ',
            self::YAHOO_AUCTION => 'ヤフオク',
            self::YAHOO_FLEA => 'Yahoo!フリマ',
            self::AMAZON => 'Amazon',
            self::RAKUMA => '楽天ラクマ',
            self::IN_PERSON => '店頭・対面',
            self::OTHER => 'その他',
        };
    }
}
