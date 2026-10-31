<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 売却（払出）。1仕入=1売却（ロットを分けて売る形は扱わない）。
     * 商品ステータス（在庫中/売却済）は本テーブルの有無から導出する。
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 1仕入=1売却の制約は**アプリ層（RegisterSaleRequest）で担保**する。
            // DB の unique は論理削除を認識せず「取り消した売却の行」が残るため、
            // 売却を取り消した商品を二度と売れなくなる。「deleted_at が null の行だけ一意」は
            // MySQL / SQLite 双方で移植性のある形に書けないため、DB は外部キー索引のみとする。
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->date('sale_date')->comment('売却年月日');
            $table->tinyInteger('channel')->default(9)->comment('販路（SalesChannel Enum）');
            $table->unsignedInteger('sale_price')->comment('売却金額（円）');
            $table->unsignedInteger('fee')->default(0)->comment('販売手数料（円）');
            $table->unsignedInteger('shipping_cost')->default(0)->comment('送料（円）');
            $table->string('counterparty_name')->nullable()->comment('相手方 氏名（払出記載義務がある取引用・任意）');
            $table->string('counterparty_address')->nullable()->comment('相手方 住所（同上）');
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->index(['user_id', 'sale_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
