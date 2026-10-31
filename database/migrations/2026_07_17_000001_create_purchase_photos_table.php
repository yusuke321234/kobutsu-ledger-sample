<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 仕入の写真（1つの仕入につき5枚まで）。
     *
     * purchases に列を持つと1枚しか持てないため、別テーブルにする。
     * 写真は法定の記載事項ではない（記載事項は「特徴」のテキスト）ので論理削除は不要＝
     * ユーザーが消したら行もファイルも消す。ファイルは Web 非公開の local ディスクに置き、
     * 所有者チェック付きの認証ルートで配信する。
     */
    public function up(): void
    {
        Schema::create('purchase_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->string('path')->comment('local ディスク上の保存パス（Web非公開）');
            $table->datetimes();

            $table->index(['purchase_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_photos');
    }
};
