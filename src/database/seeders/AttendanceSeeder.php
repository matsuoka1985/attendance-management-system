<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Support\Carbon;

class AttendanceSeeder extends Seeder
{
    /** 土日スキップ率 */
    private const WEEKEND_SKIP_RATE = 0.95;

    public function run(): void
    {
        $endDate   = Carbon::yesterday()->startOfDay();
        $startDate = $endDate->copy()->subMonthsNoOverflow(6);

        User::where('role', 'user')->get()->each(function (User $user) use ($startDate, $endDate) {

            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {

                // 土日は 95 % スキップ
                if ($currentDate->isWeekend() && rand(0, 100) < self::WEEKEND_SKIP_RATE * 100) {
                    $currentDate->addDay();
                    continue;
                }

                Attendance::firstOrCreate([
                    'user_id'   => $user->id,
                    'work_date' => $currentDate->toDateString(),
                ]);

                $currentDate->addDay();
            }
        });
    }
}
