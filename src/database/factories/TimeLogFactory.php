<?php

namespace Database\Factories;

use App\Models\TimeLog;
use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class TimeLogFactory extends Factory
{
    protected $model = TimeLog::class;

    public function definition(): array
    {
        return [
            'attendance_id'         => Attendance::factory(),
            'logged_at'             => now(),
            'type'                  => 'clock_in',
            'correction_request_id' => null,
        ];
    }

    /* ---------- 便利ステート ---------- */

    public function clockIn(Carbon $day): static
    {
        return $this->state([
            'logged_at' => $day->copy()->setTime(9, 0),
            'type'      => 'clock_in',
        ]);
    }

    public function clockOut(Carbon $day): static
    {
        return $this->state([
            'logged_at' => $day->copy()->setTime(18, 0),
            'type'      => 'clock_out',
        ]);
    }

    public function breakStart(Carbon $time): static
    {
        return $this->state([
            'logged_at' => $time,
            'type'      => 'break_start',
        ]);
    }

    public function breakEnd(Carbon $time): static
    {
        return $this->state([
            'logged_at' => $time,
            'type'      => 'break_end',
        ]);
    }
}
