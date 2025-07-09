<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TimeLog;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;
use App\Models\CorrectionRequest;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        /** 1) 事前データ — 9 :00 出勤／18 :00 退勤、休憩 12–13 **/
        $user = User::factory()->create(['email_verified_at' => now()]);
        $date = Carbon::now()->startOfMonth();

        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $date->toDateString(),
        ]);

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

        /** 2) 修正申請 — 出勤 18 :00／退勤 09 :00（逆転）＋休憩そのまま **/
        $response = $this->actingAs($user)
            ->from("/attendance/{$attendance->id}")
            ->followingRedirects()
            ->post('/store', [
                'mode'          => 'finished',
                'attendance_id' => $attendance->id,
                'work_date'     => $date->toDateString(),
                'start_at'      => '18:00',          // ← 出勤
                'end_at'        => '09:00',          // ← 退勤（逆転）
                'breaks'        => [
                    ['start' => '12:00', 'end' => '13:00'],
                ],
                'reason'        => 'テスト修正',
            ]);

        /** 3) 期待値 — バリデーションエラー文言が出ていること **/
        $response->assertStatus(200)
            ->assertSee('出勤時間もしくは退勤時間が不適切な値です');
    }

    /**
     * スプレッドシートにおいて、機能要件のページでは「休憩時間が勤務時間外です」というエラーメッセージになっており、
     * 一方テストケースのページでは「休憩時間が不適切な値です」というエラーメッセージになっている。以下のテストコードにおいては
     * 機能要件のページに合わせて「休憩時間が勤務時間外です」というエラーメッセージを表示するように修正している。
     *
     */
    #[Test]
    public function 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        /** 1) 勤怠情報が登録されたユーザーを用意（9:00-18:00／休憩 12:00-13:00） */
        $user = User::factory()->create(['email_verified_at' => now()]);
        $date = Carbon::now()->startOfMonth();

        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $date->toDateString(),
        ]);

        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'type' => 'clock_in',  'logged_at' => $date->copy()->setTime(9, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_start', 'logged_at' => $date->copy()->setTime(12, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_end',  'logged_at' => $date->copy()->setTime(13, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'clock_out',  'logged_at' => $date->copy()->setTime(18, 0)],
        ]);

        /** 2) 勤怠詳細ページを開く（from() で元ページ指定）し、
         *     休憩開始を 19:00（退勤後）にして保存 POST  */
        $response = $this->actingAs($user)
            ->from("/attendance/{$attendance->id}")      // ← 手順②
            ->followingRedirects()                       // リダイレクト後まで見る
            ->post('/store', [                           // 手順④
                'mode'          => 'finished',
                'attendance_id' => $attendance->id,
                'work_date'     => $date->toDateString(),
                'start_at'      => '09:00',
                'end_at'        => '18:00',
                'breaks'        => [
                    ['start' => '19:00', 'end' => '20:00'],     // 手順③
                ],
                'reason'        => 'テスト修正',
            ]);

        /** 3) 期待挙動 — バリデーションメッセージが表示される */
        $response->assertStatus(200)
            ->assertSee('休憩時間が勤務時間外です');   // 実装メッセージ
    }

    /**
     * スプレッドシートにおいて、機能要件のページでは「休憩時間が勤務時間外です」というエラーメッセージになっており、
     * 一方テストケースのページでは「休憩時間が不適切な値です」というエラーメッセージになっている。以下のテストコードにおいては
     * 機能要件のページに合わせて「休憩時間が勤務時間外です」というエラーメッセージを表示するように修正している。
     *
     */

    #[Test]
    public function 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される(): void
    {
        /** 1) 勤怠情報が登録されたユーザーを用意（9:00-18:00／休憩 12:00-13:00） */
        $user = User::factory()->create(['email_verified_at' => now()]);
        $date = Carbon::now()->startOfMonth();

        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $date->toDateString(),
        ]);

        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'type' => 'clock_in',  'logged_at' => $date->copy()->setTime(9, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_start', 'logged_at' => $date->copy()->setTime(12, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_end',  'logged_at' => $date->copy()->setTime(13, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'clock_out',  'logged_at' => $date->copy()->setTime(18, 0)],
        ]);

        /** 2) 勤怠詳細ページを開く → 休憩終了を 19:00（退勤後）にして保存 */
        $response = $this->actingAs($user)
            ->from("/attendance/{$attendance->id}")
            ->followingRedirects()
            ->post('/store', [
                'mode'          => 'finished',
                'attendance_id' => $attendance->id,
                'work_date'     => $date->toDateString(),
                'start_at'      => '09:00',
                'end_at'        => '18:00',
                'breaks'        => [
                    ['start' => '17:00', 'end' => '19:00'],
                ],
                'reason'        => 'テスト修正',
            ]);

        /** 3) 期待挙動 — バリデーションメッセージが表示される */
        $response->assertStatus(200)
            ->assertSee('休憩時間が勤務時間外です');
    }

    #[Test]
    public function 備考欄が未入力の場合のエラーメッセージが表示される(): void
    {
        /** 1) 勤怠情報付きユーザーを用意（9:00-18:00、休憩 12:00-13:00）*/
        $user = User::factory()->create(['email_verified_at' => now()]);
        $date = Carbon::now()->startOfMonth();

        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $date->toDateString(),
        ]);

        TimeLog::factory()->createMany([
            ['attendance_id' => $attendance->id, 'type' => 'clock_in',  'logged_at' => $date->copy()->setTime(9, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_start', 'logged_at' => $date->copy()->setTime(12, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'break_end',  'logged_at' => $date->copy()->setTime(13, 0)],
            ['attendance_id' => $attendance->id, 'type' => 'clock_out',  'logged_at' => $date->copy()->setTime(18, 0)],
        ]);

        /** 2-3) 勤怠詳細を開き、備考を空のまま保存  */
        $response = $this->actingAs($user)
            ->from("/attendance/{$attendance->id}")       // 手順②
            ->followingRedirects()
            ->post('/store', [                            // 手順③
                'mode'          => 'finished',
                'attendance_id' => $attendance->id,
                'work_date'     => $date->toDateString(),
                'start_at'      => '09:00',
                'end_at'        => '18:00',
                'breaks'        => [
                    ['start' => '12:00', 'end' => '13:00'],
                ],
                // ★ reason をわざと送らない
            ]);

        /** 期待挙動 — バリデーションメッセージを確認 */
        $response->assertStatus(200)
            ->assertSee('備考を記入してください。');
    }

    #[Test]
    public function 修正申請処理が実行される(): void
    {
        /* === テスト基準日 : 実行時点の “前月末日” === */
        $baseDate = now()->startOfMonth()->subDay();         // 前月末日
        $workDate = $baseDate->toDateString();               // e.g. 2025-06-30

        /* ---------- 手順①：一般ユーザーで修正申請 ---------- */
        $user = User::factory()->create([
            'role'              => 'user',
            'email_verified_at' => now(),                    // ここはリアルタイムで OK
        ]);

        // 元勤怠（09:00〜18:00）
        $attendance = Attendance::factory()->create([
            'user_id'   => $user->id,
            'work_date' => $workDate,
        ]);
        TimeLog::factory()->createMany([
            [
                'attendance_id' => $attendance->id,
                'logged_at'     => $baseDate->copy()->setTime(9, 0),
                'type'          => 'clock_in',
            ],
            [
                'attendance_id' => $attendance->id,
                'logged_at'     => $baseDate->copy()->setTime(18, 0),
                'type'          => 'clock_out',
            ],
        ]);

        // 申請送信（08:00〜17:00 ＋ 12:00‒13:00 休憩）
        $this->actingAs($user);
        $this->followingRedirects()
            ->post('/store', [
                'mode'          => 'finished',
                'attendance_id' => $attendance->id,
                'work_date'     => $workDate,
                'start_at'      => '08:00',
                'end_at'        => '17:00',
                'breaks'        => [
                    ['start' => '12:00', 'end' => '13:00'],
                ],
                'reason'        => '終電の都合',
            ])
            ->assertOk()
            //申請一覧画面での確認
            ->assertSeeTextInOrder([
                '承認待ち',
                $user->name,
                Carbon::parse($workDate)->format('Y/m/d'),
                '終電の都合',
            ]);

        /* ---------- 手順②：DB に pending 申請が出来ているか ---------- */
        $correction = CorrectionRequest::where('user_id', $user->id)->first();
        $this->assertNotNull($correction);
        $this->assertSame(CorrectionRequest::STATUS_PENDING, $correction->status);

        /* ---------- 手順③：管理者ユーザーで承認画面を確認 ---------- */
        $admin = User::factory()->create([
            'role'              => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get("/admin/stamp_correction_request/approve/{$correction->id}")
            ->assertOk()
            ->assertSeeText('申請中の変更')
            ->assertSeeTextInOrder(['08:00', '17:00'])       // 出退勤
            ->assertSeeTextInOrder(['12:00', '13:00']);      // 休憩
    }



    #[Test]
    public function 「承認待ち」にログインユーザーが行った申請が全て表示されていること(): void
    {
        /* 1) テストユーザーでログイン */
        $user = User::factory()->create([
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        /* 2) “前月 1 日” 起点で 3 日分の勤怠＋打刻＋申請を作成 */
        $base = Carbon::now()->startOfMonth()->subMonth();           // 例: 2025-05-01

        foreach (range(0, 2) as $i) {
            $workDate = $base->copy()->addDays($i);                  // 05-01, 05-02, 05-03

            // 2-1. 勤怠レコード
            $attendance = Attendance::create([
                'user_id'    => $user->id,
                'work_date'  => $workDate->toDateString(),
                'created_at' => $workDate->copy()->setTime(8, 0),
                'updated_at' => $workDate->copy()->setTime(8, 0),
            ]);

            // 2-2. 打刻ログ（出勤-休憩-退勤）
            TimeLog::insert([
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $workDate->copy()->setTime(9, 0),
                    'type'          => 'clock_in',
                    'created_at'    => $workDate->copy()->setTime(9, 0),
                    'updated_at'    => $workDate->copy()->setTime(9, 0),
                ],
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $workDate->copy()->setTime(12, 0),
                    'type'          => 'break_start',
                    'created_at'    => $workDate->copy()->setTime(12, 0),
                    'updated_at'    => $workDate->copy()->setTime(12, 0),
                ],
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $workDate->copy()->setTime(13, 0),
                    'type'          => 'break_end',
                    'created_at'    => $workDate->copy()->setTime(13, 0),
                    'updated_at'    => $workDate->copy()->setTime(13, 0),
                ],
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $workDate->copy()->setTime(18, 0),
                    'type'          => 'clock_out',
                    'created_at'    => $workDate->copy()->setTime(18, 0),
                    'updated_at'    => $workDate->copy()->setTime(18, 0),
                ],
            ]);

            // 2-3. 修正申請（承認待ち）
            $this->post('/store', [
                'mode'          => 'finished',
                'attendance_id' => $attendance->id,
                'work_date'     => $workDate->toDateString(),
                'start_at'      => '09:00',
                'end_at'        => '18:00',
                'breaks'        => [['start' => '12:00', 'end' => '13:00']],
                'reason'        => "テスト理由" . ($i + 1),
            ])->assertRedirect('/stamp_correction_request/list');

            // 2-4. 申請日時を操作（見た目確認用）
            $appliedAt = Carbon::now()->subDays(2 - $i);              // 今日-2d,-1d,0d
            CorrectionRequest::latest()->first()->forceFill([
                'created_at' => $appliedAt,
                'updated_at' => $appliedAt,
            ])->saveQuietly();
        }

        /* 3) 申請一覧ページで各列を検証 */
        $response = $this->get('/stamp_correction_request/list')->assertOk();

        foreach (range(0, 2) as $i) {
            $response
                ->assertSeeText("テスト理由" . ($i + 1))                                // 申請理由
                ->assertSeeText($base->copy()->addDays($i)->format('Y/m/d'))            // 対象日
                ->assertSeeText(Carbon::now()->subDays(2 - $i)->format('Y/m/d'));       // 申請日時
        }

        $response->assertSeeText('承認待ち')                                            // 状態
            ->assertSeeText($user->name);                                          // 名前
    }

    #[Test]
    public function 「承認済み」に管理者が承認した修正申請が全て表示されている(): void
    {
        /* ───── 1) 一般ユーザーでログイン ───── */
        $user = User::factory()->create([
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($user);

        /* ───── 2) 前月 1 日を起点に 3 日分の勤怠・申請・承認 ───── */
        $base = Carbon::now()->startOfMonth()->subMonth();           // 例: 2025-05-01

        foreach (range(0, 2) as $i) {
            $workDate = $base->copy()->addDays($i);

            // 2-1. 勤怠
            $attendance = Attendance::create([
                'user_id'   => $user->id,
                'work_date' => $workDate->toDateString(),
            ]);

            // 2-2. 打刻ログ（出勤・休憩開始／終了・退勤）
            TimeLog::insert([
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $workDate->copy()->setTime(9, 0),
                    'type'          => 'clock_in',
                ],
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $workDate->copy()->setTime(12, 0),
                    'type'          => 'break_start',
                ],
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $workDate->copy()->setTime(13, 0),
                    'type'          => 'break_end',
                ],
                [
                    'attendance_id' => $attendance->id,
                    'logged_at'     => $workDate->copy()->setTime(18, 0),
                    'type'          => 'clock_out',
                ],
            ]);

            // 2-3. 修正申請（承認待ち）
            $this->post('/store', [
                'mode'          => 'finished',
                'attendance_id' => $attendance->id,
                'work_date'     => $workDate->toDateString(),
                'start_at'      => '09:00',
                'end_at'        => '18:00',
                'breaks'        => [
                    ['start' => '12:00', 'end' => '13:00'],
                ],
                'reason'        => 'テスト理由' . ($i + 1),
            ])->assertRedirect('/stamp_correction_request/list');

            /* 2-4. 管理者が承認済みに変更 */
            $admin = User::factory()->create(['role' => 'admin']);
            $approvedAt = Carbon::now()->subDays(2 - $i);

            CorrectionRequest::latest()->first()
                ->forceFill([
                    'status'      => CorrectionRequest::STATUS_APPROVED,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => $approvedAt,
                    'created_at'  => $approvedAt,
                    'updated_at'  => $approvedAt,
                ])
                ->saveQuietly();
        }

        /* ───── 3) “承認済み” タブで表示確認 ───── */
        $response = $this->get('/stamp_correction_request/list')->assertOk();

        foreach (range(0, 2) as $i) {
            $response
                ->assertSeeText('テスト理由' . ($i + 1))
                ->assertSeeText($base->copy()->addDays($i)->format('Y/m/d'))
                ->assertSeeText(Carbon::now()->subDays(2 - $i)->format('Y/m/d'));
        }

        $response->assertSeeText('承認')
            ->assertSeeText($user->name);
    }
}
