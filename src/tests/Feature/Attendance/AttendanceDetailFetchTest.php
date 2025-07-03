<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TimeLog;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

class AttendanceDetailFetchTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function 勤怠詳細画面の「名前」がログインユーザーの氏名になっている(): void
    {
        /* ---------- 1. データ準備 ---------- */
        $userName   = '山田 太郎';
        $targetDate = Carbon::now()->startOfMonth();  // 当月 1 日

        $user = User::factory()->create([
            'name'              => $userName,
            'email_verified_at' => now(),
        ]);

        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $targetDate->toDateString(),
        ]);

        // 出勤・休憩開始・休憩終了・退勤（内容は今回の検証対象ではない）
        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'type' => 'clock_in',    'logged_at' => $targetDate->copy()->setTime(9, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_start', 'logged_at' => $targetDate->copy()->setTime(12, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_end',   'logged_at' => $targetDate->copy()->setTime(13, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'clock_out',   'logged_at' => $targetDate->copy()->setTime(18, 0)],
        ]);

        /* ---------- 2. 画面取得 ---------- */
        $response = $this->actingAs($user)
            ->get("/attendance/{$attendance->id}");

        /* ---------- 3. 検証 ---------- */
        $response->assertStatus(200)
            ->assertSee($userName);  // 「名前」欄にログインユーザーの氏名が表示されている
    }
}
