<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TimeLog;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class AdminAttendanceListTest extends DuskTestCase
{
    use DatabaseMigrations;

    #[Test]
    public function 「翌月」を押下した時に表示月の翌月の情報が表示される(): void
    {
        /* ---------- テストデータ ---------- */
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'user']);

        // 対象 = “前月”
        $prevMonth      = Carbon::today()->subMonth()->startOfMonth();   // 例: 2025-06-01
        $prevMonthLabel = $prevMonth->format('Y/m');                     // 2025/06 （画面中央に出る）
        $prevPrevMonth  = $prevMonth->copy()->subMonth();                // その前の月（2025-05）

        // 前月の平日すべてに 09-18 (休憩 12-13) を生成
        $expectedRows = [];
        for ($d = $prevMonth->copy(); $d->lte($prevMonth->copy()->endOfMonth()); $d->addDay()) {
            if ($d->isWeekend()) continue;

            $att = Attendance::factory()->create([
                'user_id'   => $staff->id,
                'work_date' => $d->toDateString(),
            ]);

            TimeLog::factory()->createMany([
                ['attendance_id' => $att->id, 'logged_at' => $d->copy()->setTime(9, 0),  'type' => 'clock_in'],
                ['attendance_id' => $att->id, 'logged_at' => $d->copy()->setTime(12, 0), 'type' => 'break_start'],
                ['attendance_id' => $att->id, 'logged_at' => $d->copy()->setTime(13, 0), 'type' => 'break_end'],
                ['attendance_id' => $att->id, 'logged_at' => $d->copy()->setTime(18, 0), 'type' => 'clock_out'],
            ]);

            $expectedRows[] = [
                'label' => $d->isoFormat('MM/DD(ddd)'),
                'start' => '09:00',
                'end'   => '18:00',
                'break' => '1:00',
                'total' => '8:00',
            ];
        }

        /* ---------- ブラウザテスト ---------- */
        $this->browse(function (Browser $browser) use ($admin, $staff, $prevPrevMonth, $prevMonthLabel, $expectedRows) {

            //  前々月画面を開いて「翌月」をクリック
            $browser->loginAs($admin, 'admin')
                ->visit(route('admin.staff_attendance.index', [
                    'id'    => $staff->id,
                    'month' => $prevPrevMonth->format('Y-m'),
                ]))
                ->press('翌月')
                ->waitForText($prevMonthLabel)        // 見出しが前月になったか
                ->assertSee($prevMonthLabel);

            //  前月平日すべての行を検証（テーブル内に値が存在するか）
            foreach ($expectedRows as $row) {
                $browser->assertSee($row['label'])          // 日付ラベル
                    ->assertSeeIn('table', $row['start'])
                    ->assertSeeIn('table', $row['end'])
                    ->assertSeeIn('table', $row['break'])
                    ->assertSeeIn('table', $row['total']);
            }

        });
    }
}
