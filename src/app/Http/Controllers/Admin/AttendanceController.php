<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\TimeLog;
use App\Models\CorrectionRequest;
use App\Models\User;




class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * 当日の一般ユーザ勤怠一覧
     * GET /admin/attendance/list?date=YYYY-MM-DD
     */

    public function index(Request $req)
    {
        /* ---------- 1. 対象日 ---------- */
        $date = Carbon::parse($req->query('date', today()))->startOfDay();

        /* ---------- 2. 当日の勤怠＋ログ＋申請ヘッダ ---------- */
        $attendances = Attendance::with([
            'user:id,name',
            'timeLogs' => fn($q) => $q->orderBy('logged_at'),
            'correctionRequests:id,attendance_id,status,created_at',
        ])
            ->whereDate('work_date', $date)
            ->orderBy('user_id')
            ->get();

        /* ---------- 3. 一覧行 ---------- */
        $rows = $attendances->map(function (Attendance $att) {

            /* ① 最新 approved の cutLine ------------- */
            $cutLine = $att->correctionRequests
                ->where('status', CorrectionRequest::STATUS_APPROVED)
                ->sortByDesc('created_at')
                ->first()?->created_at;          // Carbon|null

            /* ② show() と同じルールで baseLogs 絞る ------ */
            $baseLogs = $att->timeLogs
                ->filter(function (TimeLog $l) {
                    return is_null($l->correction_request_id) ||
                        optional($l->correctionRequest)->status === CorrectionRequest::STATUS_APPROVED;
                })
                ->when($cutLine, function ($col) use ($cutLine) {
                    // Collection::filter で cutLine 以降に限定
                    return $col->filter(fn(TimeLog $l) => $l->created_at >= $cutLine);
                })
                ->values();   // インデックス振り直し

            /* ③ 出勤・退勤 ---------------------------- */
            $start = optional($baseLogs->firstWhere('type', 'clock_in'))->logged_at;
            $end   = optional($baseLogs->where('type', 'clock_out')->last())->logged_at;

            /* ④ 休憩合計 ------------------------------ */
            $breakSec = 0;
            $stk = null;
            foreach ($baseLogs as $l) {
                if ($l->type === 'break_start') {
                    $stk = $l->logged_at;
                } elseif ($l->type === 'break_end' && $stk) {
                    $breakSec += $stk->diffInSeconds($l->logged_at);
                    $stk = null;
                }
            }

            /* ⑤ フォーマット -------------------------- */
            $fmtTime = fn($dt) => $dt?->format('H:i') ?? '--:--';
            $fmtDur  = fn($s)  => $s ? gmdate('G:i', $s) : '';

            return [
                'id'    => $att->id,
                'name'  => $att->user->name,
                'start' => $fmtTime($start),
                'end'   => $fmtTime($end),
                'break' => $fmtDur($breakSec),
                'total' => ($start && $end)
                    ? $fmtDur(max(0, $start->diffInSeconds($end) - $breakSec))
                    : '',
            ];
        });

        /* ---------- 4. ビュー ---------- */
        return view('admin.attendance.index', [
            'date' => $date,
            'prev' => $date->copy()->subDay()->toDateString(),
            'next' => $date->copy()->addDay()->toDateString(),
            'rows' => $rows,
        ]);
    }




    /**
     * 管理者が “勤怠の無い日” を新規作成する入力画面
     * GET /admin/attendance/create?user_id=◯&date=YYYY-MM-DD
     */
    public function create(Request $request)
    {
        /* ---------- 1.  クエリ検証 & 対象スタッフ ---------- */
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date'    => ['required', 'date'],
        ]);

        /** @var \App\Models\User $staff */
        $staff = User::where('role', '!=', 'admin')->findOrFail($data['user_id']);

        /* ---------- 2.  対象日 ---------- */
        $workDate = Carbon::parse($data['date'])->startOfDay();

        /* ---------- 3.  既に勤怠があれば編集画面へ ---------- */
        if ($att = Attendance::where('user_id', $staff->id)
            ->whereDate('work_date', $workDate)
            ->first()
        ) {
            return redirect()->route('admin.attendance.show', $att->id);
        }

        /* ---------- 4.  最新 pending 申請（あれば） ---------- */
        $pending = CorrectionRequest::with(['timeLogs' => fn($q) => $q->orderBy('logged_at')])
            ->where('user_id', $staff->id)
            ->where('status',  CorrectionRequest::STATUS_PENDING)
            ->whereHas('timeLogs', fn($q) => $q->whereDate('logged_at', $workDate))
            ->latest('created_at')
            ->first();

        $hasPendingRequest = (bool) $pending;

        /* ---------- 5.  画面初期値 ---------- */
        // 入力欄は空
        $startAt = $endAt = '';
        $breaks  = [['start' => '', 'end' => '']];   // 空行 1 本
        $reason  = $pending?->reason ?? '';



        /* ---------- 6.  ダミー Attendance (フォーム用) ---------- */
        // ─ route('admin.attendance.fix', $attendance->id) を壊さないためのプレースホルダ
        $attendance          = new Attendance();
        $attendance->id      = 0;              // ダミー ID
        $attendance->user_id = $staff->id;

        /* ---------- 7.  View ---------- */
        return view('admin.attendance.show', [
            'mode'              => 'create',
            'userName'          => $staff->name,
            'workDate'          => $workDate,
            'attendance'        => $attendance,      // ← ダミー
            'startAt'           => $startAt,
            'endAt'             => $endAt,
            'breaks'            => $breaks,
            'reason'            => $reason,
            'hasPendingRequest' => $hasPendingRequest,
            'pendingRequest'   => $pending,
        ]);
    }



    /**
     * Display the specified resource.
     */
    /* ---------- 詳細画面 ---------- */
    public function show(int $id)
    {
        /* ========== 0. 基本情報 ========== */
        $attendance = Attendance::with('user')->findOrFail($id);
        $user       = $attendance->user;
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
            ->where(function ($q) {
                $q->whereNull('correction_request_id')           // 実運用
                    ->orWhereHas(
                        'correctionRequest',
                        fn($qr) =>
                        $qr->where('status', CorrectionRequest::STATUS_APPROVED)
                    );                                             // or approved 申請ログ
            })
            ->when(
                $cutLine,
                fn($q) =>
                $q->where('created_at', '>=', $cutLine)
            )
            ->orderBy('logged_at')
            ->get();

        /* -------------------------------------------------------------
     * 2. 直近 1 件の pending 申請（あれば）
     * ----------------------------------------------------------- */
        $pending = CorrectionRequest::with(['timeLogs' => fn($q) => $q->orderBy('logged_at')])
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
        $stack  = null;
        foreach ($baseLogs as $log) {
            if ($log->type === 'break_start') {
                $stack = $log->logged_at;
            } elseif ($log->type === 'break_end' && $stack) {
                $breaks[] = [
                    'start' => $stack->format('H:i'),
                    'end'   => $log->logged_at->format('H:i'),
                ];
                $stack = null;
            }
        }
        if (!$stack) {   // 末尾空行（入力補助）
            $breaks[] = ['start' => '', 'end' => ''];
        }



        /* -------------------------------------------------------------
     * 4. View へ
     * ----------------------------------------------------------- */
        return view('admin.attendance.show', [
            'mode'              => 'edit',
            'userName'          => $user->name,
            'workDate'          => $workDate,
            'attendance'        => $attendance,
            'startAt'           => $startAt,
            'endAt'             => $endAt,
            'breaks'            => $breaks,
            'reason'            => $displayReason,
            'hasPendingRequest' => $hasPendingRequest,
            'pendingRequest'   => $pending,
        ]);
    }

}
