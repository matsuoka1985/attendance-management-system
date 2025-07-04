<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\CorrectionBreakTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CorrectionRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\TimeLog;




class StampCorrectionRequestController extends Controller
{
    /**
     * 一般ユーザー: 自分が出した修正申請の一覧
     *   - pending   : 承認待ち
     *   - approved… : 承認／却下済み
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        /* ── 共通 eager-load ──
       timeLogs を最初の１件だけ取れば OK なので orderBy + limit 1 */
        $baseWith = [
            'attendance:id,work_date,user_id',
            'attendance.user:id,name',
            'timeLogs' => fn($q) => $q->orderBy('logged_at')->limit(1),
        ];

        /* ───────── 承認待ち ───────── */
        $pendingRequests = CorrectionRequest::with($baseWith)
            ->where('user_id', $user->id)
            ->where('status', CorrectionRequest::STATUS_PENDING)
            ->latest('created_at')
            ->get();

        /* ───────── 承認／却下済み ───────── */
        $approvedRequests = CorrectionRequest::with($baseWith + ['reviewer:id,name'])
            ->where('user_id', $user->id)
            ->whereIn('status', [
                CorrectionRequest::STATUS_APPROVED,
                CorrectionRequest::STATUS_REJECTED,
            ])
            ->orderByRaw('COALESCE(reviewed_at, updated_at) DESC')
            ->get();

        /* ── 対象日（target_date）を付与 ──
       ① 勤怠があれば  work_date
       ② 無ければ      draft の先頭打刻日 */
        $addTarget = function ($col) {
            return $col->each(function ($r) {
                $r->target_date =
                    $r->attendance?->work_date
                    ?? optional($r->timeLogs->first())->logged_at?->toDateString();
            });
        };

        $addTarget($pendingRequests);
        $addTarget($approvedRequests);

        return view(
            'user.stamp_correction_requests.index',
            compact('pendingRequests', 'approvedRequests')
        );
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * 勤怠打刻修正申請処理
     */
    public function store(AttendanceCorrectionRequest $request)
    {
        $user = Auth::user();
        DB::transaction(function () use ($request, $user) {

            /* ① 対象勤怠行（あるいは NULL） */
            $attendance = $request->filled('attendance_id')
                ? Attendance::where('id', $request->attendance_id)
                ->where('user_id', $user->id) //認可処理
                ->firstOrFail()
                : null;

            $workDate = $attendance
                ? Carbon::parse($attendance->work_date)
                : Carbon::parse($request->work_date);

            /* ② 修正申請ヘッダ */
            $correction = CorrectionRequest::create([
                'user_id'       => $user->id,
                'attendance_id' => $attendance?->id,
                'reason'        => $request->reason,
                'status'        => CorrectionRequest::STATUS_PENDING,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            /* ③ 打刻ログ（correction_request_id 付き）を作成 */
            // ---- 出勤 ----
            TimeLog::create([
                'attendance_id'         => $attendance?->id,
                'logged_at'             => $workDate->copy()->setTimeFromTimeString($request->start_at),
                'type'                  => 'clock_in',
                'correction_request_id' => $correction->id,
            ]);

            // ---- 休憩 ----
            foreach ($request->breaks ?? [] as $bk) {
                $s = $bk['start'] ?? null;
                $e = $bk['end']   ?? null;

                // 勤務中モードでは片方だけでも挿入可
                if ($s) {
                    TimeLog::create([
                        'attendance_id'         => $attendance?->id,
                        'logged_at'             => $workDate->copy()->setTimeFromTimeString($s),
                        'type'                  => 'break_start',
                        'correction_request_id' => $correction->id,
                    ]);
                }
                if ($e) {
                    TimeLog::create([
                        'attendance_id'         => $attendance?->id,
                        'logged_at'             => $workDate->copy()->setTimeFromTimeString($e),
                        'type'                  => 'break_end',
                        'correction_request_id' => $correction->id,
                    ]);
                }
            }

            // ---- 退勤 ----  (勤務中モードでは無い場合のみ)
            if ($request->filled('end_at')) {
                TimeLog::create([
                    'attendance_id'         => $attendance?->id,
                    'logged_at'             => $workDate->copy()->setTimeFromTimeString($request->end_at),
                    'type'                  => 'clock_out',
                    'correction_request_id' => $correction->id,
                ]);
            }
        });

        return redirect()->route('request.index')
            ->with('success', '修正申請を送信しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
