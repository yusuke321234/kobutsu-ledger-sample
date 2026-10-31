<?php

namespace App\Services;

use App\Contracts\Repositories\PurchaseRepositoryInterface;
use App\Contracts\Repositories\SaleRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\LedgerServiceInterface;
use App\Enums\Kubun;
use App\Enums\VerificationMethod;
use App\Support\DateText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * 古物台帳（様式15号）の読み取り専用ビューと出力。
 * 台帳は保存せず、仕入（受入）と売却（払出）の記録から**その都度組み立てる**。
 * 受入・払出を時系列1表にまとめるため、2テーブルを取得してからマージする。
 */
class LedgerService implements LedgerServiceInterface
{
    private PurchaseRepositoryInterface $purchaseRepository;
    private SaleRepositoryInterface $saleRepository;
    private UserRepositoryInterface $userRepository;

    /**
     * Repository を注入する
     *
     * @param PurchaseRepositoryInterface $purchaseRepository
     * @param SaleRepositoryInterface $saleRepository
     * @param UserRepositoryInterface $userRepository
     */
    public function __construct(
        PurchaseRepositoryInterface $purchaseRepository,
        SaleRepositoryInterface $saleRepository,
        UserRepositoryInterface $userRepository
    ) {
        $this->purchaseRepository = $purchaseRepository;
        $this->saleRepository = $saleRepository;
        $this->userRepository = $userRepository;
    }



    /**
     * 台帳の行を取得する（受入＋払出の時系列1表・期間絞り込み・検索・ページング）。
     * 画面は確認用途のため新しい順に返す（帳票＝PDF/CSV は古い順＝帳簿の並び）。
     *
     * @param string $userId
     * @param int $currentPage 1始まりのページ番号
     * @param int $pageSize 1ページの件数
     * @param array $filterData from / to / q
     * @return array
     */
    public function getList(string $userId, int $currentPage, int $pageSize, array $filterData): array
    {
        $rowList = $this->buildRows($userId, $filterData, false);

        //
        $totalCount = count($rowList);
        $offset = ($currentPage - 1) * $pageSize;
        $list = array_slice($rowList, $offset, $pageSize);
        Log::info("台帳を組み立てました。 user_id: {$userId}, 総件数: {$totalCount}");

        //
        $result = [
            'total_count' => $totalCount,
            'list' => $list,
        ];
        return $result;
    }



    /**
     * 様式15号の台帳PDFを生成して返す（「営業所で直ちに書面に表示できる」法要件）。
     * 帳簿なので古い順（時系列昇順）で刷る。日本語は mpdf の ja モードでフォントを埋め込む。
     *
     * @param string $userId
     * @param array $filterData from / to / q
     * @return string PDF のバイナリ
     */
    public function buildPdf(string $userId, array $filterData): string
    {
        $rowList = $this->buildRows($userId, $filterData, true);
        $userData = $this->userRepository->getData($userId);
        Log::info("台帳PDFを生成します。 user_id: {$userId}, 行数: " . count($rowList));

        //
        $html = view('pdf.ledger', [
            'rowList' => $this->withLabels($rowList),
            'officeName' => $userData['office_name'] ?? null,
            'licenseNumber' => $userData['license_number'] ?? null,
            'periodText' => $this->periodText($filterData),
            'printedAt' => Carbon::now()->format('Y年n月j日 H:i'),
        ])->render();

        // 台帳は横に長いので A4 横。tempDir は vendor 配下が書けない環境があるため storage に置く
        $tempDir = storage_path('app/mpdf');
        if (is_dir($tempDir) === false) {
            mkdir($tempDir, 0775, true);
        } else {
            // no action.
        }

        //
        $mpdf = new Mpdf([
            'mode' => 'ja',
            'format' => 'A4-L',
            'tempDir' => $tempDir,
            'margin_top' => 12,
            'margin_bottom' => 12,
            'margin_left' => 10,
            'margin_right' => 10,
        ]);
        $mpdf->WriteHTML($html);
        $result = $mpdf->Output('', Destination::STRING_RETURN);
        Log::info("台帳PDFを生成しました。 user_id: {$userId}, バイト数: " . strlen($result));

        //
        return $result;
    }



