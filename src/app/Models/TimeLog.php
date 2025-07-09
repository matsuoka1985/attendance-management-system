<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeLog extends Model
{
    use HasFactory;

    /*--------------------------------------------------------------
    | 打刻種別定数
    |--------------------------------------------------------------*/
    public const TYPE_CLOCK_IN    = 'clock_in';
    public const TYPE_CLOCK_OUT   = 'clock_out';
    public const TYPE_BREAK_START = 'break_start';
    public const TYPE_BREAK_END   = 'break_end';


    protected $fillable = [
        'attendance_id',
        'logged_at',
        'type',
        'correction_request_id',
    ];

    /*--------------------------------------------------------------
    | 型キャスト
    |--------------------------------------------------------------*/
    protected $casts = [
        'logged_at' => 'datetime',
    ];

    /*--------------------------------------------------------------
    | リレーション
    |--------------------------------------------------------------*/

    /**
     * 対象の勤怠
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * 修正申請（NULL許容）
     */
    public function correctionRequest(): BelongsTo
    {
        return $this->belongsTo(CorrectionRequest::class);
    }

    /*--------------------------------------------------------------
    | 種別判定ヘルパ
    |--------------------------------------------------------------*/

    public function isClockIn(): bool
    {
        return $this->type === self::TYPE_CLOCK_IN;
    }

    public function isClockOut(): bool
    {
        return $this->type === self::TYPE_CLOCK_OUT;
    }

    public function isBreakStart(): bool
    {
        return $this->type === self::TYPE_BREAK_START;
    }

    public function isBreakEnd(): bool
    {
        return $this->type === self::TYPE_BREAK_END;
    }
}
