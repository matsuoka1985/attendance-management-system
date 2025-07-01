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

class ClockOutTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function 退勤ボタンが正しく機能する()
    {
        // 1. ステータスが勤務中のユーザーにログインする
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);

        $today = Carbon::today();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $today,
        ]);

        // 出勤済みログのみ作成（勤務中ステータスを再現）
        TimeLog::factory()->create([
            'attendance_id' => $attendance->id,
            'type' => 'clock_in',
            'logged_at' => now()->subHours(8),
        ]);

        // 2. 出勤画面で「退勤」ボタンが表示されていることを確認
        $response = $this->get(route('attendance.stamp'));
        $response->assertStatus(200);
        $response->assertSee('退勤');

        // 3. 退勤処理を実行
        $response = $this->post(route('attendance.end'));
        $response->assertRedirect(); // リダイレクト確認
        $response->assertSessionHas('success', '退勤しました。');

        // 再度画面を開いて「退勤済」ステータスを確認
        $response = $this->get(route('attendance.stamp'));
        $response->assertStatus(200);
        $response->assertSee('退勤済');
    }

    #[Test]
    public function 退勤時刻が勤怠一覧画面で確認できる()
    {
        // 1. ユーザ作成＆ログイン
        $user = User::factory()->create();
        $this->actingAs($user);

        // 2. 出勤処理
        $controller = new \App\Http\Controllers\User\AttendanceStampController;
        $attendance = $controller->performClockIn();

        sleep(1); // 打刻順保障

        // 3. 退勤処理（この時刻を検証対象にする）
        $attendance = $controller->performClockOut();

        // 4. 退勤ログ取得
        $clockOutLog = $attendance->timeLogs()
            ->where('type', 'clock_out')
            ->latest('logged_at')
            ->first();

        $date = $clockOutLog->logged_at->format('m/d');   // 例: 07/01
        $time = $clockOutLog->logged_at->format('H:i');   // 例: 18:12

        // 5. 勤怠一覧画面にアクセス
        $response = $this->get(route('attendance.index'));

        // 6. 表示検証：日付と時刻両方
        $response->assertSee($date);
        $response->assertSee($time);
    }
}
