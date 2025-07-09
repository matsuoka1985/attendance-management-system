<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use App\Models\CorrectionRequest;
use App\Models\TimeLog;
use App\Models\User;
use Carbon\Carbon;

class CorrectionRequestSeeder extends Seeder
{
    /** ずらす ±分数の範囲 */
    private const SHIFT_MIN = 3;   // 3 分以上
    private const SHIFT_MAX = 9;   // 9 分以内

    public function run(): void
    {
        /* ── 承認者（管理者） ── */
        $admin = User::where('role', 'admin')->first();

        Attendance::whereHas('user', fn($userQuery) => $userQuery->where('role', '!=', 'admin'))
            ->inRandomOrder()
            ->whereDoesntHave('correctionRequests')
            ->limit(50)
            ->get()
            ->each(function (Attendance $attendance) use ($admin) {

                $workDate = Carbon::parse($attendance->work_date);

                /* === ① 申請ヘッダ === */
                $statusRand = rand(1, 3);               // 1‑2: approved, 3: pending

                $baseFactory     = CorrectionRequest::factory()
                    ->for($attendance->user, 'user')
                    ->for($attendance,      'attendance');

                $selectedFactory = match ($statusRand) {
                    1, 2   => $baseFactory->approved($admin),
                    default => $baseFactory,            // pending
                };

                /** @var CorrectionRequest $correctionRequest */
                $correctionRequest = $selectedFactory->create();

                /* === ② 元ログ取得（時刻順） === */
                $originalLogs = $attendance->timeLogs->sortBy('logged_at')->values();

                /* === ③ どの打刻をずらすか決定 === */
                $shouldShift = function () {
                    return rand(0, 1) === 1;           // 50 % の確率で変更
                };

                // 元ログが 1 本も無い場合は skip
                if ($originalLogs->isEmpty()) {
                    return;
                }

                /* === ④ ドラフト TimeLog を全件コピー ===
                       ・変更対象だけ ±3〜9 分シフト
                       ・他は同じ時刻で複写                           */
                foreach ($originalLogs as $originalLog) {
                    $shiftedLoggedAt = Carbon::parse($originalLog->logged_at);

                    if ($shouldShift()) {
                        // ±(3〜9) 分シフト
                        $minuteDelta     = rand(self::SHIFT_MIN, self::SHIFT_MAX);
                        $shiftedLoggedAt->addMinutes(rand(0, 1) ? $minuteDelta : -$minuteDelta);
                    }

                    TimeLog::create([
                        'attendance_id'         => $attendance->id,   // 承認後紐付く
                        'logged_at'             => $shiftedLoggedAt,
                        'type'                  => $originalLog->type,
                        'correction_request_id' => $correctionRequest->id,
                    ]);
                }
            });
    }
}
