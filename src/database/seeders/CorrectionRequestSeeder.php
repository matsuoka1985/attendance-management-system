<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\CorrectionRequest;


class CorrectionRequestSeeder extends Seeder
{
    public function run(): void
    {
        // 対象期間：今日から遡って 6 か月
        $since = now()->subMonthsNoOverflow(6)->startOfMonth();

        // 対象勤怠を取得
        Attendance::where('work_date', '>=', $since)
            ->inRandomOrder()
            ->chunk(500, function ($attendances) {

                foreach ($attendances as $attendance) {

                    // 25 % の確率で申請を作成
                    if (fake()->boolean(25)) {

                        // 1 個だけ生成（重複防止のため updateOrCreate）
                        CorrectionRequest::updateOrCreate(
                            ['attendance_id' => $attendance->id],
                            CorrectionRequest::factory()->make()->toArray()
                        );
                    }
                }
            });
    }
}
