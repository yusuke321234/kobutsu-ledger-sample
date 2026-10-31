<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;

/**
 * リクエストごとにログファイルを振り分けるロガーを作る（config/logging.php の single チャンネル）
 *
 * storage/logs/年/月/日/web/コントローラ/メソッド.log に書く（例：web/purchase/register.log）。
 * どのファイルに書くかは RequestPathLogHandler が「ログを書く時」に決める。
 * 設定ファイルの中で決めると、本番の config:cache（artisan optimize）で最初の値に固定されてしまうため。
 */
class CreateRequestPathLogger
{
    /**
     * ロガーを作る
     *
     * @param array $config
     * @return Logger
     */
    public function __invoke(array $config): Logger
    {
        // get
        $level = $config['level'] ?? 'debug';
        $replacePlaceholders = $config['replace_placeholders'] ?? false;

        //
        $logger = new Logger((string)config('app.env'));
        $logger->pushHandler(new RequestPathLogHandler($level));

        // メッセージ中の {name} を context の値で置き換える（Laravel 標準の single と同じ）
        if ($replacePlaceholders === true) {
            $logger->pushProcessor(new PsrLogMessageProcessor());
        } else {
            // no action.
        }

        //
        return $logger;
    }
}
