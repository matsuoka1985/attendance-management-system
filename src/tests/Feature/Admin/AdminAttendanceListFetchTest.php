<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TimeLog;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;



class AdminAttendanceListFetchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function その日になされた全ユーザーの勤怠情報が正確に確認できる(): void
    {
        // ── 0) 事前データ ───────────────────────────────────
        // 管理者
        $admin = User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        // 一般ユーザー 3 名
        $users = User::factory()->count(3)->create([
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);

        // 今日の日付
        $today = Carbon::today();

        // 各ユーザーに “09-18 勤務・12-13 休憩” の勤怠を付与
        foreach ($users as $u) {
            $att = Attendance::factory()
                ->for($u)
                ->create(['work_date' => $today]);

            // clock-in
            TimeLog::create([
                'attendance_id' => $att->id,
                'type'          => 'clock_in',
                'logged_at'     => $today->copy()->setTime(9, 0),
            ]);
            // break 12-13
            TimeLog::create([
                'attendance_id' => $att->id,
                'type'          => 'break_start',
                'logged_at'     => $today->copy()->setTime(12, 0),
            ]);
            TimeLog::create([
                'attendance_id' => $att->id,
                'type'          => 'break_end',
                'logged_at'     => $today->copy()->setTime(13, 0),
            ]);
            // clock-out
            TimeLog::create([
                'attendance_id' => $att->id,
                'type'          => 'clock_out',
                'logged_at'     => $today->copy()->setTime(18, 0),
            ]);
        }

        // ── 1) 管理者として一覧ページへ ─────────────────────
        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.attendance.index', ['date' => $today->toDateString()]));

        $response->assertOk();

        // ── 2) 見出しの日付が正しいこと ───────────────────
        $response->assertSeeText($today->format('Y年n月j日'));

        // ── 3) 各ユーザーの勤怠行が期待どおり表示されていること ─────
        foreach ($users as $u) {
            $response
                ->assertSeeText($u->name)   // 名前
                ->assertSeeText('09:00')    // 出勤
                ->assertSeeText('18:00')    // 退勤
                ->assertSeeText('1:00')     // 休憩
                ->assertSeeText('8:00');    // 合計
        }
    }
}
