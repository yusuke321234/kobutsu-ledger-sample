<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class InitialLog
{
    /**
     * リクエスト開始時に IP / ホストをログする（リクエスト開始マーカー）
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // IP とホスト名（逆引き）を取得
        $ipAddress = (string)$request->server->get('REMOTE_ADDR');
        $host = '';
        if ($ipAddress !== '') {
            $host = (string)gethostbyaddr($ipAddress);
        } else {
            // no action.
        }

        // リクエスト開始ログ
        $initialLogText  = "";
        $initialLogText .= "\n\n\n\n\n\n\n\n\n\n";
        $initialLogText .= "=================================== ↓↓ start ↓↓ ===================================\n";
        $initialLogText .= "IP: {$ipAddress}\n";
        $initialLogText .= "HOST: {$host}\n";
        Log::info($initialLogText);

        //
        $response = $next($request);

        //
        return $response;
    }
}
