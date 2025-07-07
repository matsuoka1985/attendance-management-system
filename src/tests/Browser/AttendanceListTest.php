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

class AttendanceListTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[Test]
    public function 「前月」を押下した時に表示月の前月の情報が表示される()
    {
        $this->browse(function (Browser $browser) {
            $baseDate = Carbon::now()->subMonth()->startOfMonth();
            $user = User::factory()->create([
                'email_verified_at' => now(),
            ]);

            foreach (range(0, 2) as $i) {
                $date = $baseDate->copy()->addDays($i);

                $attendance = Attendance::factory()->create([
                    'user_id' => $user->id,
                    'work_date' => $date->format('Y-m-d'),
                ]);

                TimeLog::factory()->create([
                    'attendance_id' => $attendance->id,
                    'logged_at' => $date->format('Y-m-d') . ' 09:00:00',
                    'type' => 'clock_in',
                ]);

                TimeLog::factory()->create([
                    'attendance_id' => $attendance->id,
                    'logged_at' => $date->format('Y-m-d') . ' 12:00:00',
                    'type' => 'break_start',
                ]);

                TimeLog::factory()->create([
                    'attendance_id' => $attendance->id,
                    'logged_at' => $date->format('Y-m-d') . ' 13:00:00',
                    'type' => 'break_end',
                ]);

                TimeLog::factory()->create([
                    'attendance_id' => $attendance->id,
                    'logged_at' => $date->format('Y-m-d') . ' 18:00:00',
                    'type' => 'clock_out',
                ]);
            }

            $browser->loginAs($user)
                ->visit('/attendance/list')
                ->press('前月')
                ->pause(1000);

            foreach (range(0, 2) as $i) {
                $date = $baseDate->copy()->addDays($i)->isoFormat('MM/DD(ddd)');

                $browser->with("table tbody tr:nth-of-type(" . ($i + 1) . ")", function ($row) use ($date) {
                    $row->assertSee($date);        // 日付列
                    $row->assertSee('09:00');      // 出勤
                    $row->assertSee('18:00');      // 退勤
                    $row->assertSee('1:00');       // 休憩（1時間）
                    $row->assertSee('8:00');       // 合計（9時間 - 1時間）
                    $row->assertSee('詳細');       // 詳細リンク
                });
            }
        });
    }

    #[Test]
    public function 「翌月」を押下した時に表示月の前月の情報が表示される(): void
    {
        /* ---------- 0. ユーザ作成 & 基準月計算 ---------- */
        $user = User::factory()->create([
            'email'    => 'user@example.com',
            'password' => bcrypt('password'),
        ]);

        $today            = Carbon::now();          // テスト実行日
        $previousMonth    = $today->copy()->subMonth()->startOfMonth();   // 先月 1 日 00:00
        $previous2Month   = $today->copy()->subMonths(2)->startOfMonth(); // 先々月 1 日 00:00

        /* ---------- 1. データ投入 ---------- */
        // 先々月 1 日（起点用）
        $this->createAttendanceWithLogs($user, $previous2Month->copy());

        // 先月 1, 2, 3 日（検証対象）
        $this->createAttendanceWithLogs($user, $previousMonth->copy()->addDays(0)); // 1 日
        $this->createAttendanceWithLogs($user, $previousMonth->copy()->addDays(1)); // 2 日
        $this->createAttendanceWithLogs($user, $previousMonth->copy()->addDays(2)); // 3 日

        /* ---------- 2–5. ブラウザ操作 & 期待値確認 ---------- */
        $this->browse(function (Browser $browser) use ($user, $previous2Month, $previousMonth) {

            $browser->loginAs($user)
                // 2. 先々月の勤怠一覧へ
                ->visit('/attendance/list?month=' . $previous2Month->format('Y-m'))

                // 3. 「翌月」ボタン押下
                ->press('翌月')

                // 4. URL クエリ / ヘッダ月 表示確認
                ->assertPathIs('/attendance/list')
                ->assertQueryStringHas('month', $previousMonth->format('Y-m'))
                ->assertSee($previousMonth->format('Y/m'))      // ヘッダの「YYYY/MM」

                // 5. テーブル 3 行分の日付と打刻時刻が見えていること
                ->assertSee($previousMonth->copy()->addDays(0)->format('m/d'))
                ->assertSee($previousMonth->copy()->addDays(1)->format('m/d'))
                ->assertSee($previousMonth->copy()->addDays(2)->format('m/d'))
                ->assertSeeIn('table', '09:00')    // 出勤
                ->assertSeeIn('table', '18:00')    // 退勤
                ->assertSeeIn('table', '1:00')     // 休憩（12–13 時）
                ->assertSeeIn('table', '8:00');    // 労働（9 h − 1 h）
        });
    }

    /*
    *  勤怠 + 打刻 4 本（出勤・休憩開始・休憩終了・退勤）をまとめて作るヘルパ
    */
    private function createAttendanceWithLogs(User $user, Carbon $date): void
    {
        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $date->toDateString(),
        ]);

        TimeLog::factory()->createMany([
            [
                'attendance_id' => $attendance->id,
                'type'          => 'clock_in',
                'logged_at'     => $date->copy()->setTime(9, 0),
            ],
            [
                'attendance_id' => $attendance->id,
                'type'          => 'break_start',
                'logged_at'     => $date->copy()->setTime(12, 0),
            ],
            [
                'attendance_id' => $attendance->id,
                'type'          => 'break_end',
                'logged_at'     => $date->copy()->setTime(13, 0),
            ],
            [
                'attendance_id' => $attendance->id,
                'type'          => 'clock_out',
                'logged_at'     => $date->copy()->setTime(18, 0),
            ],
        ]);
    }


    #[Test]
    public function 「詳細」を押下すると、その日の勤怠詳細画面に遷移する(): void
    {
        /*──────────────────────────────
               1. 当月 1 日の勤怠データを持つユーザ
             ──────────────────────────────*/
        $user       = User::factory()->create(['email_verified_at' => now()]);
        $targetDate = Carbon::now()->startOfMonth();                 // 当月 1 日

        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $targetDate->toDateString(),
        ]);

        // ─ 打刻 4 本を “その場” で作成（9-18 + 休憩 12-13）
        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'type' => 'clock_in', 'logged_at' => $targetDate->copy()->setTime(9, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_start', 'logged_at' => $targetDate->copy()->setTime(12, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_end', 'logged_at' => $targetDate->copy()->setTime(13, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'clock_out', 'logged_at' => $targetDate->copy()->setTime(18, 0)],
        ]);

        /*──────────────────────────────
               2. ブラウザ操作と検証
             ──────────────────────────────*/
        $this->browse(function (Browser $browser) use ($user, $attendance, $targetDate) {
            $browser->loginAs($user)
                ->visit('/attendance/list')                          // 当月の勤怠一覧
                ->assertSee($targetDate->isoFormat('MM/DD'))         // 行が存在
                ->assertSee($targetDate->format('Y/m'))              // ヘッダの年月

                // 「詳細」リンクをクリック 「末尾一致」($=)
                ->click("a[href$='/attendance/{$attendance->id}']")

                // ─ 遷移先を検証 ─
                ->assertPathIs("/attendance/{$attendance->id}")      // URL
                ->assertSee('勤怠詳細')                               // 見出し
                ->assertSee($targetDate->format('n月j日'));          // 日付表示 例: 7月1日
        });
    }
}
