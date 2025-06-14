<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CorrectionRequest;
use App\Models\CorrectionRequestItem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use Illuminate\Support\Carbon;



class CorrectionRequestItemSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * 既存の CorrectionRequest それぞれに
         * 1〜3 件の明細を付与する（重複は作らない）
         */
        CorrectionRequest::query()
            ->with(['attendance.breakTimes'])        // 後で使うので eager load
            ->chunk(300, function ($requests) {

                foreach ($requests as $request) {

                    /** @var \App\Models\Attendance $attendance */
                    $attendance = $request->attendance;

                    if (!$attendance) {
                        continue;
                    }

                    // 既に明細があればスキップ（再実行安全）
                    if ($request->items()->exists()) {
                        continue;
                    }

                    // 1〜3 種類のフィールドをランダム選択
                    $fields = Arr::random([
                        CorrectionRequestItem::FIELD_START_TIME,
                        CorrectionRequestItem::FIELD_END_TIME,
                        CorrectionRequestItem::FIELD_BREAK_START,
                        CorrectionRequestItem::FIELD_BREAK_END,
                        CorrectionRequestItem::FIELD_NOTE,
                    ], random_int(1, 3));

                    foreach ($fields as $field) {

                        // —— before / after を決定 ————————————————
                        $before = $after = null;
                        $breakTimeId = null;

                        switch ($field) {
                            case CorrectionRequestItem::FIELD_START_TIME:
                                $before = $attendance->start_at->format('H:i');
                                $after  = Carbon::parse($attendance->start_at)
                                    ->addMinutes(random_int(-30, 30))
                                    ->format('H:i');
                                break;

                            case CorrectionRequestItem::FIELD_END_TIME:
                                // end_at が NULL ならスキップ（まだ退勤していないデータ）
                                if (!$attendance->end_at) {
                                    continue 2;
                                }
                                $before = $attendance->end_at->format('H:i');
                                $after  = Carbon::parse($attendance->end_at)
                                    ->addMinutes(random_int(-30, 60))
                                    ->format('H:i');
                                break;

                            case CorrectionRequestItem::FIELD_BREAK_START:
                            case CorrectionRequestItem::FIELD_BREAK_END:
                                $break = $attendance->breakTimes->first();
                                if (!$break) {
                                    continue 2;
                                }
                                $breakTimeId = $break->id;

                                if ($field === CorrectionRequestItem::FIELD_BREAK_START) {
                                    $before = $break->start_at->format('H:i');
                                    $after  = Carbon::parse($break->start_at)
                                        ->addMinutes(random_int(-10, 10))
                                        ->format('H:i');
                                } else {
                                    // BREAK_END
                                    if (!$break->end_at) {
                                        continue 2;
                                    }
                                    $before = $break->end_at->format('H:i');
                                    $after  = Carbon::parse($break->end_at)
                                        ->addMinutes(random_int(-10, 15))
                                        ->format('H:i');
                                }
                                break;

                            case CorrectionRequestItem::FIELD_NOTE:
                                $before = $attendance->note ?? '';
                                $after  = Str::limit($before, 50) . '（修正）';
                                break;
                        }

                        CorrectionRequestItem::updateOrCreate(
                            [
                                'correction_id' => $request->id,
                                'field'         => $field,
                                'break_time_id' => $breakTimeId,
                            ],
                            [
                                'before_value'  => $before,
                                'after_value'   => $after,
                            ]
                        );
                    }
                }
            });
    }
}
