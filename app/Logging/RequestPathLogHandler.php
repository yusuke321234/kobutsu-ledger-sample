<?php

namespace App\Logging;

use App\Consts\LogConsts;
use DateTimeZone;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * ログ1件ごとに書き先のファイルを決めて追記する
 *
 * - 画面・操作：storage/logs/年/月/日/web/コントローラ/メソッド.log（POST と PUT で URL が同じでもメソッドで分かれる）
 * - 存在しない URL：…/web/not-found.log ／ artisan：…/console/コマンド.log
 * - ルートが決まる前のエラー等：…/web/other.log
 * 日付と時刻は日本時間。振り分け先に書けない時は従来の laravel.log に書く（ログのせいで処理を止めない）。
 */
class RequestPathLogHandler extends AbstractProcessingHandler
{
    /**
     * 書式は Laravel 標準の single チャンネルと同じにし、時刻を日本時間に直す
     *
     * @param int|string|Level $level
     */
    public function __construct(int|string|Level $level = Level::Debug)
    {
        parent::__construct($level, true);

        //
        $this->setFormatter(new LineFormatter(null, 'Y-m-d H:i:s', true, true, true));
        $this->pushProcessor(function (LogRecord $record): LogRecord {
            return $record->with(datetime: $record->datetime->setTimezone(new DateTimeZone(LogConsts::TIMEZONE)));
        });
    }



    /**
     * ログ1件を、日付とリクエストに応じたファイルへ追記する
     *
     * @param LogRecord $record
     * @return void
     */
    protected function write(LogRecord $record): void
    {
        // get
        $path = storage_path("logs/" . $record->datetime->format("Y/m/d") . "/" . $this->getLogName() . ".log");
        $text = (string)$record->formatted;

        //
        $written = $this->append($path, $text);
        if ($written === true) {
            // ok.
        } else {
            $this->append(storage_path(LogConsts::FALLBACK_FILE), $text);
        }
    }



    /**
     * 書き先の名前（年/月/日 より下、拡張子なし）を返す
     *
     * @return string
     */
    private function getLogName(): string
    {
        $route = request()->route();
        if ($route instanceof Route) {
            return $this->getRouteLogName($route);
        } else {
            // ルートが無い：artisan コマンド、またはルートが決まる前のエラー
            if (app()->runningInConsole() === true) {
                return LogConsts::CONSOLE_DIRECTORY . "/" . $this->getCommandName();
            } else {
                return LogConsts::OTHER_NAME;
            }
        }
    }



    /**
     * ルートから書き先の名前を返す（アプリのコントローラなら web/コントローラ/メソッド）
     *
     * @param Route $route
     * @return string
     */
    private function getRouteLogName(Route $route): string
    {
        // 存在しない URL（Route::fallback）
        if ($route->isFallback === false) {
            // ok.
        } else {
            return LogConsts::NOT_FOUND_NAME;
        }

        //
        $actionName = $route->getActionName();
        if (str_starts_with($actionName, "App\\Http\\Controllers\\") === true) {
            return $this->getControllerLogName($actionName);
        } else {
            // クロージャ等
            return LogConsts::OTHER_NAME;
        }
    }



    /**
     * アプリのコントローラから書き先の名前を返す（例：web/purchase/register、web/purchase-photo/show）
     *
     * @param string $actionName
     * @return string
     */
    private function getControllerLogName(string $actionName): string
    {
        // get（1メソッドだけのコントローラは @ を持たない）
        $actionPartList = explode("@", $actionName);
        $controllerName = Str::kebab(Str::beforeLast(class_basename($actionPartList[0]), "Controller"));
        $methodName = Str::kebab($actionPartList[1] ?? "__invoke");

        //
        return LogConsts::WEB_DIRECTORY . "/{$controllerName}/{$methodName}";
    }



    /**
     * artisan のコマンド名を、ファイル名に使える形で返す（例：schedule:run → schedule-run）
     *
     * @return string
     */
    private function getCommandName(): string
    {
        $command = (string)($_SERVER['argv'][1] ?? "");
        $fileName = (string)preg_replace("/[^a-z0-9-]/", "", str_replace(":", "-", strtolower($command)));
        if ($fileName !== "") {
            return $fileName;
        } else {
            return "artisan";
        }
    }



    /**
     * ファイルへ追記する（フォルダが無ければ作る）。書けたかを返す
     *
     * @param string $path
     * @param string $text
     * @return bool
     */
    private function append(string $path, string $text): bool
    {
        // 同時に複数のリクエストが作ることもあるため、作成の失敗（既にある）は気にしない
        $directory = dirname($path);
        if (is_dir($directory) === true) {
            // ok.
        } else {
            @mkdir($directory, 0775, true);
        }

        //
        $result = @file_put_contents($path, $text, FILE_APPEND | LOCK_EX);
        if ($result !== false) {
            return true;
        } else {
            return false;
        }
    }
}
