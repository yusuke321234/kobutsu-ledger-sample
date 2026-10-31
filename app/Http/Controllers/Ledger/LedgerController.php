<?php

namespace App\Http\Controllers\Ledger;

use App\Consts\PageConsts;
use App\Contracts\Services\LedgerServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\GetLedgerExportRequest;
use App\Http\Requests\Ledger\GetLedgerListRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class LedgerController extends Controller
{
    private LedgerServiceInterface $ledgerService;



    /**
     * LedgerServiceInterface を注入する
     *
     * @param LedgerServiceInterface $ledgerService
     */
    public function __construct(LedgerServiceInterface $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }



    /**
     * 古物台帳の画面を表示する（受入＋払出の時系列1表。期間絞り込み・検索・「もっと見る」のページング）
     *
     * @param GetLedgerListRequest $request
     * @return InertiaResponse
     */
    public function getList(GetLedgerListRequest $request): InertiaResponse
    {
        // get
        $userId = (string)$request->user()->id;
        $currentPage = (int)($request->validated('current_page') ?? 1);
        $pageSize = (int)($request->validated('page_size') ?? PageConsts::DEFAULT_PAGE_SIZE);
        $filterData = $this->extractFilterData($request);

        // log
        Log::info("user_id: {$userId}");
        Log::info("currentPage: {$currentPage}");
        Log::info("pageSize: {$pageSize}");
        Log::info("filterData: " . print_r($filterData, true));

        // execute（一覧と件数は1回の取得を共有する。部分リロードで要らないときは取得しない）
        $listResult = null;
        $getListResult = function () use (&$listResult, $userId, $currentPage, $pageSize, $filterData): array {
            if ($listResult !== null) {
                // ok.
            } else {
                $listResult = $this->ledgerService->getList($userId, $currentPage, $pageSize, $filterData);
            }
            return $listResult;
        };

        // return（一覧は「もっと見る」で後ろに継ぎ足す。行 id は受入/払出の種別付きで一意）
        return Inertia::render('ledger', [
            'filterData' => $filterData,
            'pageSize' => $pageSize,
            'ledgerList' => Inertia::merge(fn() => $getListResult()['list'])->matchOn('id'),
            'totalCount' => fn() => $getListResult()['total_count'],
        ]);
    }



    /**
     * 様式15号の台帳PDFを出力する（「営業所で直ちに書面に表示できる」法要件）。
     * ブラウザで即表示→印刷できるよう inline で返す。
     *
     * @param GetLedgerExportRequest $request
     * @return Response
     */
    public function exportPdf(GetLedgerExportRequest $request): Response
    {
        // get
        $userId = (string)$request->user()->id;
        $filterData = $this->extractFilterData($request);

        // log
        Log::info("user_id: {$userId}");
        Log::info("filterData: " . print_r($filterData, true));

        // execute
        $pdf = $this->ledgerService->buildPdf($userId, $filterData);

        // return（ダウンロードでなく inline＝立入時にワンクリックで表示→印刷）
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->fileName($filterData, 'pdf') . '"',
        ]);
    }



    /**
     * 様式15号と同項目の台帳CSVを出力する
     *
     * @param GetLedgerExportRequest $request
     * @return Response
     */
    public function exportCsv(GetLedgerExportRequest $request): Response
    {
        // get
        $userId = (string)$request->user()->id;
        $filterData = $this->extractFilterData($request);

        // log
        Log::info("user_id: {$userId}");
        Log::info("filterData: " . print_r($filterData, true));

        // execute
        $csv = $this->ledgerService->buildCsv($userId, $filterData);

        // return
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $this->fileName($filterData, 'csv') . '"',
        ]);
    }



    /**
     * リクエストから期間・検索の絞り込みを取り出す
     *
     * @param FormRequest $request
     * @return array
     */
    private function extractFilterData(FormRequest $request): array
    {
        // 期間は任意（nullable の日付で検証済み。クエリの値なので文字列か null）
        $result = [
            'from' => $request->validated('from'),
            'to' => $request->validated('to'),
            'q' => (string)($request->validated('q') ?? ''),
        ];
        return $result;
    }



    /**
     * 出力ファイル名を組み立てる（環境差を避けるため ASCII のみ）
     *
     * @param array $filterData from / to
     * @param string $extension
     * @return string
     */
    private function fileName(array $filterData, string $extension): string
    {
        $period = 'all';
        if ($filterData['from'] !== null || $filterData['to'] !== null) {
            $period = ($filterData['from'] ?? '') . '_' . ($filterData['to'] ?? '');
        } else {
            // all のまま
        }

        //
        return "kobutsu-daicho_{$period}.{$extension}";
    }
}
