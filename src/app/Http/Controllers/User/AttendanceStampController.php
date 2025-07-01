<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\TimeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceStampController extends Controller
{
    /* ───────── 画面表示 ───────── */

    public function create()
    {
        $user  = Auth::user();
        $today = Carbon::today();

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        $logs = $attendance
            ? $this->filteredTimeLogs($attendance)
            : collect();

        $status = $this->determineStatus($logs);

        return view('user.attendance.stamp', [
            'attendance' => $attendance,
            'status'     => $status,
        ]);
    }

    /* ───────── 状態判定 ───────── */

    private function determineStatus($logs): string
    {
        if ($logs->isEmpty()) {
            return 'not_working';
        }

        $last = $logs->last();

        if ($last->type === 'clock_out') {
            return 'finished';
        }

        if ($last->type === 'break_start') {
            return 'on_break';
        }

        return 'working';
    }

    /* ───────── 出勤 ───────── */

    // public function start()
    // {
    //     $user  = Auth::user();
    //     $today = Carbon::today();

    //     DB::transaction(function () use ($user, $today) {
    //         $attendance = Attendance::firstOrCreate([
    //             'user_id'   => $user->id,
    //             'work_date' => $today,
    //         ]);

    //         $logs = $this->filteredTimeLogs($attendance);
    //         if ($logs->contains('type', 'clock_in')) {
    //             abort(403, '本日はすでに出勤済みです。');
    //         }

    //         $attendance->timeLogs()->create([
    //             'logged_at' => now(),
    //             'type'      => 'clock_in',
    //         ]);
    //     });

    //     return back()->with('success', '出勤しました。');
    // }

    // 本体から出勤処理を分離
    public function start()
    {
        $attendance = $this->performClockIn(); // 戻り値を取得

        return back()->with('success', '出勤しました。');
    }

    // テストで利用したい処理本体
    public function performClockIn(): Attendance
    {
        $user  = Auth::user();
        $today = Carbon::today();

        return DB::transaction(function () use ($user, $today) {
            $attendance = Attendance::firstOrCreate([
                'user_id'   => $user->id,
                'work_date' => $today,
            ]);

            $logs = $this->filteredTimeLogs($attendance);
            if ($logs->contains('type', 'clock_in')) {
                abort(403, '本日はすでに出勤済みです。');
            }

            $attendance->timeLogs()->create([
                'logged_at' => now(),
                'type'      => 'clock_in',
            ]);

            return $attendance;
        });
    }



    /* ───────── 休憩開始 ───────── */

    // public function startBreak()
    // {
    //     $attendance = $this->todayAttendanceOrFail();

    //     $logs = $this->filteredTimeLogs($attendance);

    //     if ($logs->contains('type', 'clock_out')) {
    //         return back()->with('error', '退勤後のため操作できません。');
    //     }

    //     $last = $logs->last();
    //     if ($last && $last->type === 'break_start') {
    //         return back()->with('error', 'すでに休憩中です。');
    //     }

    //     $attendance->timeLogs()->create([
    //         'logged_at' => now(),
    //         'type'      => 'break_start',
    //     ]);

    //     return back()->with('success', '休憩を開始しました。');
    // }
    public function startBreak()
    {
        try {
            $this->performStartBreak();
            return back()->with('success', '休憩を開始しました。');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function performStartBreak(): Attendance
    {
        $attendance = $this->todayAttendanceOrFail();
        $logs = $this->filteredTimeLogs($attendance);

        if ($logs->contains('type', 'clock_out')) {
            throw new \RuntimeException('退勤後のため操作できません。');
        }

        $last = $logs->last();
        if ($last && $last->type === 'break_start') {
            throw new \RuntimeException('すでに休憩中です。');
        }

        $attendance->timeLogs()->create([
            'logged_at' => now(),
            'type'      => 'break_start',
        ]);

        return $attendance;
    }


    /* ───────── 休憩終了 ───────── */

    // public function endBreak()
    // {
    //     $attendance = $this->todayAttendanceOrFail();

    //     $logs = $this->filteredTimeLogs($attendance);
    //     $last = $logs->last();
    //     if (!$last || $last->type !== 'break_start') {
    //         return back()->with('error', '休憩中ではありません。');
    //     }

    //     $attendance->timeLogs()->create([
    //         'logged_at' => now(),
    //         'type'      => 'break_end',
    //     ]);

    //     return back()->with('success', '休憩を終了しました。');
    // }

    public function endBreak()
    {
        try {
            $this->performEndBreak();
            return back()->with('success', '休憩を終了しました。');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function performEndBreak(): Attendance
    {
        $attendance = $this->todayAttendanceOrFail();
        $logs = $this->filteredTimeLogs($attendance);

        $last = $logs->last();
        if (!$last || $last->type !== 'break_start') {
            throw new \RuntimeException('休憩中ではありません。');
        }

        $attendance->timeLogs()->create([
            'logged_at' => now(),
            'type'      => 'break_end',
        ]);

        return $attendance;
    }


    /* ───────── 退勤 ───────── */

    public function end()
    {
        $attendance = $this->todayAttendanceOrFail();

        $logs = $this->filteredTimeLogs($attendance);
        $last = $logs->last();

        if ($last && $last->type === 'clock_out') {
            return back()->with('error', 'すでに退勤済みです。');
        }

        if ($last && $last->type === 'break_start') {
            return back()->with('error', '休憩中は退勤できません。');
        }

        $attendance->timeLogs()->create([
            'logged_at' => now(),
            'type'      => 'clock_out',
        ]);

        return back()->with('success', '退勤しました。');
    }

    /* ───────── ヘルパ ───────── */

    private function todayAttendanceOrFail(): Attendance
    {
        $user  = Auth::user();
        $today = Carbon::today();

        return Attendance::where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->firstOrFail();
    }

    private function filteredTimeLogs(Attendance $attendance)
    {
        $correction = $attendance->correctionRequests()
            ->where('status', 'approved')
            ->latest('created_at')
            ->first();

        $query = $attendance->timeLogs()->orderBy('logged_at');

        if ($correction) {
            $query->where('created_at', '>=', $correction->created_at);
        }

        return $query->get();
    }
}
