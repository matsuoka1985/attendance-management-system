<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
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

    #[Test]
    public function 勤怠詳細画面の「日付」が選択した日付になっている(): void
    {
        /* ---------- 1. データ準備 ---------- */
        $user       = User::factory()->create();
        $date       = Carbon::now()->startOfMonth()->addDays(3);   // 当月 4 日
        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $date->toDateString(),
        ]);

        /* ---------- 2. 画面取得 ---------- */
        $response = $this->actingAs($user)
            ->get("/attendance/{$attendance->id}");

        /* ---------- 3. 検証 ---------- */
        // Blade 側は「n月j日」表示なので期待値を合わせる
        $expected = $date->format('n月j日');

        $response->assertOk()
            ->assertSee($expected);
    }

    #[Test]
    public function 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致している(): void
    {
        // 1. データ準備 －－－－－－－－－－－－－－－－－－－－－
        $user       = User::factory()->create();                 // ログインユーザ
        $date       = Carbon::now()->startOfMonth();             // 当月 1 日
        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $date->toDateString(),
        ]);

        // 出勤 09:00 ／ 退勤 18:00 の打刻ログを作成
        TimeLog::factory()->createMany([
            [
                'attendance_id' => $attendance->id,
                'type'          => 'clock_in',
                'logged_at'     => $date->copy()->setTime(9, 0),
            ],
            [
                'attendance_id' => $attendance->id,
                'type'          => 'clock_out',
                'logged_at'     => $date->copy()->setTime(18, 0),
            ],
        ]);

        // 2. 画面取得 －－－－－－－－－－－－－－－－－－－－－－－
        $response = $this->actingAs($user)
            ->get("/attendance/{$attendance->id}");

        // 3. 検証 －－－－－－－－－－－－－－－－－－－－－－－－
        $response->assertOk()
            ->assertSee('09:00')   // 出勤時刻
            ->assertSee('18:00');  // 退勤時刻
    }

    #[Test]
    public function 「休憩」にて記されている時間がログインユーザーの打刻と一致している(): void
    {
        /** 1) データ準備  --------------------------------------------------*/
        $user       = User::factory()->create();
        $date       = Carbon::now()->startOfMonth();                // 当月 1 日
        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $date->toDateString(),
        ]);

        // 休憩：12:00〜13:00 のログを生成
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

        /** 2) 画面取得  ----------------------------------------------------*/
        $response = $this->actingAs($user)
            ->get("/attendance/{$attendance->id}");

        /** 3) 期待値検証  --------------------------------------------------*/
        $response->assertOk()
            // 休憩開始・終了の両方が詳細画面に表示されているか？
            ->assertSee('12:00')
            ->assertSee('13:00');
    }
}
