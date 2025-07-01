<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TimeLog;
use Carbon\Carbon;

class StatusCheckTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function 勤務外の場合、勤怠ステータスが正しく表示される()
    {
        // 勤務外状態（本日打刻なし）のユーザを作成
        $user = User::factory()->create();

        // ログイン（メール認証済みにしておく）
        $this->actingAs($user);
        $user->markEmailAsVerified();

        // 勤怠打刻画面へアクセス
        $response = $this->get(route('attendance.stamp'));

        // ステータス表示を検証
        $response->assertOk();
        $response->assertSee('勤務外');
    }


    #[Test]
    public function 出勤中の場合、勤怠ステータスが正しく表示される()
    {
        // 1. 出勤中ユーザを作成
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        // 2. 出勤レコとログを作成（clock_in のみ → 出勤中）
        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => Carbon::today(),
        ]);

        TimeLog::factory()->create([
            'attendance_id' => $attendance->id,
            'type'          => 'clock_in',
            'logged_at'     => now()->subHour(),
        ]);

        // 3. 出勤打刻画面へアクセス
        $response = $this->get(route('attendance.stamp'));

        // 4. ステータスが「出勤中」と表示されていることを確認
        $response->assertOk();
        $response->assertSee('出勤中');
    }

    #[Test]
    public function 休憩中の場合、勤怠ステータスが正しく表示される()
    {
        // テスト用ユーザと本日の勤怠を作成
        $user = User::factory()->create();
        $this->actingAs($user);

        $today = Carbon::today();

        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $today,
        ]);

        // 出勤済み → 休憩開始済み（現在休憩中）のログを作成
        TimeLog::factory()->create([
            'attendance_id' => $attendance->id,
            'type'          => 'clock_in',
            'logged_at'     => now()->subHour(),
            'created_at'    => now()->subHour(),
        ]);
        TimeLog::factory()->create([
            'attendance_id' => $attendance->id,
            'type'          => 'break_start',
            'logged_at'     => now()->subMinutes(30),
            'created_at'    => now()->subMinutes(30),
        ]);

        // 勤怠打刻画面にアクセス
        $response = $this->get(route('attendance.stamp'));

        // 「休憩中」と表示されていることを検証
        $response->assertOk();
        $response->assertSeeText('休憩中');
    }
}
