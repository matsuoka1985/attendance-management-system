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

        foreach ($keys as $key) {
            $oldValue = $baseMap[$key]    ?? null;
            $newValue = $pendingMap[$key] ?? null;
            if ($oldValue === $newValue) {
                continue; // 変更なし
            }

            $label = match (true) {
                $key === 'start_at'       => '出勤',
                $key === 'end_at'         => '退勤',
                str_starts_with($key, 'break_start#') => '休憩' . substr($key, 12, 1) . '開始',
                str_starts_with($key, 'break_end#')   => '休憩' . substr($key, 10, 1) . '終了',
            };

            $result[] = compact('label', 'oldValue', 'newValue');
        }

        return $result;
    }

    /** TimeLog -> キー付き配列に正規化 */
    private static function toMap(Collection $logs): array
    {
        $map = [];
        $index = 1;

        foreach ($logs as $logEntry) {
            $formattedTime = $logEntry->logged_at->format('H:i');
            switch ($logEntry->type) {
                case 'clock_in':
                    $map['start_at']         = $formattedTime;
                    break;
                case 'clock_out':
                    $map['end_at']           = $formattedTime;
                    break;
                case 'break_start':
                    $map["break_start#{$index}"] = $formattedTime;
                    break;
                case 'break_end':
                    $map["break_end#{$index}"]   = $formattedTime;
                    $index++;
                    break;
            }
        }
        return $map;
    }
}
