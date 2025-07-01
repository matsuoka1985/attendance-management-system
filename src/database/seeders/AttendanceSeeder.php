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
        $end   = Carbon::yesterday()->startOfDay();    
        $start = $end->copy()->subMonthsNoOverflow(6);

        User::all()->each(function (User $user) use ($start, $end) {

            $date = $start->copy();

            while ($date->lte($end)) {

                // 土日は 95 % スキップ
                if ($date->isWeekend() && rand(0, 100) < self::WEEKEND_SKIP_RATE * 100) {
                    $date->addDay();
                    continue;
                }

                Attendance::firstOrCreate([
                    'user_id'   => $user->id,
                    'work_date' => $date->toDateString(),
                ]);

                $date->addDay();
            }
        });
    }
}
