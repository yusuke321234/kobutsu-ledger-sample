<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 様式15号の帳票ヘッダに刷る営業所名・許可番号。
     * 未設定でも台帳の記録自体は成立するため nullable（PDF 側で未設定を促す）。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('office_name')->nullable()->after('given_name')->comment('営業所名（様式15号の帳票ヘッダ用）');
            $table->string('license_number')->nullable()->after('office_name')->comment('古物商許可番号（帳票ヘッダ用・任意）');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['office_name', 'license_number']);
        });
    }
};
