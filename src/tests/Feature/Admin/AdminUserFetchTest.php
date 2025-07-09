<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Attendance;
use App\Models\TimeLog;
use Carbon\Carbon;

class AdminUserFetchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる(): void
    {
        /* ───── データ準備 ───── */
        // 管理者
        $admin = User::factory()->create(['role' => 'admin']);

        // 一般ユーザーを複数人生成
        $staffs = User::factory()->count(5)->create(['role' => 'user']);

        /* ───── テスト本体 ───── */
        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.staff.index'));

        // 200 OK であること
        $response->assertOk();

        // 生成した全スタッフの氏名・メールアドレスが
        // 一覧ページに表示されていること
        foreach ($staffs as $staff) {
            $response->assertSeeText($staff->name)
                ->assertSeeText($staff->email);
        }
    }

    #[Test]
    public function ユーザーの勤怠情報が正しく表示される(): void
    {
        /* ---------- 0. 基本セットアップ ---------- */
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'user']);

        /* ---------- 1. 今月の平日すべてに勤怠を作成 ---------- */
        $now          = Carbon::now();                 // テスト実行日時
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth   = $now->copy()->endOfMonth();

        foreach ($startOfMonth->daysUntil($endOfMonth) as $day) {
            // 土日を除外
            if ($day->isWeekend()) {
                continue;
            }

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
        }

        /* ---------- 2. 一覧ページへアクセス ---------- */
        $response = $this
            ->actingAs($admin, 'admin')
            ->get(route('admin.staff_attendance.index', [
                'id'    => $staff->id,
                'month' => $now->format('Y-m'),
            ]));

        /* ---------- 3. 各行が表示されているか検証 ---------- */
        $response->assertOk()
            ->assertSee($staff->name)                // タイトル
            ->assertSee($now->format('Y/m'));        // 月見出し

        foreach ($startOfMonth->daysUntil($endOfMonth) as $day) {
            if ($day->isWeekend()) {
                continue;                                 // 土日はスキップ
            }

            $response->assertSee($day->isoFormat('MM/DD'))  // 日付
                ->assertSee('09:00')                   // 出勤
                ->assertSee('18:00')                   // 退勤
                ->assertSee('1:00')                    // 休憩
                ->assertSee('8:00');                   // 合計
        }
    }

    #[Test]
    public function 「前月」を押下した時に表示月の前月の情報が表示される(): void
    {
        /* ───────── 1. 管理者 & スタッフ生成 ───────── */
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'user']);

        /* ───────── 2. “前月” の平日すべてに勤怠データ作成 ───────── */
        $prevMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $prevMonthEnd   = $prevMonthStart->copy()->endOfMonth();

        for ($day = $prevMonthStart->copy(); $day->lte($prevMonthEnd); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;                 // 土日はスキップ
            }

            $attendance = Attendance::factory()->create([
                'user_id'   => $staff->id,
                'work_date' => $day->toDateString(),
            ]);

            TimeLog::factory()->createMany([
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $day->copy()->setTime(9, 0),
                    'type'          => 'clock_in',
                ],
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $day->copy()->setTime(12, 0),
                    'type'          => 'break_start',
                ],
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $day->copy()->setTime(13, 0),
                    'type'          => 'break_end',
                ],
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $day->copy()->setTime(18, 0),
                    'type'          => 'clock_out',
                ],
            ]);
        }

        /* ───────── 3. “前月” 一覧ページを取得 ───────── */
        $response = $this
            ->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.staff_attendance.index',
                    [
                        'id'    => $staff->id,
                        'month' => $prevMonthStart->format('Y-m'),   // ?month=YYYY-MM
                    ]
                )
            );

        $response->assertOk();

        /* ───────── 4. 先頭の平日行が正しく表示されているか確認 ───────── */
        $firstWeekday = $prevMonthStart->copy();
        while ($firstWeekday->isWeekend()) {
            $firstWeekday->addDay();
        }

        $dateLabel = $firstWeekday->isoFormat('MM/DD(ddd)');   // 例: 06/01(木)

        $response
            ->assertSee($dateLabel)   // 日付
            ->assertSee('09:00')      // 出勤
            ->assertSee('18:00')      // 退勤
            ->assertSee('1:00')       // 休憩
            ->assertSee('8:00');      // 合計
    }
}
