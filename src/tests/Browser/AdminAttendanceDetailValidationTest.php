<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Admin;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TimeLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;

class AdminAttendanceDetailValidationTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[Test]
    public function 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        /* ───── テストデータ生成 ───── */
        // ─ 管理者（admins テーブル）
        $admin = User::factory()->create([
            'role'=>'admin',
        ]);

        // ─ 一般スタッフ（users テーブル）
        $staff = User::factory()->create([
            'role' => 'user',
        ]);

        // ─ 勤怠 & 打刻ログ（前日）
        $day        = Carbon::yesterday()->startOfDay();
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
                'logged_at'     => $day->copy()->setTime(20, 0),
                'type'          => 'clock_out',
            ],
        ]);

        /* ───── ブラウザテスト ───── */
        $this->browse(function (Browser $browser) use ($admin, $attendance) {

            // 1. 管理者としてログイン（ガード 'admin' を指定）
            $browser->loginAs($admin, 'admin');

            // 2. 勤怠詳細ページへ
            $browser->visit(route('admin.attendance.show', $attendance->id))

                // 3. 出勤・退勤を不正値に変更（出勤=21:00, 退勤=20:00）
                ->script(
                    <<<JS
                   document.querySelector('input[name="start_at"]').value = '21:00';
                   document.querySelector('input[name="end_at"]').value   = '20:00';
                   JS
                );

            // 4. 修正ボタン押下 → バリデーションエラー確認
            $browser->press('修正')
                ->waitForText('出勤時間もしくは退勤時間が不適切な値です')
                ->assertSee('出勤時間もしくは退勤時間が不適切な値です');
        });
    }

    /**
     * スプレッドシートのテストケース一覧のページではエラーメッセージの指定が
     * '出勤時間もしくは退勤時間が不適切な値です'となっていますが機能要件のページでは
     * '休憩時間が勤務時間外です'となってます。休憩時間の入力ミスであることを考慮すると後者の方がより適切な表現であるため、後者を採用しています。
     */
    #[Test]
    public function 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        /* ---------- テストデータ ---------- */
        $admin = User::factory()->create([
            'role'     => 'admin',
        ]);

        $staff = User::factory()->create(['role' => 'user']);

        $day        = Carbon::yesterday()->startOfDay();
        $attendance = Attendance::factory()->create([
            'user_id'   => $staff->id,
            'work_date' => $day->toDateString(),
        ]);

        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(9, 0),  'type' => 'clock_in'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(12, 0), 'type' => 'break_start'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(13, 0), 'type' => 'break_end'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(20, 0), 'type' => 'clock_out'],
        ]);

        /* ---------- ブラウザテスト ---------- */
        $this->browse(function (Browser $browser) use ($admin, $attendance) {
            $browser->loginAs($admin, 'admin')
                ->visit(route('admin.attendance.show', $attendance->id))

                // 休憩開始=21:00 / 休憩終了=22:00（退勤=20:00 より後 → 不正）
                ->script(<<<'JS'
                        document.querySelector('input[name="breaks[0][start]"]').value = '21:00';
                        document.querySelector('input[name="breaks[0][end]"]').value   = '22:00';
                    JS);

            $browser->press('修正')
                ->waitForText('休憩時間が勤務時間外です')
                ->assertSee('休憩時間が勤務時間外です')
                ->screenshot('admin_attendance_break_start_after_end');
        });
    }

    /**
     * スプレッドシートのテストケース一覧のページではエラーメッセージの指定が
     * '出勤時間もしくは退勤時間が不適切な値です'となっていますが機能要件のページでは
     * '休憩時間が勤務時間外です'となってます。休憩時間の入力ミスであることを考慮すると後者の方がより適切な表現であるため、後者を採用しています。
     */
    #[Test]
    public function 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        /* ---------- テストデータ ---------- */
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $staff = User::factory()->create(['role' => 'user']);

        $day        = Carbon::yesterday()->startOfDay();
        $attendance = Attendance::factory()->create([
            'user_id'   => $staff->id,
            'work_date' => $day->toDateString(),
        ]);

        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(9, 0),  'type' => 'clock_in'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(12, 0), 'type' => 'break_start'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(13, 0), 'type' => 'break_end'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(20, 0), 'type' => 'clock_out'],
        ]);

        /* ---------- ブラウザテスト ---------- */
        $this->browse(function (Browser $browser) use ($admin, $attendance) {
            $browser->loginAs($admin, 'admin')
                ->visit(route('admin.attendance.show', $attendance->id))

                // 休憩開始=18:00（勤務時間内） / 休憩終了=21:00（退勤=20:00 より後 → 不正）
                ->script(<<<'JS'
                        document.querySelector('input[name="breaks[0][start]"]').value = '18:00';
                        document.querySelector('input[name="breaks[0][end]"]').value   = '21:00';
                    JS);

            $browser->press('修正')
                ->waitForText('休憩時間が勤務時間外です')
                ->assertSee('休憩時間が勤務時間外です')
                ->screenshot('admin_attendance_break_end_after_end');
        });
    }

    #[Test]
    public function 備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        /* ───── テストデータ ───── */
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'user']);

        $day        = Carbon::yesterday()->startOfDay();
        $attendance = Attendance::factory()->create([
            'user_id'   => $staff->id,
            'work_date' => $day->toDateString(),
        ]);

        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(9, 0),  'type' => 'clock_in'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(12, 0), 'type' => 'break_start'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(13, 0), 'type' => 'break_end'],
            ['attendance_id' => $attendance->id, 'logged_at' => $day->copy()->setTime(20, 0), 'type' => 'clock_out'],
        ]);

        /* ───── ブラウザテスト ───── */
        $this->browse(function (Browser $browser) use ($admin, $attendance) {
            $browser->loginAs($admin, 'admin')
                ->visit(route('admin.attendance.show', $attendance->id))
                // 備考欄を空のまま送信（既に空だが念のため明示）
                ->script(<<<'JS'
                    document.querySelector('textarea[name="reason"]').value = '';
                JS);

            $browser->press('修正')
                ->waitForText('備考を記入してください')
                ->assertSee('備考を記入してください');
        });
    }
}
