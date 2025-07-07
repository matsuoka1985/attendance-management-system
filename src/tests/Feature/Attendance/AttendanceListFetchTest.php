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

class AttendanceListFetchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function 自分が行った勤怠情報が全て表示されている()
    {
        // 1. ユーザ作成 & ログイン
        $user = User::factory()->create();
        $this->actingAs($user);

        // 2. 勤怠データ作成（月初～3日分）
        $attendances = Attendance::factory()->count(3)->sequence(
            ['work_date' => '2025-07-01'],
            ['work_date' => '2025-07-02'],
            ['work_date' => '2025-07-03'],
        )->create([
            'user_id' => $user->id,
        ]);

        // 3. 各日付に打刻ログ作成（clock_in, break_start, break_end, clock_out）
        foreach ($attendances as $attendance) {
            TimeLog::factory()->create([
                'attendance_id' => $attendance->id,
                'logged_at'     => Carbon::parse($attendance->work_date)->setTime(9, 0),
                'type'          => 'clock_in',
            ]);
            TimeLog::factory()->create([
                'attendance_id' => $attendance->id,
                'logged_at'     => Carbon::parse($attendance->work_date)->setTime(12, 0),
                'type'          => 'break_start',
            ]);
            TimeLog::factory()->create([
                'attendance_id' => $attendance->id,
                'logged_at'     => Carbon::parse($attendance->work_date)->setTime(12, 45),
                'type'          => 'break_end',
            ]);
            TimeLog::factory()->create([
                'attendance_id' => $attendance->id,
                'logged_at'     => Carbon::parse($attendance->work_date)->setTime(18, 0),
                'type'          => 'clock_out',
            ]);
        }

        // 4. 勤怠一覧画面へアクセス
        $response = $this->get('/attendance/list');

        // 5. 勤怠一覧に3日分の出勤・退勤・休憩・合計が表示されていること
        $response->assertStatus(200);
        $response->assertSee('07/01');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('0:45');
        $response->assertSee('8:15');

        $response->assertSee('07/02');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('0:45');
        $response->assertSee('8:15');

        $response->assertSee('07/03');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('0:45');
        $response->assertSee('8:15');
    }

    #[Test]
    public function 勤怠一覧画面に遷移した際に現在の月が表示される(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('attendance.index'));

        // 現在の年月を "YYYY/MM" 形式で取得
        $currentMonth = now()->format('Y/m');

        // レスポンス内にその文字列が含まれていることを検証
        $response->assertOk()
            ->assertSee($currentMonth);
    }
}
