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

class BreakTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function 休憩ボタンが正しく機能する()
    {
        // 1. ステータスが出勤中のユーザーにログインする
        $user = User::factory()->create();
        $this->actingAs($user);

        // 出勤処理（本番コードと同様の処理）
        $today = now()->startOfDay();
        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'work_date' => $today,
        ]);

        TimeLog::create([
            'attendance_id' => $attendance->id,
            'logged_at' => now()->subHour(),
            'type' => 'clock_in',
        ]);

        // 2. 出勤状態で出勤画面を開く
        $response = $this->get(route('attendance.stamp'));
        $response->assertOk();
        $response->assertSee('休憩入');

        // 3. 休憩の処理を行う
        $this->post(route('break.start'))
            ->assertRedirect();

        // 再度出勤画面へアクセスし、ステータスが「休憩中」か確認
        $response = $this->get(route('attendance.stamp'));
        $response->assertOk();
        $response->assertSee('休憩中');
        $response->assertSee('休憩戻');
    }

    #[Test]
    public function 休憩は一日に何回でもできる()
    {
        // 1. ステータスが出勤中であるユーザーにログインする
        $user = User::factory()->create();
        $this->actingAs($user);

        // 出勤処理
        $this->post(route('attendance.start'));

        // 2. 休憩入と休憩戻の処理を行う
        $this->post(route('break.start'));
        $this->post(route('break.end'));


        // 3. 「休憩入」ボタンが表示されることを確認する（勤務中状態）
        $response = $this->get(route('attendance.stamp'));
        $response->assertOk();
        $response->assertSee('休憩入');
    }

    #[Test]
    public function 休憩戻ボタンが正しく機能する()
    {
        // 1. ステータスが出勤中であるユーザを作成してログイン
        $user = User::factory()->create();
        $this->actingAs($user);

        // 出勤済みの勤怠とbreak_startログを作成
        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => Carbon::today(),
        ]);

        $attendance->timeLogs()->create([
            'type'      => 'clock_in',
            'logged_at' => now()->subHours(2),
        ]);

        $attendance->timeLogs()->create([
            'type'      => 'break_start',
            'logged_at' => now()->subHour(),
        ]);

        // 休憩戻ボタンが表示されることを確認
        $this->get(route('attendance.stamp'))
            ->assertSee('休憩戻');

        // 2. 休憩戻の処理を行う
        $response = $this->post(route('break.end'));

        // 3. ステータスが「出勤中」に変更されることを確認
        $response->assertRedirect();
        $this->get(route('attendance.stamp'))->assertSee('出勤中');

        // break_endログが登録されたことを確認
        $this->assertDatabaseHas('time_logs', [
            'attendance_id' => $attendance->id,
            'type'          => 'break_end',
        ]);
    }

    #[Test]
    public function 休憩戻は一日に何回でもできる(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // 出勤
        $this->post(route('attendance.start'));

        // 1回目休憩 → 戻る
        $this->post(route('break.start'));
        $this->post(route('break.end'));


        // 2回目休憩開始
        $this->post(route('break.start'));

        // 画面アクセス（出勤画面）
        $response = $this->get(route('attendance.stamp'));

        // 「休憩戻」ボタンが表示されていることを検証
        $response->assertSee('休憩戻');
    }


    #[Test]
    public function 休憩時刻が勤怠一覧画面で確認できる(): void
    {
        // 1. ユーザ作成してログイン
        $user = User::factory()->create();
        $this->actingAs($user);

        // 2. 出勤処理
        $response = $this->post(route('attendance.start'));
        $response->assertRedirect();

        $attendance = Attendance::where('user_id', $user->id)->latest()->first();

        // 3. 休憩開始処理
        $response = $this->post(route('break.start'));
        $response->assertRedirect();

        $startBreakAt = TimeLog::where('attendance_id', $attendance->id)
            ->where('type', 'break_start')
            ->latest('logged_at')->first()->logged_at;


        sleep(5);

        // 5. 休憩終了処理
        $response = $this->post(route('break.end'));
        $response->assertRedirect();

        $endBreakAt = TimeLog::where('attendance_id', $attendance->id)
            ->where('type', 'break_end')
            ->latest('logged_at')->first()->logged_at;

        // 6. 休憩合計時間を算出
        $diff = $startBreakAt->diffInMinutes($endBreakAt);
        $breakTime = sprintf('%d:%02d', floor($diff / 60), $diff % 60);

        // 7. 勤怠一覧ページで休憩合計時間を確認
        $response = $this->get(route('attendance.index'));
        $response->assertSee($breakTime);

        // 8. 勤怠詳細ページで休憩開始・終了時間を確認
        $response = $this->get(route('attendance.show', ['id' => $attendance->id]));
        $response->assertSee($startBreakAt->format('H:i'));
        $response->assertSee($endBreakAt->format('H:i'));
    }
}
