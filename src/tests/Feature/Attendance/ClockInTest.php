<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Attendance;
use App\Http\Controllers\User\AttendanceStampController;

use Carbon\Carbon;

class ClockInTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function 出勤ボタンが正しく機能する(): void
    {
        // 勤務外状態のユーザ作成
        $user = User::factory()->create();

        // ログイン
        $this->actingAs($user);

        // 出勤登録画面へアクセス
        $response = $this->get(route('attendance.stamp'));
        $response->assertOk();
        $response->assertSee('出勤');

        // 出勤処理実行
        $response = $this->post(route('attendance.start'));

        // 成功メッセージを確認し、再度画面に出勤中ステータスが表示されていることを確認
        $response->assertRedirect();
        $response = $this->get(route('attendance.stamp'));
        $response->assertOk();
        $response->assertSee('出勤中');

        // DBにclock_inが記録されていることを確認
        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'work_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('time_logs', [
            'type' => 'clock_in',
        ]);
    }

    #[Test]
    public function 出勤は一日一回のみできる(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user);

        // すでに出勤済みの勤怠データを作成
        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => today(),
        ]);

        // 出勤ログを登録
        $attendance->timeLogs()->create([
            'type'      => 'clock_in',
            'logged_at' => now()->subHours(8),
            'created_at' => now()->subHours(8),
        ]);

        // 出勤画面にアクセス
        $response = $this->get(route('attendance.stamp'));

        // 出勤ボタン（id付き）が存在しないことを確認
        $response->assertDontSee('id="clock-in-button"', false);
    }




    #[Test]
    public function 出勤時刻が勤怠一覧画面で確認できる()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // 出勤処理を実行し、Attendanceインスタンスを取得
        $controller = new AttendanceStampController();
        $attendance = $controller->performClockIn();

        // 出勤時刻を取得
        $clockInTime = $attendance->timeLogs()->where('type', 'clock_in')->first()->logged_at->format('H:i');

        // 勤怠一覧画面にアクセス
        $response = $this->get(route('attendance.index'));
        $response->assertOk();

        // 日付表示 "07/01(火)" を確認
        $date = $attendance->work_date->format('m/d');
        $day  = ['日', '月', '火', '水', '木', '金', '土'][$attendance->work_date->dayOfWeek];
        $response->assertSee("{$date}({$day})");

        // 出勤時刻が正しく表示されていることを確認
        $response->assertSee($clockInTime);
    }
}
