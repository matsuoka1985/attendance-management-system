<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use App\Models\User;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // 1日の固定パターン（09:00–18:00/休憩1h）
        $workDate = $this->faker->dateTimeBetween('-6 months', '+6 months')
            ->format('Y-m-d');

        $start = Carbon::parse("$workDate 09:00:00");
        $end   = Carbon::parse("$workDate 18:00:00");

        return [
            //
            'user_id'  => User::where('role', 'user')->inRandomOrder()->first()->id,
            'work_date' => $workDate,
            'start_at' => $start,
            'end_at'   => $end,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
