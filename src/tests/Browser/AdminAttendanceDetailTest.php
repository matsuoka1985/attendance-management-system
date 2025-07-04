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

    #[Test]
    public function 詳細を押下すると、その日の勤怠詳細画面に遷移する(): void
    {
        /* ---------- ① テストデータ ---------- */
        // 管理者・スタッフ
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'user']);

        // 当月 1 日
        $day = Carbon::today()->startOfMonth();        // 例) 2025-07-01

        // 勤怠 & ログ 09-18（休憩 12-13）
        $attendance = Attendance::factory()->create([
            'user_id'   => $staff->id,
            'work_date' => $day->toDateString(),
        ]);

        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(9, 0),  'type' => 'clock_in'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(12, 0), 'type' => 'break_start'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(13, 0), 'type' => 'break_end'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(18, 0), 'type' => 'clock_out'],
        ]);

        // 一覧で表示される日付ラベル (MM/DD(ddd))
        $rowLabel   = $day->isoFormat('MM/DD(ddd)');
        // 勤怠詳細ページの URL（リンクは href で完全一致）
        $detailHref = route('admin.attendance.show', $attendance->id);

        /* ---------- ② ブラウザテスト ---------- */
        $this->browse(function (Browser $browser) use ($admin, $staff, $rowLabel, $detailHref, $day, $attendance) {

            // ─ スタッフ月次勤怠一覧（当月）を開く
            $browser->loginAs($admin, 'admin')
                ->visit(route('admin.staff_attendance.index', $staff->id))
                ->waitForText($rowLabel)               // 行が描画されるまで待機
                ->assertSee($rowLabel);

            // ─ 行内リンク〔詳細〕をクリック（href で一意に特定）
            $browser->click("a[href=\"{$detailHref}\"]")
                ->waitForLocation("/admin/attendance/{$attendance->id}");

            // ─ 勤怠詳細ページで情報一致確認
            $browser->assertSee('勤怠詳細')               // 見出し
                ->assertSee($staff->name)            // 氏名
                // 日付は「YYYY年」「n月j日」の 2 つの文字列で出ているレイアウト
                ->assertSee($day->year . '年')
                ->assertSee($day->format('n月j日'))
                ->assertValue('input[name="start_at"]', '09:00')
                ->assertValue('input[name="end_at"]',   '18:00')
                ->assertValue('input[name="breaks[0][start]"]', '12:00')
                ->assertValue('input[name="breaks[0][end]"]',   '13:00')
                ->screenshot('admin_attendance_detail_navigation_ok');
        });
    }
}
