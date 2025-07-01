<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /* ──────────── 基本ルール ──────────── */
    public function rules(): array
    {
        return [
            'mode'              => ['required', 'in:working,finished'],
            'attendance_id'     => ['nullable', 'integer', 'exists:attendances,id'],
            'work_date'         => ['required', 'date'],
            'start_at'          => ['required', 'date_format:H:i'],
            'end_at'            => ['nullable', 'date_format:H:i'],
            'breaks'            => ['array'],
            'breaks.*.start'    => ['nullable', 'date_format:H:i'],
            'breaks.*.end'      => ['nullable', 'date_format:H:i'],
            'reason'            => ['required', 'string', 'max:255'],
        ];
    }

    /* ──────────── カスタムバリデーション ──────────── */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {

            $mode    = $this->input('mode');            // working / finished
            $startAt = $this->input('start_at');
            $endAt   = $this->input('end_at');
            $breaks  = $this->input('breaks', []);

            /* 1) 退勤後は退勤必須 */
            if ($mode === 'finished' && ! $endAt) {
                $validator->errors()->add('end_at', '退勤後の修正では退勤時刻が必須です。');
            }

            /* 2) 出勤 < 退勤 */
            if ($startAt && $endAt && $startAt >= $endAt) {
                $validator->errors()->add('end_at', '出勤時間もしくは退勤時間が不適切な値です。');
            }

            /* 3) 休憩毎の判定 */
            foreach ($breaks as $i => $bk) {
                $s = $bk['start'] ?? null;
                $e = $bk['end']   ?? null;

                // 終了のみ入力
                if (! $s && $e) {
                    $validator->errors()->add("breaks.$i.start", '休憩終了を入力する場合は休憩開始も入力してください。');
                }

                // 退勤後はペア必須
                if ($mode === 'finished' && ($s xor $e)) {
                    $validator->errors()->add("breaks.$i.start", '退勤後は休憩開始・終了を両方入力してください。');
                }

                // 開始 ≦ 終了
                if ($s && $e && $s > $e) {
                    $validator->errors()->add("breaks.$i.end", '休憩終了は開始より後にしてください。');
                }

                // 勤務時間外
                if ($s && ($s < $startAt || ($endAt && $s > $endAt))) {
                    $validator->errors()->add("breaks.$i.start", '休憩時間が勤務時間外です');
                }
                if ($e && ($e < $startAt || ($endAt && $e > $endAt))) {
                    $validator->errors()->add("breaks.$i.end", '休憩時間が勤務時間外です');
                }

                // 連続休憩重複
                if ($i > 0) {
                    $prevEnd = $breaks[$i - 1]['end'] ?? null;
                    if ($s && $prevEnd && $s < $prevEnd) {
                        $validator->errors()->add("breaks.$i.start", '前の休憩と重複しています。');
                    }
                }
            }

            /* 4) 歯抜け行チェック（入力がある行は必ず前詰め） */
            $filledIdx = collect($breaks)
                ->filter(fn($v) => ($v['start'] ?? null) || ($v['end'] ?? null))
                ->keys()
                ->values();                             // 0,1,2…のはず
            foreach ($filledIdx as $pos => $idx) {
                if ($idx !== $pos) {
                    $validator->errors()->add('breaks', '休憩入力に空行があります。前の行を埋めてください。');
                    break;
                }
            }

            /* 5) 変更なしブロック（attendance_id があるとき） */
            // if ($this->filled('attendance_id') && ! $validator->fails()) {
            //     $attendance = Attendance::with(['timeLogs' => fn($q) => $q->orderBy('logged_at')])
            //         ->find($this->attendance_id);

            //     /* 現行データを整形 */
            //     $currentStart = optional(
            //         $attendance->timeLogs->firstWhere('type', 'clock_in')
            //     )->logged_at?->format('H:i');

            //     $currentEnd = optional(
            //         $attendance->timeLogs->where('type', 'clock_out')->last()
            //     )->logged_at?->format('H:i');

            //     // 休憩を安全にペア化
            //     $breakLogs = $attendance->timeLogs
            //         ->whereIn('type', ['break_start', 'break_end'])
            //         ->sortBy('logged_at')
            //         ->values();

            //     $currentBreaks = [];
            //     for ($i = 0; $i < $breakLogs->count(); $i += 2) {
            //         $currentBreaks[] = [
            //             'start' => optional($breakLogs[$i]   ?? null)->logged_at?->format('H:i'),
            //             'end'   => optional($breakLogs[$i + 1] ?? null)->logged_at?->format('H:i'),
            //         ];
            //     }

            //     $current = [
            //         'start_at' => $currentStart,
            //         'end_at'   => $currentEnd,
            //         'breaks'   => $currentBreaks,
            //     ];

            //     /* リクエスト側 */
            //     $requested = [
            //         'start_at' => $this->start_at,
            //         'end_at'   => $this->end_at,
            //         'breaks'   => collect($this->breaks ?? [])
            //             ->map(fn($b) => [
            //                 'start' => $b['start'] ?? null,
            //                 'end'   => $b['end']   ?? null,
            //             ])
            //             ->toArray(),
            //     ];

            //     if ($current == $requested) {
            //         $validator->errors()->add('base', '修正内容がありません。');
            //     }
            // }
        });
    }

    /* ──────────── 固定メッセージ ──────────── */
    public function messages(): array
    {
        return [
            // 出退勤
            'start_at.required'    => '出勤時間もしくは退勤時間が不適切な値です。',
            'start_at.date_format' => '出勤時間もしくは退勤時間が不適切な値です。',
            'end_at.date_format'   => '出勤時間もしくは退勤時間が不適切な値です。',

            // 休憩
            'breaks.*.start.date_format' => '休憩開始の時刻形式が不正です。',
            'breaks.*.end.date_format'   => '休憩終了の時刻形式が不正です。',

            // 備考
            'reason.required'      => '備考を記入してください。',
            'reason.max'           => '理由は255文字以内で入力してください。',
        ];
    }
}
