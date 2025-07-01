<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * confirmedLogs と pendingLogs を突き合わせて
 * 「違うところだけ」配列で返すユーティリティ
 */
class AttendanceDiff
{
    /**
     * TimeLog のコレクション２つを比較し差分を返す
     *
     * @return array<array{label:string, old:string|null, new:string|null}>
     */
    public static function diff(Collection $base, Collection $pending): array
    {
        $baseMap    = self::toMap($base);
        $pendingMap = self::toMap($pending);

        $keys   = array_unique(array_merge(array_keys($baseMap), array_keys($pendingMap)));
        $result = [];

        foreach ($keys as $k) {
            $old = $baseMap[$k]    ?? null;
            $new = $pendingMap[$k] ?? null;
            if ($old === $new) {
                continue; // 変更なし
            }

            $label = match (true) {
                $k === 'start_at'       => '出勤',
                $k === 'end_at'         => '退勤',
                str_starts_with($k, 'break_start#') => '休憩' . substr($k, 12, 1) . '開始',
                str_starts_with($k, 'break_end#')   => '休憩' . substr($k, 10, 1) . '終了',
            };

            $result[] = compact('label', 'old', 'new');
        }

        return $result;
    }

    /** TimeLog -> キー付き配列に正規化 */
    private static function toMap(Collection $logs): array
    {
        $map = [];
        $idx = 1;

        foreach ($logs as $l) {
            $t = $l->logged_at->format('H:i');
            switch ($l->type) {
                case 'clock_in':
                    $map['start_at']         = $t;
                    break;
                case 'clock_out':
                    $map['end_at']           = $t;
                    break;
                case 'break_start':
                    $map["break_start#{$idx}"] = $t;
                    break;
                case 'break_end':
                    $map["break_end#{$idx}"]   = $t;
                    $idx++;
                    break;
            }
        }
        return $map;
    }
}
