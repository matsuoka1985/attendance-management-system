<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\CorrectionRequest;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Carbon;




/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CorrectionRequest>
 */
class CorrectionRequestFactory extends Factory
{
    protected $model = CorrectionRequest::class;

    public function definition(): array
    {
        /** @var Attendance $attendance */
        $attendance = Attendance::inRandomOrder()->first();

        // ステータスを決定
        $status      = $this->faker->randomElement([
            CorrectionRequest::STATUS_PENDING,
            CorrectionRequest::STATUS_APPROVED,
            CorrectionRequest::STATUS_REJECTED,
        ]);

        // 承認者（admin）は、approved / rejected のみセット
        $reviewedBy  = null;
        $reviewedAt  = null;

        if ($status !== CorrectionRequest::STATUS_PENDING) {
            $reviewedBy = User::where('role', 'admin')->inRandomOrder()->value('id');
            $reviewedAt = Carbon::parse($attendance->work_date)
                ->addDays($this->faker->numberBetween(1, 5))
                ->setTimeFromTimeString(
                    $this->faker->randomElement(['10:00', '14:00', '18:00'])
                );
        }

        return [
            'attendance_id' => $attendance->id,
            'reason'        => $this->faker->randomElement([
                '遅延のため',
                '早退のため',
                '打刻漏れ修正',
                'その他',
            ]),
            'status'        => $status,
            'reviewed_by'   => $reviewedBy,
            'reviewed_at'   => $reviewedAt,
            'created_at'    => Carbon::parse($attendance->work_date)
                ->addDays($this->faker->numberBetween(1, 3))
                ->setTimeFromTimeString('09:00'),
        ];
    }
}
