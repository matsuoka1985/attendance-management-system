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

        Attendance::whereHas('user', fn($q) => $q->where('role', '!=', 'admin'))
            ->inRandomOrder()
            ->whereDoesntHave('correctionRequests')
            ->limit(50)
            ->get()
            ->each(function (Attendance $att) use ($admin) {

                /* === ① 申請ヘッダ === */
                $statusRand = rand(1, 3);               // 1‑2: approved, 3: pending

                $baseFactory     = CorrectionRequest::factory()
                    ->for($att->user, 'user')
                    ->for($att,      'attendance');

                $selectedFactory = match ($statusRand) {
                    1, 2   => $baseFactory->approved($admin),
                    default => $baseFactory,            // pending
                };

                /** @var CorrectionRequest $request */
                $request = $selectedFactory->create();

                /* === ② 元ログ取得（時刻順） === */
                $logs = $att->timeLogs->sortBy('logged_at')->values();

                /* === ③ どの打刻をずらすか決定 === */
                $shouldShift = function () {
                    return rand(0, 1) === 1;           // 50 % の確率で変更
                };

                // 元ログが 1 本も無い場合は skip
                if ($logs->isEmpty()) {
                    return;
                }

                /* === ④ ドラフト TimeLog を全件コピー ===
                       ・変更対象だけ ±3〜9 分シフト
                       ・他は同じ時刻で複写                           */
                foreach ($logs as $origin) {
                    $loggedAt = Carbon::parse($origin->logged_at);

                    if ($shouldShift()) {
                        // ±(3〜9) 分シフト
                        $delta     = rand(self::SHIFT_MIN, self::SHIFT_MAX);
                        $loggedAt->addMinutes(rand(0, 1) ? $delta : -$delta);
                    }

                    TimeLog::create([
                        'attendance_id'         => $att->id,   // 承認後紐付く
                        'logged_at'             => $loggedAt,
                        'type'                  => $origin->type,
                        'correction_request_id' => $request->id,
                    ]);
                }
            });
    }
}
