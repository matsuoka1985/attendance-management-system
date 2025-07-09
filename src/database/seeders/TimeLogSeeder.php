<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\TimeLog;
use Carbon\Carbon;

class TimeLogSeeder extends Seeder
{
    public function run(): void
    {
        Attendance::whereHas('user', fn($query) => $query->where('role', '!=', 'admin'))
            ->get()
            ->each(function (Attendance $attendance) {

            $day      = Carbon::parse($attendance->work_date);
            $clockIn  = $day->copy()->setTime(9, 0);
            $clockOut = $clockIn->copy()->addMinutes(rand(510, 570));  // 8.5〜9.5 h

            /* 出勤ログ */
            TimeLog::factory()->clockIn($day)->create([
                'attendance_id' => $attendance->id,
                'logged_at'     => $clockIn,
            ]);

            /* 休憩生成 */
            $totalBreakMin = 0;
            $cursor        = $clockIn->copy()->addHours(1);            // 10:00 付近から候補

            while (true) {
                // 退勤 30 分前を超えたら終了
                if ($cursor->gt($clockOut->copy()->subMinutes(30))) {
                    break;
                }

                // 休憩長さ & 開始時刻
                $lengthMin = rand(20, 40);                              // 20〜40 分
                $start     = $cursor->copy()->addMinutes(rand(0, 30));
                $end       = $start->copy()->addMinutes($lengthMin);

                // end が退勤30分前以降はやめる
                if ($end->gt($clockOut->copy()->subMinutes(30))) {
                    break;
                }

                /* 休憩開始 */
                TimeLog::factory()->breakStart($start)->create([
                    'attendance_id' => $attendance->id,
                ]);

                /* 休憩終了 */
                TimeLog::factory()->breakEnd($end)->create([
                    'attendance_id' => $attendance->id,
                ]);

                $totalBreakMin += $lengthMin;

                // 8h超勤務の場合 60 分以上になったら終了
                if ($clockOut->diffInMinutes($clockIn) > 480 && $totalBreakMin >= 60) {
                    break;
                }

                // 次の候補（休憩後 90〜120 分後）
                $cursor = $end->copy()->addMinutes(rand(90, 120));
            }

            /* 退勤ログ */
            TimeLog::factory()->clockOut($day)->create([
                'attendance_id' => $attendance->id,
                'logged_at'     => $clockOut,
            ]);
        });
    }
}
