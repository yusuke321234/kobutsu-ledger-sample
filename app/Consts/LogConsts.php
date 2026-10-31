<?php

namespace App\Consts;

/**
 * ログファイルの振り分け（App\Logging\RequestPathLogHandler）の定数。
 */
class LogConsts
{
    /** 日付フォルダと時刻のタイムゾーン（アプリ本体は UTC のまま。ログだけ日本時間で読めるようにする） */
    public const TIMEZONE = 'Asia/Tokyo';

    /** 画面・操作（routes/web.php）のログの置き場 */
    public const WEB_DIRECTORY = 'web';

    /** artisan コマンド（スケジューラ含む）のログの置き場 */
    public const CONSOLE_DIRECTORY = 'console';

    /** 存在しない URL（Route::fallback）のログ */
    public const NOT_FOUND_NAME = 'web/not-found';

    /** どれにも当たらないもの（ルートが決まる前のエラー等）のログ */
    public const OTHER_NAME = 'web/other';

    /** 振り分け先に書けなかった時の書き先（従来のログ） */
    public const FALLBACK_FILE = 'logs/laravel.log';
}
