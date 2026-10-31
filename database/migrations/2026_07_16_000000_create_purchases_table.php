<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 仕入（受入）。様式15号の必須6項目＋税務（仕入金額）＋下書き。
     * 論理削除のみ（法定3年保存のため物理削除しない）。
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date')->comment('取引年月日（様式15号）');
            $table->tinyInteger('kubun')->default(1)->comment('区分（Kubun Enum）');
            // 品目は様式15号の必須項目だが、下書き（現場捕獲＝写真＋金額のみ）を許すため DB は nullable。
            // 確定時の必須チェックはアプリ層（RegisterPurchaseRequest）で行う。
            $table->string('item_name')->nullable()->comment('品目（確定時必須・下書きは任意）');
            $table->unsignedInteger('quantity')->default(1)->comment('数量');
            $table->text('features')->nullable()->comment('特徴（型番・シリアル・状態）');
            $table->string('counterparty_name')->nullable()->comment('相手方 氏名');
            $table->string('counterparty_address')->nullable()->comment('相手方 住所');
            $table->string('counterparty_occupation')->nullable()->comment('相手方 職業');
            $table->string('counterparty_age')->nullable()->comment('相手方 年齢');
            $table->tinyInteger('verification_method')->nullable()->comment('確認方法（VerificationMethod Enum）');
            $table->unsignedInteger('price')->nullable()->comment('仕入金額（円）');
            $table->boolean('is_exempt')->default(false)->comment('記帳免除対象（1万円未満等）');
            // 写真は purchase_photos テーブルへ（1つの仕入に複数枚付けるため）
            $table->boolean('is_draft')->default(false)->comment('下書き（現場捕獲→PCで完成）');
            $table->datetimes();
            $table->softDeletesDatetime();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['user_id', 'is_draft']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
