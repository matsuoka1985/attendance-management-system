<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrectionRequestItem extends Model
{
    use HasFactory;

    /*--------------------------------------------------------------
    | 修正対象フィールド定数
    |--------------------------------------------------------------*/
    public const FIELD_START_TIME  = 'start_time';
    public const FIELD_END_TIME    = 'end_time';
    public const FIELD_BREAK_START = 'break_start';
    public const FIELD_BREAK_END   = 'break_end';
    public const FIELD_NOTE        = 'note';

    /*--------------------------------------------------------------
    | 一括代入を許可する属性
    |--------------------------------------------------------------*/
    protected $fillable = [
        'correction_id',
        'break_time_id',
        'field',
        'before_value',
        'after_value',
    ];

    /*--------------------------------------------------------------
    | リレーション
    |--------------------------------------------------------------*/

    /** 親の修正申請ヘッダ */
    public function correctionRequest(): BelongsTo
    {
        return $this->belongsTo(CorrectionRequest::class, 'correction_id');
    }

    /** 対象休憩（NULL 可） */
    public function breakTime(): BelongsTo
    {
        return $this->belongsTo(BreakTime::class, 'break_time_id');
    }

    /*--------------------------------------------------------------
    | アクセサ：変更後値を日時として取得（対象が時刻の場合）
    |--------------------------------------------------------------*/
    public function getAfterValueAsDatetimeAttribute(): ?\Carbon\Carbon
    {
        //after_valueが時刻の時だけ、つまり備考(申請理由)ではないときにその時刻をCarbonオブジェクトに変換して扱いやすくする
        return in_array($this->field, [
            self::FIELD_START_TIME,
            self::FIELD_END_TIME,
            self::FIELD_BREAK_START,
            self::FIELD_BREAK_END,
        ])
            ? \Carbon\Carbon::createFromFormat('H:i', $this->after_value)
            : null;
    }
}
