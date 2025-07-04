<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TimeLog;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AdminAttendanceDetailTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[Test]
    public function 勤怠詳細画面に表示されるデータが選択したものになっている(): void
    {
        /* ───── 1) 事前データ ───── */
        // 管理者ユーザー
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        // スタッフ & 勤怠
        $staff    = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $workDate = Carbon::yesterday()->startOfDay();

        $attendance = Attendance::factory()
            ->for($staff)
            ->create(['work_date' => $workDate]);

        /* 打刻ログ — 出勤 09:00 / 退勤 20:00 / 休憩 12:00〜13:00 */
        TimeLog::factory()->createMany([
            [
                'attendance_id' => $attendance->id,
                'type'          => 'clock_in',
                'logged_at'     => $workDate->copy()->setTime(9, 0),
            ],
            [
                'attendance_id' => $attendance->id,
                'type'          => 'break_start',
                'logged_at'     => $workDate->copy()->setTime(12, 0),
            ],
            [
                'attendance_id' => $attendance->id,
                'type'          => 'break_end',
                'logged_at'     => $workDate->copy()->setTime(13, 0),
            ],
            [
                'attendance_id' => $attendance->id,
                'type'          => 'clock_out',
                'logged_at'     => $workDate->copy()->setTime(20, 0),
            ],
        ]);

        /* ───── 期待値文字列 ───── */
        $expectedDateY = $workDate->year . '年';
        $expectedDateM = $workDate->format('n月j日');
        $startTxt      = '09:00';
        $endTxt        = '20:00';
        $breakS        = '12:00';
        $breakE        = '13:00';

        /* ───── 2) ブラウザ操作 & 検証 ───── */
        $this->browse(function (Browser $browser) use (
            $admin,
            $attendance,
            $staff,
            $expectedDateY,
            $expectedDateM,
            $startTxt,
            $endTxt,
            $breakS,
            $breakE
        ) {
            $browser->loginAs($admin, guard: 'admin')
                ->visit(route('admin.attendance.show', $attendance->id))

                // ------- 画面上部の静的テキスト -------
                ->assertSee($staff->name)
                ->assertSee($expectedDateY)
                ->assertSee($expectedDateM)

                // ------- input の value 属性 -------
                ->assertValue('input[name="start_at"]',            $startTxt)
                ->assertValue('input[name="end_at"]',              $endTxt)
                ->assertValue('input[name="breaks[0][start]"]',    $breakS)
                ->assertValue('input[name="breaks[0][end]"]',      $breakE)

                // ------- スクリーンショット -------
                ->screenshot('admin_attendance_detail_ok');
        });
    }
}
