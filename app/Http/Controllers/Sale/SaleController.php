<?php

namespace App\Http\Controllers\Sale;

use App\Consts\FlashConsts;
use App\Contracts\Services\SaleServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\RegisterSaleRequest;
use App\Http\Requests\Sale\UpdateSaleRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Support\Toast;

class SaleController extends Controller
{
    private SaleServiceInterface $saleService;



    /**
     * SaleServiceInterface を注入する
     *
     * @param SaleServiceInterface $saleService
     */
    public function __construct(SaleServiceInterface $saleService)
    {
        $this->saleService = $saleService;
    }



    /**
     * 売却を登録する（在庫の「売れた」。単品の粗利を案内する）
     *
     * @param RegisterSaleRequest $request
     * @return RedirectResponse
     */
    public function register(RegisterSaleRequest $request): RedirectResponse
    {
        // get
        $userId = (string)$request->user()->id;
        $registerData = $this->extractSaleData($request);
        $registerData['purchase_id'] = (string)$request->validated('purchase_id');

        // log
        Log::info("user_id: {$userId}");
        Log::info("registerData: " . print_r($this->maskForLog($registerData), true));

        // execute
        $result = $this->saleService->register($userId, $registerData);

        // return（粗利＝売価−仕入。仕入額が未入力なら粗利は出さない）
        $message = '売却を記録しました。';
        if ($result['raw_gross_profit'] !== null) {
            $message .= '粗利 ¥' . number_format((int)$result['raw_gross_profit']);
        } else {
            // no action.
        }
        Inertia::flash(FlashConsts::TOAST_DATA, Toast::success($message));
        return back();
    }



    /**
     * 売却を更新する（販路・売価・手数料の訂正）
     *
     * @param UpdateSaleRequest $request
     * @param string $id
     * @return RedirectResponse
     */
    public function update(UpdateSaleRequest $request, string $id): RedirectResponse
    {
        // get
        $userId = (string)$request->user()->id;
        $updateData = $this->extractSaleData($request);

        // log
        Log::info("user_id: {$userId}");
        Log::info("sale_id: {$id}");
        Log::info("updateData: " . print_r($this->maskForLog($updateData), true));

        // execute
        $result = $this->saleService->update($userId, $id, $updateData);

        // return（対象が無ければ404）
        if ($result !== null) {
            Log::info("売却を更新しました。 sale_id: {$id}");
        } else {
            Log::info("更新対象が見つからないため404を返します。 sale_id: {$id}");

            //
            abort(404, '対象の売却が見つかりません。');
        }

        //
        Inertia::flash(FlashConsts::TOAST_DATA, Toast::success('売却を更新しました。'));
        return back();
    }



    /**
     * 売却を論理削除する（売却の取り消し。商品は在庫中に戻る）
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
        Log::info("sale_id: {$id}");

        // execute
        $deleted = $this->saleService->delete($userId, $id);

        // return（対象が無ければ404）
        if ($deleted === true) {
            Log::info("売却を論理削除しました。 sale_id: {$id}");
        } else {
            Log::info("削除対象が見つからないため404を返します。 sale_id: {$id}");

            //
            abort(404, '対象の売却が見つかりません。');
        }

        //
        Inertia::flash(FlashConsts::TOAST_DATA, Toast::success('売却を取り消しました。'));
        return back();
    }



    /**
     * リクエストから売却の登録/更新データを取り出す（経費は未入力を 0 に寄せる）
     *
     * @param FormRequest $request
     * @return array
     */
    private function extractSaleData(FormRequest $request): array
    {
        // 経費は未入力なら 0、入力があれば int にそろえる
        $fee = 0;
        if ($request->validated('fee') !== null) {
            $fee = (int)$request->validated('fee');
        } else {
            // no action.
        }
        $shippingCost = 0;
        if ($request->validated('shipping_cost') !== null) {
            $shippingCost = (int)$request->validated('shipping_cost');
        } else {
            // no action.
        }

        //
        $result = [
            'sale_date' => (string)$request->validated('sale_date'),
            'channel' => (string)$request->validated('channel'),
            'sale_price' => (int)$request->validated('sale_price'),
            'fee' => $fee,
            'shipping_cost' => $shippingCost,
            'counterparty_name' => $request->validated('counterparty_name'),
            'counterparty_address' => $request->validated('counterparty_address'),
        ];
        return $result;
    }



    /**
     * ログ用に相手方の住所（個人情報）をマスクする
     *
     * @param array $data
     * @return array
     */
    private function maskForLog(array $data): array
    {
        $masked = $data;
        $masked['counterparty_address'] = '(記録しません)';

        //
        return $masked;
    }
}
