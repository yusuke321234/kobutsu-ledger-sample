<?php

namespace App\Http\Controllers\Purchase;

use App\Consts\FlashConsts;
use App\Consts\PageConsts;
use App\Contracts\Services\PurchasePhotoServiceInterface;
use App\Contracts\Services\PurchaseServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\GetPurchaseListRequest;
use App\Http\Requests\Purchase\RegisterPurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\Toast;

class PurchaseController extends Controller
{
    private PurchaseServiceInterface $purchaseService;

    private PurchasePhotoServiceInterface $purchasePhotoService;



    /**
     * PurchaseServiceInterface / PurchasePhotoServiceInterface を注入する
     *
     * @param PurchaseServiceInterface $purchaseService
     * @param PurchasePhotoServiceInterface $purchasePhotoService
     */
    public function __construct(
        PurchaseServiceInterface $purchaseService,
        PurchasePhotoServiceInterface $purchasePhotoService,
    ) {
        $this->purchaseService = $purchaseService;
        $this->purchasePhotoService = $purchasePhotoService;
    }



    /**
     * 在庫画面（ホーム）を表示する（在庫＝台帳の元データ。検索・状態フィルタ・「もっと見る」のページング）
     *
     * @param GetPurchaseListRequest $request
     * @return Response
     */
    public function getList(GetPurchaseListRequest $request): Response
    {
        // get（状態フィルタの既定は「在庫中」＝在庫画面の初期表示）
        $userId = (string)$request->user()->id;
        $currentPage = (int)($request->validated('current_page') ?? 1);
        $pageSize = (int)($request->validated('page_size') ?? PageConsts::DEFAULT_PAGE_SIZE);
        $filterData = [
            'status' => (string)($request->validated('status') ?? 'in_stock'),
            'q' => (string)($request->validated('q') ?? ''),
        ];

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
                $listResult = $this->purchaseService->getList($userId, $currentPage, $pageSize, $filterData);
            }
            return $listResult;
        };

        // return（一覧は「もっと見る」で後ろに継ぎ足す。同じ id の行は置き換える）
        return Inertia::render('inventory', [
            'filterData' => $filterData,
            'pageSize' => $pageSize,
            'purchaseList' => Inertia::merge(fn() => $getListResult()['list'])->matchOn('id'),
            'totalCount' => fn() => $getListResult()['total_count'],
            // 仕入が1件でもあるか（0件のとき「まだ登録がない」と「条件に当てはまらない」を出し分ける）
            'hasPurchase' => fn() => $this->purchaseService->hasAny($userId),
            'counterpartyList' => fn() => $this->purchaseService->getCounterparties($userId)['list'],
        ]);
    }



    /**
     * 仕入を登録する（連続登録の1件。下書き可。写真が付いていれば一緒に保存する）
     *
     * @param RegisterPurchaseRequest $request
     * @return RedirectResponse
     */
    public function register(RegisterPurchaseRequest $request): RedirectResponse
    {
        // get
        $userId = (string)$request->user()->id;
        $registerData = $this->extractPurchaseData($request);
        $photo = $request->validated('photo');

        // log（相手方は個人情報のため氏名以外は記録しない）
        Log::info("user_id: {$userId}");
        Log::info("registerData: " . print_r($this->maskForLog($registerData), true));
        Log::info("hasPhoto: " . var_export($photo !== null, true));

        // execute（写真は仕入の id が要るので、仕入を保存してから紐づける）
        $result = $this->purchaseService->register($userId, $registerData);
        $photoNote = null;
        if ($photo !== null) {
            $photoNote = $this->registerPhoto($userId, (string)$result['id'], $photo);
        } else {
            // no action.
        }

        // return（完了の案内は、連続登録のモーダル／PC のクイック追加で文言が違うため画面側で出す。
        // 写真だけ保存できなかった時は、その理由だけをサーバーから伝える）
        if ($photoNote !== null) {
            Inertia::flash(FlashConsts::TOAST_DATA, Toast::error($photoNote));
        } else {
            // no action.
        }
        return back();
    }



    /**
     * 仕入を更新する（インライン編集・下書きの完成。写真が付いていれば追加する）
     *
     * @param UpdatePurchaseRequest $request
     * @param string $id
     * @return RedirectResponse
     */
    public function update(UpdatePurchaseRequest $request, string $id): RedirectResponse
    {
        // get
        $userId = (string)$request->user()->id;
        $updateData = $this->extractPurchaseData($request);
        $photo = $request->validated('photo');

        // log
        Log::info("user_id: {$userId}");
        Log::info("purchase_id: {$id}");
        Log::info("updateData: " . print_r($this->maskForLog($updateData), true));
        Log::info("hasPhoto: " . var_export($photo !== null, true));

        // execute
        $result = $this->purchaseService->update($userId, $id, $updateData);

        // return（対象が無ければ404）
        if ($result !== null) {
            Log::info("仕入を更新しました。 purchase_id: {$id}");
        } else {
            Log::info("更新対象が見つからないため404を返します。 purchase_id: {$id}");

            //
            abort(404, '対象の仕入が見つかりません。');
        }

        //
        $photoNote = null;
        if ($photo !== null) {
            $photoNote = $this->registerPhoto($userId, $id, $photo);
        } else {
            // no action.
        }

        // 写真だけ保存できなかった時は、その理由を添える
        $message = '保存しました。';
        if ($photoNote !== null) {
            $message .= $photoNote;
        } else {
            // no action.
        }
        Inertia::flash(FlashConsts::TOAST_DATA, Toast::success($message));
        return back();
    }



    /**
     * 仕入を論理削除する（法定3年保存のため物理削除しない）
     *
     * @param Request $request
     * @param string $id
     * @return RedirectResponse
     */
    public function delete(Request $request, string $id): RedirectResponse
    {
        // get
        $userId = (string)$request->user()->id;

        // log
        Log::info("user_id: {$userId}");
        Log::info("purchase_id: {$id}");

        // execute
        $result = $this->purchaseService->delete($userId, $id);

        // return（売却済みは先に売却取消が必要なので403、対象が無ければ404）
        if ($result['status'] === 'ok') {
            Log::info("仕入を論理削除しました。 purchase_id: {$id}");
        } else if ($result['status'] === 'sold') {
            Log::info("売却済みのため403を返します。 purchase_id: {$id}");

            //
            abort(403, '売却済みの仕入は、先に売却を取り消してから削除してください。');
        } else {
            Log::info("削除対象が見つからないため404を返します。 purchase_id: {$id}");

            //
            abort(404, '対象の仕入が見つかりません。');
        }

        //
        Inertia::flash(FlashConsts::TOAST_DATA, Toast::success('削除しました。'));
        return back();
    }



    /**
     * 仕入に写真を1枚紐づける（1つの仕入につき5枚まで）。
     * 写真だけ保存できなくても仕入の保存は成立させ、上限超過はトーストに添える文言を返す
     *
     * @param string $userId
     * @param string $purchaseId
     * @param UploadedFile $photo
     * @return string|null 画面に添える案内（無ければ null）
     */
    private function registerPhoto(string $userId, string $purchaseId, UploadedFile $photo): ?string
    {
        $result = $this->purchasePhotoService->register($userId, $purchaseId, $photo);

        // Service の status に応じて案内する
        $note = null;
        Log::info("写真の保存結果を判定します。 purchase_id: {$purchaseId}, status: {$result['status']}");
        if ($result['status'] === 'limit_exceeded') {
            $note = '写真は1つの仕入につき5枚までです。';
        } else {
            // no action.（保存できた。仕入が見つからない場合は、呼び出し元で先に弾いている）
        }

        //
        return $note;
    }



    /**
     * リクエストから仕入の登録/更新データを取り出す（boolean は safe()->boolean() で正規化。値は検証済みのものだけを読む）
     *
     * @param FormRequest $request
     * @return array
     */
    private function extractPurchaseData(FormRequest $request): array
    {
        // 金額は未入力なら null、入力があれば int にそろえる
        $price = null;
        if ($request->validated('price') !== null) {
            $price = (int)$request->validated('price');
        } else {
            // no action.
        }

        //
        $result = [
            'transaction_date' => (string)$request->validated('transaction_date'),
            'kubun' => (string)$request->validated('kubun'),
            'item_name' => $request->validated('item_name'),
            'quantity' => (int)($request->validated('quantity') ?? 1),
            'features' => $request->validated('features'),
            'counterparty_name' => $request->validated('counterparty_name'),
            'counterparty_address' => $request->validated('counterparty_address'),
            'counterparty_occupation' => $request->validated('counterparty_occupation'),
            'counterparty_age' => $request->validated('counterparty_age'),
            'verification_method' => $request->validated('verification_method'),
            'price' => $price,
            'is_exempt' => (bool)$request->safe()->boolean('is_exempt'),
            'is_draft' => (bool)$request->safe()->boolean('is_draft'),
        ];
        return $result;
    }



    /**
     * ログ用に相手方の個人情報（氏名以外）をマスクする
     *
     * @param array $data
     * @return array
     */
    private function maskForLog(array $data): array
    {
        $masked = $data;
        $masked['counterparty_address'] = '(記録しません)';
        $masked['counterparty_occupation'] = '(記録しません)';
        $masked['counterparty_age'] = '(記録しません)';

        //
        return $masked;
    }
}
