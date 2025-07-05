<?php


use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\User\AttendanceController as UserAttendanceController;

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\StaffAttendanceController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StampCorrectionRequestController as AdminStampCorrectionRequestController;
use App\Http\Controllers\User\StampCorrectionRequestController as UserStampCorrectionRequestController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\User\AttendanceStampController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\RegisteredUserController;



Route::get('/', function () {
    return view('welcome');
});

//テストのルーティング定義。最後の削除してしまってok。
Route::get('/test', function () {
    return view('test');
});

// 自作ルート メール確認通知表示
Route::get('/email/verify', function () {
    if (auth()->user()?->hasVerifiedEmail()) {
        return redirect()->route('attendance.stamp'); // メール確認済みなら出勤登録画面へリダイレクト
    }
    return view('auth.verify-email');
})->middleware(['auth'])->name('verification.notice');



// 自作ルート メール内リンククリック後
Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    if (auth()->user()?->hasVerifiedEmail()) {
        return redirect()->route('attendance.stamp'); // メール確認済みなら出勤登録画面へリダイレクト
    }

    $request->fulfill();
    return redirect()->route('attendance.stamp')->with('mail_status', 'メール認証が完了しました。ご確認ありがとうございます。');
})->middleware(['auth', 'signed'])->name('verification.verify');



// 自作ルート 再送信
Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();

    return back()->with('status', 'verification-link-sent');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');


//自作ルート
Route::get('/register', [RegisteredUserController::class, 'create'])
    ->middleware(['guest'])
    ->name('register');

//自作ルート
Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware(['guest']);


//自作ルート
Route::post('/login', [LoginController::class, 'store'])->name('login');






// ──────────────────────────────────────────────────────────────
// 一般ユーザー向け（Fortify の register / login は自動登録済み）
// ──────────────────────────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    // 出勤登録画面
    Route::get('/attendance', [AttendanceStampController::class, 'create'])
        ->name('attendance.stamp');

    // 出勤打刻処理　
    Route::post('/attendance/start', [AttendanceStampController::class, 'start'])
        ->name('attendance.start');

    // 休憩開始処理
    Route::post('/break/start', [AttendanceStampController::class, 'startBreak'])
        ->name('break.start');

    // 休憩終了処理
    Route::post('/break/end', [AttendanceStampController::class, 'endBreak'])
        ->name('break.end');

    //退勤処理
    Route::post('/attendance/end', [AttendanceStampController::class, 'end'])
        ->name('attendance.end');


    // 勤怠一覧
    Route::get('/attendance/list', [UserAttendanceController::class, 'index'])
        ->name('attendance.index');


    // 勤怠詳細(勤怠登録していない日について後日修正申請するためのルーティング)
    Route::get('/attendance/create', [UserAttendanceController::class, 'create'])->name('attendance.create');


    //　勤怠詳細
    Route::get('/attendance/{id}', [UserAttendanceController::class, 'show'])
        ->name('attendance.show');



    // 申請一覧
    Route::get('/stamp_correction_request/list',  [UserStampCorrectionRequestController::class, 'index'])
        ->name('request.index');

    // 修正申請処理
    Route::post('/store', [UserStampCorrectionRequestController::class, 'store'])->name('request.store');
});



// ──────────────────────────────────────────────────────────────
// 管理者向け
//   - プレフィックス: /admin
//   - 名前空間(任意): admin.*
//   - 認証:   guest:admin  (ログインフォームだけ)
//             auth.admin  (ログイン後の全画面)
// ──────────────────────────────────────────────────────────────
Route::prefix('admin')
    ->as('admin.')
    ->group(function () {
        //.仮ルート。あとで削除する。
        Route::get('/dashboard', function () {
            return "テストです";
        })
            ->middleware('auth:admin')
            ->name('dashboard');

        // --- ログイン画面（ゲスト専用） ----------------------
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])
            ->middleware('guest:admin')
            ->name('login');

        // ── ログイン実行（POST） ───────────
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('guest:admin')            // 同上
            ->name('login.store');

        // ── ログアウト（POST） ────────────
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
            ->middleware('auth:admin')       // もしくは 'auth.admin'
            ->name('logout');                // 結果 → admin.logout



        // --- ログイン後に閲覧できる画面 ----------------------
        Route::middleware('auth:admin')->group(function () {

            // 勤怠一覧
            Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])
                ->name('attendance.index');

            // 勤怠詳細
            Route::get('/attendance/{id}', [AdminAttendanceController::class, 'show'])->whereNumber('id')
                ->name('attendance.show');

            // 管理者が “勤怠新規作成” へ飛ぶルート
            Route::get('/attendance/create', [AdminAttendanceController::class, 'create'])
                ->name('attendance.create');   // ?user_id=◯&date=YYYY-MM-DD

            // スタッフ一覧
            Route::get('/staff/list', [StaffController::class, 'index'])
                ->name('staff.index');

            // 指定スタッフの勤怠一覧
            Route::get('/attendance/staff/{id}', [StaffAttendanceController::class, 'index'])
                ->name('staff_attendance.index');

            // 指定スタッフの月次勤怠CSV出力
            Route::get(
                '/attendance/staff/{id}/csv',
                [StaffAttendanceController::class, 'exportCsv']
            )->name('staff_attendance.csv');      // GET /admin/attendance/staff/8/csv?month=2025-06

            // 申請一覧
            Route::get(
                '/stamp_correction_request/list',
                [AdminStampCorrectionRequestController::class, 'index']
            )
                ->name('request.index');

            // 修正申請承認画面
            Route::get(
                '/stamp_correction_request/approve/{attendance_correct_request}',
                [AdminStampCorrectionRequestController::class, 'approve']
            )->name('request.approve');

            /* 承認実行（POST）— ステータスを approved にする */
            // ── 修正申請 承認実行（POST）────────────
            Route::post(
                '/stamp_correction_request/approve/{correctionRequest}',
                [AdminStampCorrectionRequestController::class, 'approveExecute']
            )->name('request.approve.execute');

            // 管理者による打刻修正処理
            Route::post(
                '/attendance/{attendance}/fix',
                [AdminStampCorrectionRequestController::class, 'store']
            )->name('attendance.fix');

        });
    });
