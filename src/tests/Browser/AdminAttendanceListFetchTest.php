<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TimeLog;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

class AdminAttendanceListFetchTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[Test]
    public function 遷移した際に現在の日付が表示される(): void
    {
        /* ───────── 1) 事前データ ───────── */
        // 管理者ユーザ
        $admin = User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        // 今日の日付
        $today = Carbon::today();

        // スタッフ 2 名分のダミー勤怠（行が無くても日付表示自体は確認できるが、行がある方が自然なので作成）
        User::factory()->count(2)->create()->each(function ($staff) use ($today) {
            $attendance = Attendance::factory()
                ->for($staff)
                ->create(['work_date' => $today]);

            // ざっくり 09:00 出勤だけ入れておく（休憩・退勤は不要）
            TimeLog::create([
                'attendance_id'         => $attendance->id,
                'logged_at'             => $today->copy()->setTime(9, 0),
                'type'                  => 'clock_in',
                'correction_request_id' => null,
            ]);
        });

        /* ───────── 2) ブラウザ操作 ───────── */
        $this->browse(function (Browser $browser) use ($admin, $today) {

            // ① 管理者としてログイン
            $browser->loginAs($admin, 'admin')

                // ② 勤怠一覧へ遷移
                ->visit('/admin/attendance/list')
                ->assertPathIs('/admin/attendance/list')

                // ③ タイトル・カレンダー部に「今日の日付」が表示されているか
                //    - タイトル：『YYYY年n月j日』の勤怠
                //    - カレンダー部：YYYY/MM/DD
                ->waitForText($today->format('Y/m/d'), 5)
                ->assertSee($today->format('Y/m/d'))
                ->assertSee($today->format('Y年n月j日'))

                // ④ デバッグ用スクリーンショット（失敗時のみファイルが残る）
                ->screenshot('admin_attendance_list_today');
        });
    }


    #[Test]
    public function 「前日」を押下した時に前の日の勤怠情報が表示される(): void
    {
        /* ---------- 1) テストデータ ---------- */
        $today     = now()->startOfDay();
        $yesterday = $today->copy()->subDay();

        /* 管理者 */
        $admin = User::factory()->create([
            'role'              => 'admin',
        ]);

        /* スタッフ 3 名（ID 昇順で確定） */
        $staffs = User::factory()
            ->count(3)
            ->create([
                'role'              => 'user',
                'email_verified_at' => now(),
            ])
            ->sortBy('id')            // 行順 = id 順
            ->values();               // 0,1,2 の連番に整形

        /* ── 2 日分の勤怠＋ログ（休憩 1 h 付き）を投入 ── */
        foreach ([$yesterday, $today] as $targetDate) {
            foreach ($staffs as $staffUser) {
                $attendance = Attendance::factory()
                    ->for($staffUser)
                    ->create(['work_date' => $targetDate]);

                TimeLog::insert([
                    [
                        'attendance_id' => $attendance->id,
                        'logged_at'     => $targetDate->copy()->setTime(9, 0),
                        'type'          => 'clock_in',
                    ],
                    [
                        'attendance_id' => $attendance->id,
                        'logged_at'     => $targetDate->copy()->setTime(12, 0),
                        'type'          => 'break_start',
                    ],
                    [
                        'attendance_id' => $attendance->id,
                        'logged_at'     => $targetDate->copy()->setTime(13, 0),
                        'type'          => 'break_end',
                    ],
                    [
                        'attendance_id' => $attendance->id,
                        'logged_at'     => $targetDate->copy()->setTime(18, 0),
                        'type'          => 'clock_out',
                    ],
                ]);
            }
        }

        /* ---------- 2) ブラウザ操作 ---------- */
        $this->browse(function (Browser $browser) use (
            $admin,
            $today,
            $yesterday,
            $staffs
        ) {

            /* ① 当日 → 「前日」クリック */
            $browser->loginAs($admin, 'admin')
                ->visit('/admin/attendance/list')
                ->assertSee($today->isoFormat('YYYY年M月D日の勤怠'))
                ->press('前日')
                ->waitForText($yesterday->isoFormat('YYYY年M月D日の勤怠'), 5);

            /* ② 各行（id 昇順）で列値を検証 */
            foreach ($staffs as $index => $staffUser) {

                // tr:nth-child(n) … 名前 = 1 列目
                $row = "table tbody tr:nth-child(" . ($index + 1) . ")";

                $browser->assertSeeIn("$row td:nth-child(1)", $staffUser->name)  // 名前
                    ->assertSeeIn("$row td:nth-child(2)", '09:00')   // 出勤
                    ->assertSeeIn("$row td:nth-child(3)", '18:00')   // 退勤
                    ->assertSeeIn("$row td:nth-child(4)", '1:00')    // 休憩
                    ->assertSeeIn("$row td:nth-child(5)", '8:00');   // 合計
            }
        });
    }

    #[Test]
    public function 「翌日」を押下した時に次の日の勤怠情報が表示される(): void
    {
        /* ────── 1. 事前データ ────── */
        // 管理者
        $admin = User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        // テスト対象日: 今日の前日 (= 昨日) とその前日
        $yesterday        = Carbon::yesterday()->startOfDay();           // 2025-xx-yy 00:00
        $dayBefore        = $yesterday->copy()->subDay();                // 前々日
        $nextDateString   = $yesterday->toDateString();                  // ボタン value 用
        $yesterdayH1      = $yesterday->format('Y年n月j日');             // タイトル表記
        $yesterdaySlash   = $yesterday->format('Y/m/d');                 // カレンダー表記
        $dayBeforeSlash   = $dayBefore->format('Y/m/d');

        // スタッフ 3 人 & 勤怠 + 休憩 1h（12:00-13:00）
        $staffs = User::factory()->count(3)->create([
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);

        foreach ($staffs as $staff) {
            $attendance = Attendance::factory()->create([
                'user_id'   => $staff->id,
                'work_date' => $yesterday->toDateString(),
            ]);

            TimeLog::insert([
                [ // 出勤 09:00
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $yesterday->copy()->setTime(9, 0),
                    'type'          => 'clock_in',
                ],
                [ // 休憩開始 12:00
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $yesterday->copy()->setTime(12, 0),
                    'type'          => 'break_start',
                ],
                [ // 休憩終了 13:00
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $yesterday->copy()->setTime(13, 0),
                    'type'          => 'break_end',
                ],
                [ // 退勤 18:00
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $yesterday->copy()->setTime(18, 0),
                    'type'          => 'clock_out',
                ],
            ]);
        }

        /* ────── 2. ブラウザ操作 ────── */
        $this->browse(function (Browser $browser) use (
            $admin,
            $staffs,
            $dayBefore,
            $dayBeforeSlash,
            $yesterday,
            $yesterdaySlash,
            $yesterdayH1,
            $nextDateString
        ) {
            $browser->loginAs($admin, 'admin')

                // 前々日の一覧を開く
                ->visit(route('admin.attendance.index', ['date' => $dayBefore->toDateString()]))
                ->waitForText($dayBeforeSlash)

                // ───────── 「翌日」ボタン押下 ─────────
                //   button[name=date] の value 属性で正確にクリック
                ->click('button[name="date"][value="' . $nextDateString . '"]')

                // ────── 3. 検証 ──────
                // カレンダー欄 (Y/m/d)
                ->waitForText($yesterdaySlash, 5)
                // タイトル (Y年n月j日)
                ->assertSee($yesterdayH1)

                // テーブルの中身（名前 / 出退勤 / 休憩 1:00）を 3 人分確認
                ->within('table tbody', function (Browser $tbody) use ($staffs) {
                    foreach ($staffs as $staff) {
                        $tbody->assertSee($staff->name)
                            ->assertSee('09:00')
                            ->assertSee('18:00')
                            ->assertSee('1:00');
                    }
                });
        });
    }
}
