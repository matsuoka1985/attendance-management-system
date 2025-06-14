<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\BreakTime;
use Carbon\Carbon;

class BreakTimeSeeder extends Seeder
{
    /**
     * AttendanceSeeder で生成した勤怠 1 日につき
     * 1 〜 3 回の休憩を付与する。
     *
     * 09:00 - 18:00 勤務を前提に
     * ・1 回目   12:00-13:00
     * ・2 回目   15:00-15:15
     * ・3 回目   17:00-17:10
     */
    public function run(): void
    {
        Attendance::chunk(500, function ($attendances) {

            foreach ($attendances as $attendance) {

                // ── 固定休憩パターン ─────────────
                $breakSets = [
                    ['12:00', '13:00'],
                    ['15:00', '15:15'],
                    ['17:00', '17:10'],
                ];

                $take = fake()->numberBetween(1, 3);   // 1〜3 件

                for ($i = 0; $i < $take; $i++) {
                    [$s, $e] = $breakSets[$i];

                    // ★ 日付文字列だけを使う ★
                    $date = $attendance->work_date->toDateString();

                    BreakTime::updateOrCreate(
                        [
                            'attendance_id' => $attendance->id,
                            'start_at'      => Carbon::parse("$date $s"),
                        ],
                        [
                            'end_at' => Carbon::parse("$date $e"),
                        ]
                    );
                }
            }
        });
    }
}
