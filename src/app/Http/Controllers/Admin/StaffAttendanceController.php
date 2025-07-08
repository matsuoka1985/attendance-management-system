<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\TimeLog;
use App\Models\CorrectionRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;


class StaffAttendanceController extends Controller
{

    /**
     * 指定スタッフの月次勤怠一覧
     * GET /admin/attendance/staff/{id}?month=YYYY-MM
     */
    public function index(Request $request, int $id)
    {
        /* ---------- ① 対象スタッフ ---------- */
        $staff = User::findOrFail($id);
        // 管理者アカウントは対象外にする
        if ($staff->role === 'admin') {
            abort(404);
        }

        /* ---------- ② 対象月 ---------- */
        $baseDate      = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->month)
            : now();
        $startOfMonth  = $baseDate->copy()->startOfMonth();
        $endOfMonth    = $baseDate->copy()->endOfMonth();

        $prevMonth     = $baseDate->copy()->subMonth();
        $nextMonth     = $baseDate->copy()->addMonth();

        /* ---------- ③ 勤怠＋ログ＋申請ヘッダ ---------- */
        $attendances = Attendance::with([
            'timeLogs' => fn($query) => $query->orderBy('logged_at'),
            'correctionRequests:id,attendance_id,status,created_at',
        ])
            ->where('user_id', $staff->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($attendanceRecord) => $attendanceRecord->work_date->format('Y-m-d'));

        /* ---------- ④ 月内ループで行データ生成 ---------- */
        $attendanceData = [];
        foreach (Carbon::parse($startOfMonth)->daysUntil($endOfMonth) as $date) {

            $key        = $date->format('Y-m-d');
            $attendance = $attendances->get($key);

            // 既定値
            $startTime = $endTime = $breakStr = $workStr = '';
            $attendanceId = null;

            if ($attendance) {
                $attendanceId = $attendance->id;

                // ── 最新 approved 申請日時 (cut-line)
                $cutLine = $attendance->correctionRequests
                    ->where('status', CorrectionRequest::STATUS_APPROVED)
                    ->sortByDesc('created_at')
                    ->first()?->created_at;

                // ── 確定ログへ絞り込み
                $baseLogs = $attendance->timeLogs
                    ->filter(
                        fn(TimeLog $logEntry) =>
                        is_null($logEntry->correction_request_id) ||
                            optional($logEntry->correctionRequest)->status === CorrectionRequest::STATUS_APPROVED
                    )
                    ->when(
                        $cutLine,
                        fn($logCollection) => $logCollection->filter(fn(TimeLog $logEntry) => $logEntry->created_at >= $cutLine)
                    )
                    ->values();

                // ── 出勤／退勤
                $firstIn = $baseLogs->firstWhere('type', 'clock_in');
                $lastOut = $baseLogs->where('type', 'clock_out')->last();

                $startTime = $firstIn?->logged_at->format('H:i') ?? '';
                $endTime   = $lastOut?->logged_at->format('H:i') ?? '';

                // ── 休憩合計
                $breakSec = 0;
                $breakStart = null;
                foreach ($baseLogs as $logEntry) {
                    if ($logEntry->type === 'break_start') {
                        $breakStart = $logEntry->logged_at;
                    } elseif ($logEntry->type === 'break_end' && $breakStart) {
                        $breakSec += $breakStart->diffInSeconds($logEntry->logged_at);
                        $breakStart = null;
                    }
                }
                $breakStr = $breakSec ? gmdate('G:i', $breakSec) : '';

                // ── 労働合計
                if ($firstIn) {
                    $totalSec = max(0, $firstIn->logged_at->diffInSeconds($lastOut?$lastOut->logged_at:now()) - $breakSec);
                    $workStr  = gmdate('G:i', $totalSec);
                }
            }

            $attendanceData[] = [
                'id'        => $attendanceId,
                'date'      => $date->isoFormat('MM/DD(ddd)'),
                'start'     => $startTime,
                'end'       => $endTime,
                'break'     => $breakStr,
                'work'      => $workStr,
                'work_date' => $key,
            ];
        }