    /**
     * 様式15号の台帳CSVを生成して返す（PDF と同項目）。
     * Excel（日本語環境）が文字化けせず開けるよう UTF-8 BOM を付ける。
     *
     * @param string $userId
     * @param array $filterData from / to / q
     * @return string CSV の中身
     */
    public function buildCsv(string $userId, array $filterData): string
    {
        $rowList = $this->withLabels($this->buildRows($userId, $filterData, true));
        Log::info("台帳CSVを生成します。 user_id: {$userId}, 行数: " . count($rowList));

        //
        $handle = fopen('php://temp', 'r+');
        // 受入れ/払出しは法令上の必須の区別なので独立した列にする（1表形式のため）
        fputcsv($handle, [
            '取引年月日', '受入/払出', '区別', '品目', '数量', '特徴', '代価',
            '相手方の住所', '相手方の氏名', '相手方の職業', '相手方の年齢', '確認方法',
        ]);
        foreach ($rowList as $rowData) {
            // 利用者が入れた文字（品目・特徴・相手方）は、表計算ソフトに数式として読まれないようにする
            fputcsv($handle, [
                $rowData['transaction_date'],
                $rowData['record_type_label'],
                $rowData['division_label'],
                $this->toCsvText($rowData['item_name']),
                $rowData['quantity'],
                $this->toCsvText($rowData['features']),
                $rowData['amount'],
                $this->toCsvText($rowData['counterparty_address']),
                $this->toCsvText($rowData['counterparty_name']),
                $this->toCsvText($rowData['counterparty_occupation']),
                $this->toCsvText($rowData['counterparty_age']),
                $rowData['verification_label'],
            ]);
        }
        rewind($handle);
        $csv = (string)stream_get_contents($handle);
        fclose($handle);

        // BOM 付き UTF-8（Excel で開いても文字化けしない）
        $result = "\xEF\xBB\xBF" . $csv;
        return $result;
    }



    /**
     * 期間・検索を適用した台帳の全行を組み立てる（受入＋払出のマージ）。
     *
     * 2テーブルをマージしてから並べるため、期間内の行をいったん全件読む。
     * 副業・個人の想定件数（年数百件）では問題にならない。件数が増えたら UNION + LIMIT へ移す。
     *
     * @param string $userId
     * @param array $filterData from / to / q
     * @param bool $ascending true=古い順（帳簿・帳票用）／false=新しい順（画面確認用）
     * @return array
     */
    private function buildRows(string $userId, array $filterData, bool $ascending): array
    {
        $from = $filterData['from'];
        $to = $filterData['to'];
        $q = $filterData['q'];

        // 受入（仕入。下書きは法定記録として未完成のため Repository 側で除外）
        $purchaseList = $this->purchaseRepository->getForLedger($userId, $from, $to, $q);
        $rowList = [];
        foreach ($purchaseList as $purchaseData) {
            $rowList[] = $this->shapeReceiveRow($purchaseData);
        }

        // 払出（売却）
        $saleList = $this->saleRepository->getForLedger($userId, $from, $to, $q);
        foreach ($saleList as $saleData) {
            $rowList[] = $this->shapePayoutRow($saleData);
        }

        // 時系列1表にする（PHP 8 のソートは安定なので同日は受入→払出の並びが保たれる）
        usort($rowList, function (array $a, array $b) use ($ascending): int {
            if ($ascending === true) {
                return strcmp((string)$a['transaction_date'], (string)$b['transaction_date']);
            } else {
                return strcmp((string)$b['transaction_date'], (string)$a['transaction_date']);
            }
        });

        //
        return $rowList;
    }



    /**
     * 帳票用に区別・確認方法の表示名を付ける。
     * 法令の「区別」は受入れ＝買受け/委託、払出し＝売却/委託に基づく引渡し/返還（施行規則 別記様式第15号 備考1）。
     *
     * @param array $rowList
     * @return array
     */
    private function withLabels(array $rowList): array
    {
        $result = [];
        foreach ($rowList as $rowData) {
            // 受入れ/払出しの別は法令上の必須の区別。1表形式なので行ごとに明示する
            $recordTypeLabel = '払出し';
            $divisionLabel = '売却';
            if ($rowData['record_type'] === 'receive') {
                $recordTypeLabel = '受入れ';
                if ($rowData['kubun'] !== null) {
                    $divisionLabel = Kubun::from($rowData['kubun'])->label();
                } else {
                    // no action.
                }
            } else {
                // 払出の区別：委託品を売った引渡しは法令上「委託に基づく引渡し」。買受け品は「売却」。
                // （売れ残りを持ち主へ戻す「返還」は 1仕入=1売却 の設計では払出し行が立たないため扱わない）
                if (isset($rowData['source_kubun']) && $rowData['source_kubun'] === Kubun::CONSIGN->value) {
                    $divisionLabel = '委託に基づく引渡し';
                } else {
                    // no action.
                }
            }

            $verificationLabel = '';
            if ($rowData['verification_method'] !== null) {
                $verificationLabel = VerificationMethod::from($rowData['verification_method'])->label();
            } else {
                // no action.
            }

            $rowData['record_type_label'] = $recordTypeLabel;
            $rowData['division_label'] = $divisionLabel;
            $rowData['verification_label'] = $verificationLabel;
            $result[] = $rowData;
        }

        //
        return $result;
    }



