<?php

namespace App\Support;

/**
 * LIKE 検索の部分一致のパターンを作る。
 *
 * 利用者が入れた検索語の % と _ は、ワイルドカードではなく「その文字そのもの」として探す。
 * そのためエスケープしてから % で囲む。エスケープ文字は ! にする
 * （\ は MySQL と SQLite で SQL の文字列としての書き方が違い、同じ SQL で扱えないため）。
 * 使う側は whereRaw("列 like ? escape '!'", [LikePattern::contains($q)]) と書く。
 */
class LikePattern
{
    /**
     * 部分一致のパターンを返す（例：50%オフ → %50!%オフ%）
     *
     * @param string $keyword
     * @return string
     */
    public static function contains(string $keyword): string
    {
        //
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
        return '%' . $escaped . '%';
    }
}
