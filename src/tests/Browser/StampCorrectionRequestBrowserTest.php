<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TimeLog;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;
use App\Models\CorrectionRequest;

class StampCorrectionRequestBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[Test]
    public function 各申請の「詳細」を押下すると申請詳細画面に遷移する(): void
    {
        /* ---------- 1) 事前データ ---------- */
        $user = User::factory()->create([
            'role'              => 'user',
            'email_verified_at' => now(),
        ]);

        $base = Carbon::now()->startOfMonth()->subMonth();          // 前月 1 日
        foreach (range(0, 2) as $i) {
            $workDate   = $base->copy()->addDays($i);
            $attendance = Attendance::factory()
                ->for($user)
                ->create(['work_date' => $workDate]);

            $correction = CorrectionRequest::factory()
                ->for($user)
                ->create([
                    'attendance_id' => $attendance->id,
                    'status'        => CorrectionRequest::STATUS_PENDING,
                    'reason'        => 'テスト理由' . ($i + 1),
                    'created_at'    => now()->subDays(2 - $i),
                ]);

            TimeLog::create([
                'attendance_id'         => $attendance->id,
                'correction_request_id' => $correction->id,
                'logged_at'             => $workDate->copy()->setTime(9, 0),
                'type'                  => 'clock_in',
            ]);
        }

        /* ---------- 2) ブラウザ操作 ---------- */
        $this->browse(function (Browser $browser) use ($user) {

            // クリック前に取得して後で比較する値
            $detailHref      = null;
            $expectedName    = null;   // 名前
            $expectedReason  = null;   // 備考
            $expectedYearTxt = null;   // 2025年
            $expectedMdTxt   = null;   // 6月3日

            $browser->loginAs($user)
                ->visit('/stamp_correction_request/list')
                ->waitForText('承認待ち')

                // 一覧先頭行を対象
                ->within('table tbody tr:first-child', function (Browser $row) use (
                    &$detailHref,
                    &$expectedName,
                    &$expectedReason,
                    &$expectedYearTxt,
                    &$expectedMdTxt
                ) {
                    $expectedName   = trim($row->text('td:nth-child(2)'));
                    $expectedReason = trim($row->text('td:nth-child(4)'));

                    // YYYY/MM/DD → 年・月日へ変換
                    $dateStr   = trim($row->text('td:nth-child(3)'));   // 例 2025/06/03
                    $cDate     = Carbon::createFromFormat('Y/m/d', $dateStr);
                    $expectedYearTxt = $cDate->year . '年';             // 2025年
                    $expectedMdTxt   = $cDate->format('n月j日');         // 6月3日

                    $detailHref = $row->attribute('a', 'href');
                    $row->clickLink('詳細');
                })

                // 遷移後検証
                ->waitForText('勤怠詳細', 10)
                ->assertPathIs(parse_url($detailHref, PHP_URL_PATH))
                ->assertSee($expectedName)     // 名前が一致
                ->assertSee($expectedYearTxt)  // 年が一致
                ->assertSee($expectedMdTxt)    // 月日が一致
                ->assertSee($expectedReason);  // 備考（申請理由）が一致
        });
    }
}
