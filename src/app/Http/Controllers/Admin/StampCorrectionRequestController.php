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

class StampCorrectionRequestController extends Controller
{
    /**
     * すべての一般ユーザーが提出した修正申請の一覧
     * GET /admin/stamp_correction_request/list
     * name: admin.request.index
     */
    public function index()
    {
        /* ───────── 1. 承認待ち ───────── */
        $pendingRequests = CorrectionRequest::with([
            'attendance:id,user_id,work_date',
            'attendance.user:id,name',
            'user:id,name',
            'timeLogs' => fn($query) => $query->orderBy('logged_at'),
        ])
            ->where('status', CorrectionRequest::STATUS_PENDING)
            ->latest('created_at')                           // 申請日時の降順
            ->get()
            ->each(function (CorrectionRequest $req) {
                // ─ 対象日（勤怠が無い場合はログ先頭の日）
                $req->target_date = $req->attendance
                    ? $req->attendance->work_date
                    : optional($req->timeLogs->first())->logged_at?->toDateString();

                // ─ 一覧で使う申請日時
                $req->apply_date  = $req->created_at;
            });

        /* ───────── 2. 承認済み ───────── */
        $approvedRequests = CorrectionRequest::with([
            'attendance:id,user_id,work_date',
            'attendance.user:id,name',
            'user:id,name',
            'reviewer:id,name',
            'timeLogs' => fn($query) => $query->orderBy('logged_at'),
        ])
            ->where('status', CorrectionRequest::STATUS_APPROVED)
            ->whereHas('user', fn($query) => $query->where('role', '!=', 'admin')) // 追加: 管理者作成を除外
            ->latest('created_at')                           // ★ 申請日時の降順で統一
            ->get()
            ->each(function (CorrectionRequest $req) {
                // ─ 対象日
                $req->target_date = $req->attendance
                    ? $req->attendance->work_date
                    : optional($req->timeLogs->first())->logged_at?->toDateString();

                // ─ 一覧で使う申請日時（承認済みでも created_at を表示）
                $req->apply_date  = $req->created_at;
            });

        /* ───────── 3. ビュー ───────── */
        return view(
            'admin.stamp_correction_requests.index',
            compact('pendingRequests', 'approvedRequests')
        );
    }




    /**
     * 管理者による「即時反映」勤怠登録／修正
     *
     * ルート: POST /admin/attendance/{attendance?}/fix
     *        {attendance} … 既存勤怠ID | new | 0 | (省略)
     *
     * @param  AttendanceCorrectionRequest $request
     * @param  string|null                 $attendance  ルートパラメータ
     */
    public function store(AttendanceCorrectionRequest $request, string $attendance = null)
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();          // ログイン管理者

        /* ---------- 1. 対象スタッフ ID ---------- */
        $staffId = $request->input('user_id');          // hidden で必須送信
        if (!$staffId) {
            return back()->withErrors('スタッフ ID が送信されていません。');
        }

