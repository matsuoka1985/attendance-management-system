<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'work_date' => $this->faker->dateTimeBetween('-6 months', 'now')
                ->format('Y-m-d'),
        ];
    }
}
