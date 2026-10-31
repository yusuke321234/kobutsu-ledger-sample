<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Ledger\LedgerController;
use App\Http\Controllers\Purchase\PurchaseController;
use App\Http\Controllers\Purchase\PurchasePhotoController;
use App\Http\Controllers\Sale\SaleController;
use Illuminate\Support\Facades\Route;

// アプリの画面（React + Inertia）。全ルートに掛けるミドルウェア（ログ・共有 props）は bootstrap/app.php の web グループ。
//
// 機能ごとに、画面（GET）とその操作（POST / PUT / DELETE）・ファイルの取得を並べて書く（Laravel の標準の書き方）。

// ゲスト専用（ログイン済みなら在庫へ戻す）。
// 総当たり・クレデンシャルスタッフィング対策として throttle を掛ける（上限は AppServiceProvider::configureRateLimiters）
Route::middleware("guest")->group(function () {

    // ログイン
    Route::get("/login", [AuthController::class, "showLogin"])->name("login");
    Route::post("/login", [AuthController::class, "login"])->middleware("throttle:login")->name("login.submit");

    // 新規登録
    Route::get("/register", [AuthController::class, "showRegister"])->name("register");
    Route::post("/register", [AuthController::class, "register"])->middleware("throttle:register")->name("register.submit");
});

// ログイン必須
Route::middleware("auth")->group(function () {

    // 設定・ログアウト（Auth）
    Route::get("/settings", [AuthController::class, "showSettings"])->name("settings");
    Route::put("/user/name", [AuthController::class, "updateName"])->name("user.name.update");
    Route::put("/user/office", [AuthController::class, "updateOffice"])->name("user.office.update");
    Route::post("/logout", [AuthController::class, "logout"])->name("logout");

    // 在庫（ホーム）＝仕入の一覧。台帳の元データ。
    // 写真は仕入の登録/更新と同じリクエストで受ける（1つの仕入につき5枚まで）
    Route::get("/", [PurchaseController::class, "getList"])->name("home");
    Route::post("/purchases", [PurchaseController::class, "register"])->name("purchases.register");
    Route::put("/purchases/{id}", [PurchaseController::class, "update"])->name("purchases.update");
    Route::delete("/purchases/{id}", [PurchaseController::class, "delete"])->name("purchases.delete");

    // 仕入の写真。実体は Web 非公開なので配信も認証ルート経由にする
    Route::get("/photos/{id}", [PurchasePhotoController::class, "show"])->name("photos.show");
    Route::delete("/photos/{id}", [PurchasePhotoController::class, "delete"])->name("photos.delete");

    // 売却（Sale）＝払出。1仕入=1売却
    Route::post("/sales", [SaleController::class, "register"])->name("sales.register");
    Route::put("/sales/{id}", [SaleController::class, "update"])->name("sales.update");
    Route::delete("/sales/{id}", [SaleController::class, "delete"])->name("sales.delete");

    // 古物台帳（Ledger）＝受入＋払出の時系列1表。仕入・売却の記録から都度組み立てる（保存しない）
    Route::get("/ledger", [LedgerController::class, "getList"])->name("ledger");
    Route::get("/ledger/pdf", [LedgerController::class, "exportPdf"])->name("ledger.pdf");
    Route::get("/ledger/csv", [LedgerController::class, "exportCsv"])->name("ledger.csv");
});

// どのルートにも当たらない URL は 404 画面（web グループを通るのでログイン状態を使って表示できる）
Route::fallback(function () {
    abort(404);
});