        /* ---------- 2. 勤怠行を取得 or 新規 ---------- */
        if ($attendance && ctype_digit($attendance)) {
            // 数値なら既存勤怠を編集
            $attendance = Attendance::findOrFail($attendance);
        } else {
            // new / 0 / null → 新規作成
            $workDate   = Carbon::parse($request->work_date)->startOfDay();
            $attendance = Attendance::firstOrCreate(
                ['user_id' => $staffId, 'work_date' => $workDate->toDateString()],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        /* ---------- 3. 即時承認付き修正を確定 ---------- */
        DB::transaction(function () use ($request, $admin, $attendance, $staffId) {

            // 3-1. CorrectionRequest（即時 APPROVED）
            $correction = CorrectionRequest::create([
                'user_id'       => $admin->id, // 管理者が申請者
                'attendance_id' => $attendance->id,
                'reason'        => $request->reason ?: '管理者による修正',
                'status'        => CorrectionRequest::STATUS_APPROVED,
                'reviewed_by'   => $admin->id,
                'reviewed_at'   => now(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // 3-2. TimeLog を丸ごと作成
            $logDate = Carbon::parse($attendance->work_date);

            // 出勤
            TimeLog::create([
                'attendance_id'         => $attendance->id,
                'logged_at'             => $logDate->copy()->setTimeFromTimeString($request->start_at),
                'type'                  => 'clock_in',
                'correction_request_id' => $correction->id,
            ]);

            // 休憩
            foreach ($request->breaks ?? [] as $breakPair) {
                foreach (['start' => 'break_start', 'end' => 'break_end'] as $breakKey => $type) {
                    if ($breakTime = $breakPair[$breakKey] ?? null) {
                        TimeLog::create([
                            'attendance_id'         => $attendance->id,
                            'logged_at'             => $logDate->copy()->setTimeFromTimeString($breakTime),
                            'type'                  => $type,
                            'correction_request_id' => $correction->id,
                        ]);
                    }
                }
            }

            // 退勤
            if ($request->filled('end_at')) {
                TimeLog::create([
                    'attendance_id'         => $attendance->id,
                    'logged_at'             => $logDate->copy()->setTimeFromTimeString($request->end_at),
                    'type'                  => 'clock_out',
                    'correction_request_id' => $correction->id,
                ]);
            }
        });

        /* ---------- 4. リダイレクト ---------- */
        // ここまで来ればトランザクション成功。$attendance は Model
        return redirect()
            ->route('admin.attendance.show', $attendance->id)
            ->with('success', '勤怠を登録／修正し、即時反映しました。');
    }

    /**
     * 修正申請詳細（承認用プレビュー画面）
     *
     * ルート : GET /admin/stamp_correction_request/approve/{correction_request}
     * name  : admin.request.approve
     */
    public function approve(int $correctionRequestId)
    {
        // 1. 申請と関連データを取得
        $correction = CorrectionRequest::with([
            'user:id,name',
            'attendance.user:id,name',
            'timeLogs' => fn($query) => $query->orderBy('logged_at'),
        ])->findOrFail($correctionRequestId);

        // 2. 基本情報
        $userName   = $correction->user->name ?? '退職済みユーザ';
        $attendance = $correction->attendance;
        $workDate   = $attendance
            ? Carbon::parse($attendance->work_date)
            : Carbon::parse($correction->timeLogs->first()->logged_at)->startOfDay();

        // 3. 承認前データ（base）の抽出
        if ($attendance) {
            // この申請の前に承認された最新申請日時
            $prevApprovedAt = $attendance->correctionRequests()
                ->where('status', CorrectionRequest::STATUS_APPROVED)
                ->where('created_at', '<', $correction->created_at)
                ->latest('created_at')
                ->value('created_at');

            $baseLogs = TimeLog::where('attendance_id', $attendance->id)
                ->where(function ($query) {
                    $query->whereNull('correction_request_id')
                        ->orWhereHas(
                            'correctionRequest',
                            fn($correctionQuery) =>
                            $correctionQuery->where('status', CorrectionRequest::STATUS_APPROVED)
                        );
                })
                // 前回承認以降
                ->when($prevApprovedAt, fn($query) => $query->where('created_at', '>=', $prevApprovedAt))
                // 今回申請より前まで
                ->where('created_at', '<', $correction->created_at)
                ->orderBy('logged_at')
                ->get();

            $base = $this->logsToSchema($baseLogs);
        } else {
            $base = [
                'start'  => '',
                'end'    => '',
                'breaks' => [['start' => '', 'end' => '']],
            ];
        }

        // 4. プレビュー（今回申請のドラフト）
        $preview = $this->logsToSchema($correction->timeLogs);

        // 5. 差分リスト
        $diffs = $this->makeDiffs($base, $preview);


        // 6. 新しい申請（今回の申請より後に作成された）を取得
        $newerRequests = $attendance
            ? $attendance->correctionRequests()
            ->where('created_at', '>', $correction->created_at)
            ->orderBy('created_at')
            ->get()
            : collect();

        // 6. View へ
        return view('admin.stamp_correction_requests.approve', [
            'correctionRequest' => $correction,
            'attendance'        => $attendance,
            'userName'          => $userName,
            'workDate'          => $workDate,
            'reason'            => $correction->reason,
            'base'              => $base,
            'preview'           => $preview,
            'diffs'             => $diffs,
            'isPending'         => $correction->status === CorrectionRequest::STATUS_PENDING,
            'newerRequests' => $newerRequests,

        ]);
    }




    /* ────────────────────────────────────────────────
 |  以下２つのヘルパは同 Controller 内の private メソッド
 |  既存 show() のロジックをそのまま切り出しています。
 * ────────────────────────────────────────────────*/

    /**
     * 取得した TimeLog コレクション → {start,end,breaks[]} 形式へ整形
     */
    private function logsToSchema(\Illuminate\Support\Collection $logs): array
    {
        $schema = [
            'start'  => optional($logs->firstWhere('type', 'clock_in'))->logged_at?->format('H:i') ?? '',
            'end'    => optional($logs->where('type', 'clock_out')->last())->logged_at?->format('H:i') ?? '',
            'breaks' => [],
        ];

        $stk = null;
        foreach ($logs as $timeLog) {
            if ($timeLog->type === 'break_start') {
                $stk = $timeLog->logged_at;
            } elseif ($timeLog->type === 'break_end' && $stk) {
                $schema['breaks'][] = [
                    'start' => $stk->format('H:i'),
                    'end'   => $timeLog->logged_at->format('H:i'),
                ];
                $stk = null;
            }
        }

        if (!$schema['breaks']) {
            $schema['breaks'][] = ['start' => '', 'end' => '']; // 空行１本
        }

        return $schema;
    }

    /**
     * base と preview を比べて差分配列を作成
     */
    private function makeDiffs(array $base, array $preview): array
    {
        $diffs = [];

        if ($base['start'] !== $preview['start']) {
            $diffs[] = ['label' => '出勤', 'old' => $base['start'], 'new' => $preview['start']];
        }
        if ($base['end'] !== $preview['end']) {
            $diffs[] = ['label' => '退勤', 'old' => $base['end'], 'new' => $preview['end']];
        }

        $max = max(count($base['breaks']), count($preview['breaks']));
        for ($i = 0; $i < $max; $i++) {
            $baseBreak    = $base['breaks'][$i]    ?? ['start' => null, 'end' => null];
            $previewBreak = $preview['breaks'][$i] ?? ['start' => null, 'end' => null];

            if ($baseBreak['start'] !== $previewBreak['start']) {
                $diffs[] = ['label' => '休憩' . ($i + 1) . '開始', 'old' => $baseBreak['start'], 'new' => $previewBreak['start']];
            }
            if ($baseBreak['end'] !== $previewBreak['end']) {
                $diffs[] = ['label' => '休憩' . ($i + 1) . '終了', 'old' => $baseBreak['end'], 'new' => $previewBreak['end']];
            }
        }

        return $diffs;
    }

    /**
     * POST  /admin/stamp_correction_request/approve/{correction_request}
     * name  admin.request.approve.execute
     *
     * 「承認待ちの修正申請」を確定させて勤怠へ反映する処理
     */
    public function approveExecute(Request $request, int $correction_request)
    {
        /** @var \App\Models\Admin $admin */
        $admin = Auth::guard('admin')->user();

        /* ───── 1. 対象申請を取得（要 pending） ───── */
        $correction = CorrectionRequest::with([
            'timeLogs'   => fn($query) => $query->orderBy('logged_at'),
            'attendance',           // まだ null のこともある
            'user:id,name'          // 申請者
        ])
            ->where('id', $correction_request)
            ->where('status', CorrectionRequest::STATUS_PENDING)
            ->firstOrFail();

        /* ───── 2. 勤怠レコードを用意 ───── */
        // ─ 申請ログの最初の logged_at を基準に勤務日を決定
        $firstLog = $correction->timeLogs->first();
        abort_if(!$firstLog, 404, '打刻ログが見つかりません。');

        $workDate = $firstLog->logged_at->startOfDay();
        $attendance = $correction->attendance    // 既に紐付いていたらそれを利用
            ?? Attendance::firstOrCreate(
                [
                    'user_id'   => $correction->user_id,
                    'work_date' => $workDate->toDateString(),
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

        /* ───── 3. 承認トランザクション ───── */
        DB::transaction(function () use ($correction, $attendance, $admin) {

            /* 3-1. 申請ヘッダを APPROVED に更新 */
            $correction->update([
                'status'        => CorrectionRequest::STATUS_APPROVED,
                'attendance_id' => $attendance->id,
                'reviewed_by'   => $admin->id,
                'reviewed_at'   => now(),
            ]);

            /* 3-2. 申請が持つ打刻ログを勤怠に帰属させる */
            TimeLog::where('correction_request_id', $correction->id)
                ->update(['attendance_id' => $attendance->id]);
        });

        /* ───── 4. リダイレクト ───── */
        return redirect()
            ->route('admin.request.approve', $correction->id)
            ->with('success', '修正申請を承認し、勤怠へ反映しました。');
    }

}
