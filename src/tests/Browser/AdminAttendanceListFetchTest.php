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
        foreach ([$yesterday, $today] as $d) {
            foreach ($staffs as $u) {
                $att = Attendance::factory()
                    ->for($u)
                    ->create(['work_date' => $d]);

                TimeLog::insert([
                    [
                        'attendance_id' => $att->id,
                        'logged_at'     => $d->copy()->setTime(9, 0),
                        'type'          => 'clock_in',
                    ],
                    [
                        'attendance_id' => $att->id,
                        'logged_at'     => $d->copy()->setTime(12, 0),
                        'type'          => 'break_start',
                    ],
                    [
                        'attendance_id' => $att->id,
                        'logged_at'     => $d->copy()->setTime(13, 0),
                        'type'          => 'break_end',
                    ],
                    [
                        'attendance_id' => $att->id,
                        'logged_at'     => $d->copy()->setTime(18, 0),
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
            foreach ($staffs as $idx => $u) {

                // tr:nth-child(n) … 名前 = 1 列目
                $row = "table tbody tr:nth-child(" . ($idx + 1) . ")";

                $browser->assertSeeIn("$row td:nth-child(1)", $u->name)  // 名前
                    ->assertSeeIn("$row td:nth-child(2)", '09:00')   // 出勤
                    ->assertSeeIn("$row td:nth-child(3)", '18:00')   // 退勤
                    ->assertSeeIn("$row td:nth-child(4)", '1:00')    // 休憩
                    ->assertSeeIn("$row td:nth-child(5)", '8:00');   // 合計
            }
        });
    }
}
