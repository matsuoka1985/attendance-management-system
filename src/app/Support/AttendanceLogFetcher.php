<?php

namespace App\Support;

use App\Models\TimeLog;
use App\Models\CorrectionRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class AttendanceLogFetcher
{
    /**
     * あるユーザ・日付の “確定打刻” を取得
     *
     * @return Collection<TimeLog>
     */
    public static function confirmedLogs(
        int $userId,
        Carbon $workDate,
    ): Collection {

        /* ─ 最新 approved 申請を探す ─ */
        $latest = CorrectionRequest::where('user_id',  $userId)
            ->where('status',     CorrectionRequest::STATUS_APPROVED)
            ->whereHas('timeLogs', fn ($q) => $q->whereDate('logged_at', $workDate))
            ->latest('created_at')          // created_at を基準にする！
            ->first();

        /* ─ カットライン ─ */
        $cutLine = $latest?->created_at;

        /* ─ 対象ログ ─ */
        return TimeLog::query()
            ->whereHas('attendance', fn ($q) => $q
                ->where('user_id', $userId)
                ->whereDate('work_date', $workDate)
            )
            ->when($cutLine, fn ($q) => $q->where('created_at', '>=', $cutLine))
            ->orderBy('logged_at')
            ->get();
    }
}
