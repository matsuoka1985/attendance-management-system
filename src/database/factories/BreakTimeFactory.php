<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BreakTime>
 */
class BreakTimeFactory extends Factory
{
    protected $model = BreakTime::class;

    public function definition(): array
    {
        /** @var Attendance $attendance */
        $attendance = Attendance::inRandomOrder()->first();

        // 出勤～退勤の中で「1 h 休憩」をデフォルトに作る
        $start = Carbon::instance($attendance->start_at)->addHours(3);   // 例：出勤 3 時間後
        $end   = (clone $start)->addHour();                              // +1 時間

        return [
            'attendance_id' => $attendance->id,
            'start_at'      => $start,
            'end_at'        => $end,
        ];
    }
}
