<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Attendance;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $baseDate = Carbon::now()->startOfDay();

        // -------- 期間設定 ------------
        // 例：2025-06-14 に実行 → 2024-12-01〜2025-07-31
        $from = $baseDate->copy()->startOfMonth()->subMonths(6);            // 6 か月前の月初
        $to   = $baseDate->copy()->startOfMonth()->addMonth()->endOfMonth(); // 翌月末

        // -------- 勤務パターン --------
        $startAt = ['hour' => 9,  'minute' => 0];   // 09:00 出勤
        $endAt   = ['hour' => 18, 'minute' => 0];   // 18:00 退勤

        // 「一般ユーザー」のみ対象（管理者は除外）
        $users = User::where('role', 'user')->get();

        foreach ($users as $user) {
            $day = $from->copy();

            while ($day->lte($to)) {
                // ── ここで休日判定を入れたければコメント解除 ──
                if ($day->isWeekend()) { $day->addDay(); continue; }

                Attendance::updateOrCreate(
                    [
                        'user_id'   => $user->id,
                        'work_date' => $day->toDateString(),
                    ],
                    [
                        'start_at'  => $day->copy()->setTime($startAt['hour'], $startAt['minute']),
                        'end_at'    => $day->copy()->setTime($endAt['hour'],  $endAt['minute']),
                    ]
                );

                $day->addDay(); // 次の日へ
            }
        }
    }
}
