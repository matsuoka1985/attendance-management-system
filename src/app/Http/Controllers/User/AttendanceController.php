<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\Attendance;
use App\Models\CorrectionRequest;
use App\Models\TimeLog;

class AttendanceController extends Controller
{

    /**
     * 月次の勤怠一覧
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        /* ---------- 1. 対象月 ---------- */ //クエリパラメータあればそのクエリパラメータに則って月を取得、なければ現在の月を取得
        $baseDate      = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->month)
            : now();
        $startOfMonth  = $baseDate->copy()->startOfMonth();
        $endOfMonth    = $baseDate->copy()->endOfMonth();

        $prevMonth     = $baseDate->copy()->subMonth();
        $nextMonth     = $baseDate->copy()->addMonth();

        /* ---------- 2. 勤怠＋ログ＋申請ヘッダを一括取得 ---------- */
        $attendances = Attendance::with([
            'timeLogs' => fn($query) => $query->orderBy('logged_at'),
            'correctionRequests:id,attendance_id,status,created_at',
        ])
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($attendance) => $attendance->work_date->format('Y-m-d'));

        /* ---------- 3. 月内ループで表示用配列 ---------- */
        $attendanceData = [];
        foreach (Carbon::parse($startOfMonth)->daysUntil($endOfMonth) as $date) {

            $key        = $date->format('Y-m-d');
            $attendance = $attendances->get($key);

            $startTime = $endTime = $breakStr = $workStr = '';
            $attendanceId = null;

            if ($attendance) {
                $attendanceId = $attendance->id;

                /* ① 最新 approved 申請の cutLine ---------- */
                $cutLine = $attendance->correctionRequests
                    ->where('status', CorrectionRequest::STATUS_APPROVED)
                    ->sortByDesc('created_at')
                    ->first()?->created_at;          // Carbon|null

                /* ② 確定ログへ絞り込み -------------------- */
                $baseLogs = $attendance->timeLogs
                    ->filter(function (TimeLog $log) {
                        return is_null($log->correction_request_id) ||
                            optional($log->correctionRequest)->status === CorrectionRequest::STATUS_APPROVED;
                    })
                    ->when(
                        $cutLine,
                        fn($col) =>
                        $col->filter(fn(TimeLog $log) => $log->created_at >= $cutLine)
                    )
                    ->values();                       // インデックス振り直し

                /* ③ 出勤／退勤 ---------------------------- */
                $firstIn = $baseLogs->firstWhere('type', 'clock_in');
                $lastOut = $baseLogs->where('type', 'clock_out')->last();

                $startTime = $firstIn?->logged_at->format('H:i') ?? '';
                $endTime   = $lastOut?->logged_at->format('H:i') ?? '';

                /* ④ 休憩合計秒数 ------------------------ */
                $breakSec = 0;
                $breakStart = null;
                foreach ($baseLogs as $log) {
                    if ($log->type === 'break_start')      $breakStart = $log->logged_at;
                    elseif ($log->type === 'break_end' && $breakStart) {
                        $breakSec += $breakStart->diffInSeconds($log->logged_at);
                        $breakStart = null;
                    }
                }
                $breakStr = $breakSec ? gmdate('G:i', $breakSec) : '';

                /* ⑤ 労働合計 ---------------------------- */
                if ($firstIn) {
                    $endTimeCarbon = $lastOut?->logged_at ?? now();
                    $totalSec = max(0, $firstIn->logged_at->diffInSeconds($endTimeCarbon) - $breakSec);
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

        /* ---------- 4. ビュー ---------- */
        return view('user.attendance.index', compact(
            'attendanceData',
            'prevMonth',
            'nextMonth'
        ))->with('currentMonth', $baseDate);
    }





    /* ① create()  出勤していない日の “新規登録” 画面 */
    public function create(Request $request)
    {
        $user = Auth::user();
        $date = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : now()->startOfDay();

        /* 既存勤怠があれば詳細画面へ */
        if ($attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $date)
            ->first()
        ) {
            return redirect()->route('attendance.show', $attendance->id);
        }

        /* ─ 最新 pending 申請（あれば） ─ */
        $pending = CorrectionRequest::with(['timeLogs' => fn($query) => $query->orderBy('logged_at')])
            ->where('user_id',  $user->id)
            ->where('status',   CorrectionRequest::STATUS_PENDING)
            ->whereHas('timeLogs', fn($query) => $query->whereDate('logged_at', $date))
            ->latest('created_at')
            ->first();

        $hasPendingRequest = (bool) $pending;

        /* ===== ここから初期値 ===== */
        // ★ 時刻入力欄は空のまま
        $startAt = $endAt = '';
        $breaks  = [['start' => '', 'end' => '']];   // 1 本だけ空行
        $reason  = $pending?->reason ?? '';

        /* 青帯にだけ差分を表示 ----------------------------- */
        $diffs = [];
        if ($pending) {
            $logs = $pending->timeLogs;

            /* 出勤 / 退勤 */
            if ($start = optional($logs->firstWhere('type', 'clock_in'))->logged_at?->format('H:i')) {
                $diffs[] = ['label' => '出勤', 'old' => '—', 'new' => $start];
            }
            if ($end = optional($logs->where('type', 'clock_out')->last())->logged_at?->format('H:i')) {
                $diffs[] = ['label' => '退勤', 'old' => '—', 'new' => $end];
            }

            /* 休憩ペア */
            $breakStart = null;
            $index = 1;
            foreach ($logs as $log) {
                if ($log->type === 'break_start') {
                    $breakStart = $log->logged_at;
                } elseif ($log->type === 'break_end' && $breakStart) {
                    $start = $breakStart->format('H:i');
                    $end = $log->logged_at->format('H:i');
                    $diffs[] = ['label' => "休憩{$index}", 'old' => '—', 'new' => "{$start}〜{$end}"];
                    $breakStart = null;
                    $index++;
                }
            }
        }

        /* ---------- View ---------- */
        return view('user.attendance.show', [
            'mode'              => 'create',
            'userName'          => $user->name,
            'workDate'          => $date,
            'attendance'        => null,
            'startAt'           => $startAt,      // ← 空
            'endAt'             => $endAt,        // ← 空
            'breaks'            => $breaks,       // ← 空 1 行だけ
            'reason'            => $reason,       // ← 理由は表示
            'hasPendingRequest' => $hasPendingRequest,
            'diffs'             => $diffs,        // ← 青帯
        ]);
    }




    /**
     * 既存勤怠の「詳細／修正」画面
     *
     * @param  int  $id  attendance.id
     */
    public function show(int $id)
    {
        /* ========== 0. 基本情報 ========== */
        $user       = Auth::user();
        $attendance = Attendance::where('user_id', $user->id)->findOrFail($id);
        $workDate   = Carbon::parse($attendance->work_date);

        /* -------------------------------------------------------------
     * 1. 「確定」とみなす TimeLog 一覧
     *    ─ 最新 approved 申請の created_at 以降
     *    ─ correction_request_id が NULL（実運用ログ）or approved
     * ----------------------------------------------------------- */
        $cutLine = CorrectionRequest::where('attendance_id', $attendance->id)
            ->where('status', CorrectionRequest::STATUS_APPROVED)
            ->latest('created_at')
            ->value('created_at');                   // null → approved が 1 件も無い

        $baseLogs = TimeLog::where('attendance_id', $attendance->id)
            ->where(function ($query) {
                $query->whereNull('correction_request_id')           // 実運用
                    ->orWhereHas(
                        'correctionRequest',
                        fn($correctionQuery) =>
                        $correctionQuery->where('status', CorrectionRequest::STATUS_APPROVED)
                    );                                             // or approved 申請ログ
            })
            ->when(
                $cutLine,
                fn($query) =>
                $query->where('created_at', '>=', $cutLine)
            )
            ->orderBy('logged_at')
            ->get();

        /* -------------------------------------------------------------
     * 2. 直近 1 件の pending 申請（あれば）
     * ----------------------------------------------------------- */
        $pending = CorrectionRequest::with(['timeLogs' => fn($query) => $query->orderBy('logged_at')])
            ->where('attendance_id', $attendance->id)
            ->where('status',  CorrectionRequest::STATUS_PENDING)
            ->latest('created_at')
            ->first();                                   // null → 申請なし

        $draftLogs          = $pending ? $pending->timeLogs : collect();
        $hasPendingRequest  = (bool) $pending;
        $displayReason      = $pending?->reason ?? ($attendance->reason ?? '');

        /* -------------------------------------------------------------
     * 3. 確定ログを画面用に分解
     * ----------------------------------------------------------- */
        // ─ 出勤・退勤
        $startAt = optional($baseLogs->firstWhere('type', 'clock_in'))
            ->logged_at?->format('H:i') ?? '';
        $endAt   = optional($baseLogs->where('type', 'clock_out')->last())
            ->logged_at?->format('H:i') ?? '';

        // ─ 休憩ペア化
        $breaks = [];
        $breakStart  = null;
        foreach ($baseLogs as $log) {
            if ($log->type === 'break_start') {
                $breakStart = $log->logged_at;
            } elseif ($log->type === 'break_end' && $breakStart) {
                $breaks[] = [
                    'start' => $breakStart->format('H:i'),
                    'end'   => $log->logged_at->format('H:i'),
                ];
                $breakStart = null;
            }
        }
        if (!$breakStart) {   // 末尾空行（入力補助）
            $breaks[] = ['start' => '', 'end' => ''];
        }

        /* -------------------------------------------------------------
     * 4. 差分抽出（確定 vs pending）
     * ----------------------------------------------------------- */
        $diffs = [];
        if ($pending) {
            // ① draftLogs を同スキーマで取得
            $draftStart = optional($draftLogs->firstWhere('type', 'clock_in'))
                ->logged_at?->format('H:i');
            $draftEnd   = optional($draftLogs->where('type', 'clock_out')->last())
                ->logged_at?->format('H:i');

            if ($startAt !== $draftStart) {
                $diffs[] = ['label' => '出勤', 'old' => $startAt, 'new' => $draftStart];
            }
            if ($endAt !== $draftEnd) {
                $diffs[] = ['label' => '退勤', 'old' => $endAt, 'new' => $draftEnd];
            }

            // ② draft の休憩をペア化
            $draftPairs = [];
            $breakStart = null;
            foreach ($draftLogs as $log) {
                if ($log->type === 'break_start') {
                    $breakStart = $log->logged_at;
                } elseif ($log->type === 'break_end' && $breakStart) {
                    $draftPairs[] = [
                        'start' => $breakStart->format('H:i'),
                        'end'   => $log->logged_at->format('H:i'),
                    ];
                    $breakStart = null;
                }
            }

            $max = max(count($breaks) - 1, count($draftPairs));   // -1 で末尾空行除外
            for ($i = 0; $i < $max; $i++) {
                $basePair  = $breaks[$i]     ?? ['start' => null, 'end' => null];
                $draftPair = $draftPairs[$i] ?? ['start' => null, 'end' => null];

                if (($basePair['start'] ?? null) !== ($draftPair['start'] ?? null)) {
                    $diffs[] = [
                        'label' => '休憩' . ($i + 1) . '開始',
                        'old'   => $basePair['start'],
                        'new'   => $draftPair['start'],
                    ];
                }
                if (($basePair['end'] ?? null) !== ($draftPair['end'] ?? null)) {
                    $diffs[] = [
                        'label' => '休憩' . ($i + 1) . '終了',
                        'old'   => $basePair['end'],
                        'new'   => $draftPair['end'],
                    ];
                }
            }
        }

        /* -------------------------------------------------------------
     * 5. View へ
     * ----------------------------------------------------------- */
        return view('user.attendance.show', [
            'mode'              => 'edit',
            'userName'          => $user->name,
            'workDate'          => $workDate,
            'attendance'        => $attendance,
            'startAt'           => $startAt,
            'endAt'             => $endAt,
            'breaks'            => $breaks,
            'reason'            => $displayReason,
            'hasPendingRequest' => $hasPendingRequest,
            'diffs'             => $diffs,
        ]);
    }
}