        /* ---------- ⑤ ビュー ---------- */
        return view('admin.attendance.staff.index', [
            'staff'          => $staff,
            'attendanceData' => $attendanceData,
            'prevMonth'      => $prevMonth,
            'nextMonth'      => $nextMonth,
            'currentMonth'   => $baseDate,
        ]);
    }

    /**
     * 指定スタッフの月次勤怠を CSV ダウンロード
     * GET /admin/attendance/staff/{id}/csv?month=YYYY-MM
     */
    public function exportCsv(Request $request, int $id): StreamedResponse
    {
        /* ---------- 1. 対象スタッフ ---------- */
        $staff = User::findOrFail($id);
        if ($staff->role === 'admin') abort(404);

        /* ---------- 2. 対象月 ---------- */
        $baseDate     = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->month)
            : now();
        $startOfMonth = $baseDate->copy()->startOfMonth();
        $endOfMonth   = $baseDate->copy()->endOfMonth();

        /* ---------- 3. 勤怠＋ログ＋申請ヘッダ ---------- */
        $attendances = Attendance::with([
                'timeLogs' => fn($query) => $query->orderBy('logged_at'),
                'correctionRequests:id,attendance_id,status,created_at',
            ])
            ->where('user_id', $staff->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($attendanceRecord) => $attendanceRecord->work_date->format('Y-m-d'));

        /* ---------- 4. ダウンロードストリーム ---------- */
        $fileName = "{$staff->name}_{$baseDate->format('Ym')}_attendance.csv";

        return response()->streamDownload(function () use (
            $attendances, $startOfMonth, $endOfMonth
        ) {
            $out = fopen('php://output', 'w');

            // 文字コードを Excel 互換 (UTF-8 BOM)
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // 見出し
            fputcsv($out, ['日付', '出勤', '退勤', '休憩', '合計']);

            foreach (Carbon::parse($startOfMonth)->daysUntil($endOfMonth) as $day) {

                $key        = $day->format('Y-m-d');
                $attendance = $attendances->get($key);

                $start = $end = $breakStr = $totalStr = '';

                if ($attendance) {
                    /* ── cut-line で確定ログ抽出 ── */
                    $cutLine = $attendance->correctionRequests
                        ->where('status', CorrectionRequest::STATUS_APPROVED)
                        ->sortByDesc('created_at')
                        ->first()?->created_at;

                    $logs = $attendance->timeLogs
                        ->filter(fn(TimeLog $logEntry) =>
                            is_null($logEntry->correction_request_id) ||
                            optional($logEntry->correctionRequest)->status === CorrectionRequest::STATUS_APPROVED
                        )
                        ->when($cutLine,
                            fn($logCollection) => $logCollection->filter(fn(TimeLog $logEntry) => $logEntry->created_at >= $cutLine)
                        );

                    $firstIn = $logs->firstWhere('type', 'clock_in');
                    $lastOut = $logs->where('type', 'clock_out')->last();

                    $start = $firstIn?->logged_at->format('H:i') ?? '';
                    $end   = $lastOut?->logged_at->format('H:i') ?? '';

                    // 休憩計算
                    $breakSec = 0; $breakStart = null;
                    foreach ($logs as $logEntry) {
                        if ($logEntry->type === 'break_start')       $breakStart = $logEntry->logged_at;
                        elseif ($logEntry->type === 'break_end' && $breakStart) {
                            $breakSec += $breakStart->diffInSeconds($logEntry->logged_at);
                            $breakStart = null;
                        }
                    }
                    $breakStr = $breakSec ? gmdate('G:i', $breakSec) : '';

                    if ($firstIn && $lastOut) {
                        $workSec  = max(0, $firstIn->logged_at->diffInSeconds($lastOut->logged_at) - $breakSec);
                        $totalStr = gmdate('G:i', $workSec);
                    }
                }

                fputcsv($out, [
                    $day->isoFormat('MM/DD(ddd)'),
                    $start, $end, $breakStr, $totalStr
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

}
