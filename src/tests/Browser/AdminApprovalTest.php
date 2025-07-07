<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\CorrectionRequest;
use App\Models\TimeLog;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AdminApprovalTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[Test]
    public function 承認待ちの修正申請が全て表示されている(): void
    {
        /* ───── 1. 事前データ ───── */
        // 管理者
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        // 検証対象日：前月 1 日から 3 日分
        $baseDay = Carbon::today()->subMonth()->startOfMonth(); // 例）2025-06-01

        // スタッフ 3 名 + それぞれ 1 件ずつ pending 申請
        $pendingRequests = collect();
        foreach (range(0, 2) as $i) {
            $staff = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
            $target = $baseDay->copy()->addDays($i); // 6/1, 6/2, 6/3

            // 勤怠作成
            $attendance = Attendance::factory()
                ->for($staff)
                ->create(['work_date' => $target]);

            // 申請ヘッダ（pending）
            $request = CorrectionRequest::factory()
                ->for($staff, 'user')
                ->for($attendance)
                ->create([
                    'status'      => CorrectionRequest::STATUS_PENDING,
                    'reason'      => '遅延のため',
                    'created_at'  => $target->copy()->addDay(), // 例：6/2 申請
                ]);

            // 申請ログ（出勤のみでも可）
            TimeLog::factory()->create([
                'attendance_id'         => $attendance->id,
                'correction_request_id' => $request->id,
                'type'                  => 'clock_in',
                'logged_at'             => $target->copy()->setTime(9, 0),
            ]);

            $pendingRequests->push([
                'name'   => $staff->name,
                'date'   => $target->format('Y/m/d'),
                'reason' => '遅延のため',
                'applied' => $request->created_at->format('Y/m/d'),
            ]);
        }

        /* ───── 2. ブラウザテスト ───── */
        $this->browse(function (Browser $browser) use ($admin, $pendingRequests) {

            $browser->loginAs($admin, 'admin')
                ->visit(route('admin.request.index'))
                ->waitForText('申請一覧')           // ページロード待ち
                ->assertSee('承認待ち');            // タブ名

            // 各 pending 行が表示されているか検証
            $pendingRequests->each(function ($row) use ($browser) {
                $browser->with("table tbody", function (Browser $tbl) use ($row) {
                    $tbl->assertSee($row['name'])
                        ->assertSee($row['date'])
                        ->assertSee($row['reason'])
                        ->assertSee($row['applied'])
                        ->assertSee('承認待ち');
                });
            });

            // スクリーンショット（任意）
            $browser->screenshot('admin_correction_request_pending_list_ok');
        });
    }

    #[Test]
    public function 承認済みの修正申請が全て表示されている(): void
    {
        /* ───── 1. 事前データ作成 ───── */
        // ─ 管理者
        $admin = User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        // ─ スタッフ 3 名を名前付きで生成
        $staffs = collect([
            '西 玲奈',
            '山田 太郎',
            '山田 花子',
        ])->map(fn($name) => User::factory()->create([
            'name'              => $name,
            'role'              => 'user',
            'email_verified_at' => now(),
        ]));

        // ─ 基準日（前月 1 日）から 3 連日で申請を用意
        $baseDay = Carbon::today()->subMonthNoOverflow()->startOfMonth(); // 例）2025-06-01

        // 一覧で検証したい行情報をここに詰める
        $approvedRows = collect();

        $staffs->each(function (User $staff, int $idx) use ($baseDay, $admin, $approvedRows) {
            $workDay   = $baseDay->copy()->addDays($idx);      // 6/1, 6/2, 6/3 …
            $applyDate = $workDay->copy()->addDay();           // 申請日は勤務日の翌日

            // 勤怠
            $attendance = Attendance::factory()->create([
                'user_id'   => $staff->id,
                'work_date' => $workDay->toDateString(),
            ]);

            // 承認済み申請
            CorrectionRequest::factory()->create([
                'user_id'       => $staff->id,
                'attendance_id' => $attendance->id,
                'reason'        => '遅延のため',
                'status'        => CorrectionRequest::STATUS_APPROVED,
                'created_at'    => $applyDate,
                'updated_at'    => $applyDate,
                'reviewed_at'   => $applyDate->copy()->addHour(), // ←承認日時
                'reviewed_by'   => $admin->id,
            ]);

            // 申請打刻（最低 1 本あれば OK）
            TimeLog::factory()->create([
                'attendance_id' => $attendance->id,
                'type'          => 'clock_in',
                'logged_at'     => $workDay->copy()->setTime(9, 0),
            ]);

            // 一覧検証用データ
            $approvedRows->push([
                'name'    => $staff->name,
                'date'    => $workDay->format('Y/m/d'),   // 対象日
                'reason'  => '遅延のため',
                'applied' => $applyDate->format('Y/m/d'), // 申請日を表示仕様とする
            ]);
        });

        /* ───── 2. ブラウザテスト ───── */
        $this->browse(function (Browser $browser) use ($admin, $approvedRows) {

            // ① 申請一覧 → 「承認済み」タブへ切替
            $browser->loginAs($admin, 'admin')
                ->visit(route('admin.request.index'))
                ->press('承認済み')          // Alpine.js のタブ切替
                ->waitForText('承認');       // 承認行が描画されるまで待機

            // ② 各行の内容を検証（申請日で確認）
            $approvedRows->each(function ($row) use ($browser) {

                // ※ 承認済みタブ領域だけをスコープ
                $browser->with('div[x-show="tab===\'approved\'"] table tbody', function (Browser $tbl) use ($row) {
                    $tbl->assertSee($row['name'])     // 氏名
                        ->assertSee($row['date'])     // 対象日
                        ->assertSee($row['reason'])   // 申請理由
                        ->assertSee($row['applied']); // 申請日
                });
            });


        });
    }

    #[Test]
    public function 修正申請の詳細内容が正しく表示されている(): void
    {
        /* ───── 1. 事前データ ───── */

        // 管理者
        $admin = User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        // 一般ユーザー
        $staff = User::factory()->create([
            'name'              => '西 玲奈',
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);

        // 勤務日：前月末
        $workDay = Carbon::today()->subMonthNoOverflow()->endOfMonth();   // 例）2025-06-30

        // 勤怠行
        $attendance = Attendance::factory()->create([
            'user_id'   => $staff->id,
            'work_date' => $workDay->toDateString(),
        ]);

        /* ----- ① 元（確定）ログ：09-18 ＋ 12-13 休憩 ----- */
        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'type' => 'clock_in',    'logged_at' => $workDay->copy()->setTime(9, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_start', 'logged_at' => $workDay->copy()->setTime(12, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_end',   'logged_at' => $workDay->copy()->setTime(13, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'clock_out',   'logged_at' => $workDay->copy()->setTime(18, 0)],
        ]);

        /* ----- ② 修正申請ヘッダ（pending） ----- */
        $correction = CorrectionRequest::factory()->create([
            'user_id'       => $staff->id,
            'attendance_id' => $attendance->id,
            'reason'        => '電車遅延のため',
            'status'        => CorrectionRequest::STATUS_PENDING,
            'created_at'    => $workDay->copy()->addDay(),   // 申請日＝翌日
        ]);

        /* ----- ③ 申請ログ：退勤 20:00 に延長 ----- */
        TimeLog::factory()->createMany([
            [
                'attendance_id' => $attendance->id,
                'correction_request_id' => $correction->id,
                'type' => 'clock_in',
                'logged_at' => $workDay->copy()->setTime(9, 0)
            ],
            [
                'attendance_id' => $attendance->id,
                'correction_request_id' => $correction->id,
                'type' => 'break_start',
                'logged_at' => $workDay->copy()->setTime(12, 0)
            ],
            [
                'attendance_id' => $attendance->id,
                'correction_request_id' => $correction->id,
                'type' => 'break_end',
                'logged_at' => $workDay->copy()->setTime(13, 0)
            ],
            [
                'attendance_id' => $attendance->id,
                'correction_request_id' => $correction->id,
                'type' => 'clock_out',
                'logged_at' => $workDay->copy()->setTime(20, 0)
            ],
        ]);

        /* ───── 2. ブラウザテスト ───── */
        $this->browse(function (Browser $browser) use ($admin, $correction, $staff, $workDay) {

            $browser->loginAs($admin, 'admin')
                ->visit(route('admin.request.approve', $correction->id))

                /*  基本情報（ヘッダ） */
                ->assertSee($staff->name)
                ->assertSee($workDay->year . '年')
                ->assertSee($workDay->format('n月j日'))

                /*  修正申請時刻 */
                ->assertSee('20:00')

                /*  備考 */
                ->assertSee('電車遅延のため')

                ->screenshot('admin_correction_request_detail_ok');
        });
    }

    #[Test]
    public function 修正申請の承認処理が正しく行われる(): void
    {
        /* ───── 1. 事前データ ───── */

        // 管理者
        $admin = User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        // 一般ユーザー（＝申請者）
        $staff = User::factory()->create([
            'name'              => '西 玲奈',
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);

        // 勤務日：前月末
        $workDay = Carbon::today()->subMonthNoOverflow()->endOfMonth();   // 例）2025-06-30

        // ① 元の勤怠行（09-18 ＋ 12-13 休憩）
        $attendance = Attendance::factory()->create([
            'user_id'   => $staff->id,
            'work_date' => $workDay->toDateString(),
        ]);

        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'type' => 'clock_in',    'logged_at' => $workDay->copy()->setTime(9, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_start', 'logged_at' => $workDay->copy()->setTime(12, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_end',   'logged_at' => $workDay->copy()->setTime(13, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'clock_out',   'logged_at' => $workDay->copy()->setTime(18, 0)],
        ]);

        // ② pending 修正申請（退勤を 20:00 に延長）
        $correction = CorrectionRequest::factory()->create([
            'user_id'       => $staff->id,
            'attendance_id' => $attendance->id,
            'reason'        => '電車遅延のため',
            'status'        => CorrectionRequest::STATUS_PENDING,
            'created_at'    => $workDay->copy()->addDay(),   // 申請日＝翌日
        ]);

        TimeLog::factory()->createMany([
            // 変更前と同じ 09-12-13 の３本
            ['attendance_id' => $attendance->id, 'correction_request_id' => $correction->id, 'type' => 'clock_in',    'logged_at' => $workDay->copy()->setTime(9, 0)],
            ['attendance_id' => $attendance->id, 'correction_request_id' => $correction->id, 'type' => 'break_start', 'logged_at' => $workDay->copy()->setTime(12, 0)],
            ['attendance_id' => $attendance->id, 'correction_request_id' => $correction->id, 'type' => 'break_end',   'logged_at' => $workDay->copy()->setTime(13, 0)],
            // ★ 退勤 20:00（これが確定後に反映されるはず）
            ['attendance_id' => $attendance->id, 'correction_request_id' => $correction->id, 'type' => 'clock_out',   'logged_at' => $workDay->copy()->setTime(20, 0)],
        ]);

        /* ───── 2. ブラウザテスト ───── */
        $this->browse(function (Browser $browser) use ($admin, $correction, $attendance, $workDay) {

            // ① 修正申請詳細 → 「承認」ボタンを押す
            $browser->loginAs($admin, 'admin')
                ->visit(route('admin.request.approve', $correction->id))
                ->waitForText('承認')            // ボタン描画待ち
                ->press('承認')                  // POST → approveExecute
                ->waitForText('承認済み')        // リダイレクト後、ボタンが「承認済み」に変わる
                ->assertSee('承認済み');

            // ② 勤怠詳細にジャンプして “20:00” が反映済みか確認
            $browser->visit(route('admin.attendance.show', $attendance->id))
                ->waitForText($workDay->format('n月j日'))
                ->assertInputValue('end_at', '20:00')   // 退勤が 20:00 に更新済み
                ->assertDontSee('18:00');  // 18:00 は表示されない（削除済み）
        });

        /* ───── 3. DB アサーション ───── */
        $this->assertDatabaseHas('correction_requests', [
            'id'     => $correction->id,
            'status' => CorrectionRequest::STATUS_APPROVED,
        ]);

        $this->assertDatabaseHas('time_logs', [
            'attendance_id' => $attendance->id,
            'type'          => 'clock_out',
            'logged_at'     => $workDay->copy()->setTime(20, 0),  // ← 新退勤
        ]);
    }
}