    /**
     * 帳票ヘッダに刷る対象期間の文言を組み立てる
     *
     * @param array $filterData from / to
     * @return string
     */
    private function periodText(array $filterData): string
    {
        $from = $filterData['from'];
        $to = $filterData['to'];

        $result = '全期間';
        if ($from !== null && $to !== null) {
            $result = "{$from} 〜 {$to}";
        } elseif ($from !== null) {
            $result = "{$from} 以降";
        } elseif ($to !== null) {
            $result = "{$to} まで";
        } else {
            // 全期間のまま
        }

        //
        return $result;
    }



    /**
     * 受入（仕入）を台帳の1行に整形する
     *
     * @param array $purchaseData
     * @return array
     */
    private function shapeReceiveRow(array $purchaseData): array
    {
        // 確認方法（区分コード）は string、金額は int にそろえる（未入力は null のまま）
        $verificationMethod = null;
        if ($purchaseData['verification_method'] !== null) {
            $verificationMethod = (string)$purchaseData['verification_method'];
        } else {
            // no action.
        }
        $amount = null;
        if ($purchaseData['price'] !== null) {
            $amount = (int)$purchaseData['price'];
        } else {
            // no action.
        }

        //
        $result = [
            // 2テーブル混在のため行 id は種別付きにする（画面の一覧の key 用。単体の id は purchase_id）
            'id' => 'purchase-' . $purchaseData['id'],
            'record_type' => 'receive',
            'transaction_date' => DateText::toDateString($purchaseData['transaction_date'] ?? null),
            'kubun' => (string)$purchaseData['kubun'],
            // 払出し行の区別出し分け用（受入れ行では未使用。行の形を揃えるため null を置く）
            'source_kubun' => null,
            'channel' => null,
            'item_name' => $purchaseData['item_name'],
            'quantity' => (int)$purchaseData['quantity'],
            'features' => $purchaseData['features'],
            'counterparty_name' => $purchaseData['counterparty_name'],
            'counterparty_address' => $purchaseData['counterparty_address'],
            'counterparty_occupation' => $purchaseData['counterparty_occupation'],
            'counterparty_age' => $purchaseData['counterparty_age'],
            'verification_method' => $verificationMethod,
            'amount' => $amount,
            'purchase_id' => (string)$purchaseData['id'],
        ];
        return $result;
    }



    /**
     * 払出（売却）を台帳の1行に整形する。品目・数量・特徴は紐づく仕入から引く。
     *
     * @param array $saleData
     * @return array
     */
    private function shapePayoutRow(array $saleData): array
    {
        $purchaseData = $saleData['purchase'] ?? null;

        // 元になった仕入の区別（区分コード）。仕入が引けないときは null
        $sourceKubun = null;
        if (isset($purchaseData['kubun']) === true) {
            $sourceKubun = (string)$purchaseData['kubun'];
        } else {
            // no action.
        }

        //
        $result = [
            'id' => 'sale-' . $saleData['id'],
            'record_type' => 'payout',
            'transaction_date' => DateText::toDateString($saleData['sale_date'] ?? null),
            'kubun' => null,
            // 払出しの区別を法令準拠で出し分けるため、元になった仕入の区別を持ち回す（画面では未使用）
            'source_kubun' => $sourceKubun,
            'channel' => (string)$saleData['channel'],
            'item_name' => $purchaseData['item_name'] ?? null,
            'quantity' => (int)($purchaseData['quantity'] ?? 1),
            'features' => $purchaseData['features'] ?? null,
            // 払出の相手方は記載義務がある取引のみ任意入力（職業・年齢は sales に持たない）
            'counterparty_name' => $saleData['counterparty_name'],
            'counterparty_address' => $saleData['counterparty_address'],
            'counterparty_occupation' => null,
            'counterparty_age' => null,
            'verification_method' => null,
            'amount' => (int)$saleData['sale_price'],
            'purchase_id' => (string)$saleData['purchase_id'],
        ];
        return $result;
    }



    /**
     * 利用者が入れた文字を、CSV に書いても表計算ソフトに数式として読まれない形にする（CSV インジェクション対策）。
     * = + - @ タブ 改行（CR）で始まるときは、先頭に ' を付けて文字列として扱わせる。
     *
     * @param string|null $value
     * @return string|null
     */
    private function toCsvText(?string $value): ?string
    {
        $result = $value;
        if ($value !== null && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $result = "'" . $value;
        } else {
            // no action.
        }

        //
        return $result;
    }
}
