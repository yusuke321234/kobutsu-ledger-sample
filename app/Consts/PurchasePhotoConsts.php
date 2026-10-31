<?php

namespace App\Consts;

/**
 * 仕入の写真の定数。
 */
class PurchasePhotoConsts
{
    /** 1つの仕入に付けられる枚数の上限（無制限にすると保存容量が青天井になるため上限を設ける） */
    public const MAX_COUNT = 5;
}
