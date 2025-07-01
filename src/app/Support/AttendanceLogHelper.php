<?php

namespace App\Support;

use App\Models\TimeLog;
use Illuminate\Support\Collection;

/**
 * 打刻ログを「比較しやすい配列」に整形したり、
 * ２つのログ集合から “差分” を抽出したりするユーティリティ
 */
class AttendanceLogHelper
{
    /**
     * TimeLog コレクションを
     *  [
     *    'clock_in'      => '09:00',
     *    'clock_out'     => '18:05',
     *    'break_start#0' => '12:00',
     *    'break_end#0'   => '12:45',
     *    …
     *  ]
     *  という連想配列に変換する。
     *
     * @param  Collection<TimeLog>  $logs
     * @return array<string, string|null>
     */
    public static function toMap(Collection $logs): array
    {
        $breakIndex = 0;
        $map = [];

        foreach ($logs->sortBy('logged_at') as $log) {
            $key = match ($log->type) {
                'clock_in', 'clock_out'      => $log->type,
                'break_start', 'break_end'   => $log->type . '#' . $breakIndex,
            };

            $map[$key] = $log->logged_at->format('H:i');

            // break_start → break_end で同じインデックスに
            if ($log->type === 'break_end') {
                $breakIndex++;
            }
        }

        return $map;
    }

    /**
     * 「確定ログ」vs「申請中ログ」の *差分* を抽出して
     *  [
     *    ['label'=>'出勤',       'old'=>'09:00', 'new'=>'08:45'],
     *    ['label'=>'休憩2開始',  'old'=>null,    'new'=>'15:00'],
     *    …
     *  ] 形式で返す
     */
    public static function diffMap(array $confirmed, array $pending): Collection
    {
        // 両方の key を合わせてユニークに
        $keys = collect(array_merge(array_keys($confirmed), array_keys($pending)))->unique();

        return $keys->map(function ($key) use ($confirmed, $pending) {

            [$type, $idx] = array_pad(explode('#', $key), 2, 0);

            $old = $confirmed[$key] ?? null;
            $new = $pending[$key]   ?? null;

            if ($old === $new) {
                return null; // 差分なし
            }

            $label = match ($type) {
                'clock_in'     => '出勤',
                'clock_out'    => '退勤',
                'break_start'  => '休憩' . ($idx + 1) . '開始',
                'break_end'    => '休憩' . ($idx + 1) . '終了',
                default        => $type,
            };

            return compact('label', 'old', 'new');
        })
            ->filter()      // null を除外
            ->values();     // 0,1,2… の連番に
    }
}
