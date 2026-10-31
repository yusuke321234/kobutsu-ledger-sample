<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    {{--
        様式15号（古物営業法施行規則 別記様式第15号）の台帳。
        「この様式に則っていればどのようなものでも可」＝法定の記載事項を満たすことが要件。
        記載事項：取引年月日／品目及び数量／特徴／相手方の住所・氏名・職業・年齢／確認方法。
        フォントは指定しない（mpdf の ja モード既定＝日本語を埋め込むため。sans-serif 等を
        指定すると日本語を持たないフォントにマップされ文字化けする）。
    --}}
    <style>
        @page { footer: html_footer; }
        body { font-size: 9pt; color: #000; }
        h1 { font-size: 14pt; margin: 0 0 4px 0; }
        .head { width: 100%; margin-bottom: 6px; }
        .head td { font-size: 9pt; padding: 1px 0; }
        .head .label { color: #555; width: 70px; }
        .unset { color: #b00; }
        table.ledger { width: 100%; border-collapse: collapse; }
        table.ledger th, table.ledger td { border: 0.5px solid #333; padding: 3px 4px; vertical-align: top; }
        table.ledger th { background: #eee; font-weight: bold; text-align: center; font-size: 8.5pt; }
        table.ledger td.num { text-align: right; }
        table.ledger td.center { text-align: center; }
        .empty { padding: 20px; text-align: center; color: #555; border: 0.5px solid #333; }
        .note { margin-top: 6px; font-size: 8pt; color: #555; }
    </style>
</head>
<body>
    <h1>古物台帳</h1>

    <table class="head">
        <tr>
            <td class="label">営業所名</td>
            <td>
                @if ($officeName)
                    {{ $officeName }}
                @else
                    <span class="unset">（未設定：設定画面から登録してください）</span>
                @endif
            </td>
            <td class="label">許可番号</td>
            <td>
                @if ($licenseNumber)
                    {{ $licenseNumber }}
                @else
                    <span class="unset">（未設定）</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">対象期間</td>
            <td>{{ $periodText }}</td>
            <td class="label">出力日時</td>
            <td>{{ $printedAt }}</td>
        </tr>
    </table>

    @if (count($rowList) > 0)
        <table class="ledger">
            <thead>
                <tr>
                    <th width="8%">取引年月日</th>
                    <th width="10%">区別</th>
                    <th width="16%">品目</th>
                    <th width="5%">数量</th>
                    <th width="19%">特徴</th>
                    <th width="8%">代価</th>
                    <th width="26%">相手方（住所・氏名・職業・年齢）</th>
                    <th width="8%">確認方法</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rowList as $rowData)
                    <tr>
                        <td class="center">{{ $rowData['transaction_date'] }}</td>
                        <td class="center">
                            {{ $rowData['record_type_label'] }}<br>
                            （{{ $rowData['division_label'] }}）
                        </td>
                        <td>{{ $rowData['item_name'] }}</td>
                        <td class="center">{{ $rowData['quantity'] }}</td>
                        <td>{{ $rowData['features'] }}</td>
                        <td class="num">{{ $rowData['amount'] !== null ? number_format($rowData['amount']) : '' }}</td>
                        <td>
                            {{ $rowData['counterparty_address'] }}
                            @if ($rowData['counterparty_address'] && $rowData['counterparty_name'])
                                <br>
                            @endif
                            {{ $rowData['counterparty_name'] }}
                            @if ($rowData['counterparty_occupation'] || $rowData['counterparty_age'])
                                <br>{{ $rowData['counterparty_occupation'] }}
                                @if ($rowData['counterparty_age'])
                                    {{ $rowData['counterparty_age'] }}歳
                                @endif
                            @endif
                        </td>
                        <td>{{ $rowData['verification_label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="note">
            この台帳は、記録した仕入（受入れ）・売却（払出し）から自動生成しています。全{{ count($rowList) }}件。
        </p>
    @else
        <p class="empty">この期間に記録された取引はありません。</p>
    @endif

    <htmlpagefooter name="footer">
        <div style="text-align: center; font-size: 8pt; color: #555;">
            {{ $officeName ?? '' }} 古物台帳 — {{ $periodText }} — {PAGENO} / {nbpg}
        </div>
    </htmlpagefooter>
</body>
</html>
