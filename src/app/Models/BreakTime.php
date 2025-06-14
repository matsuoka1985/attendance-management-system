<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class BreakTime extends Model
{
    use HasFactory;

    /*--------------------------------------------------------------
    | 一括代入を許可する属性
    |--------------------------------------------------------------*/
    protected $fillable = [
        'attendance_id',
        'start_at',
        'end_at',
    ];

    /*--------------------------------------------------------------
    | 型キャスト
    |--------------------------------------------------------------*/
    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    /*--------------------------------------------------------------
    | リレーション
    |--------------------------------------------------------------*/
    /** 親勤怠 */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** この休憩に関連する修正申請明細 (nullable) */
    public function correctionRequestItems(): HasMany
    {
        return $this->hasMany(CorrectionRequestItem::class);
    }

    /*--------------------------------------------------------------
    | アクセサ：休憩時間（秒）
    |--------------------------------------------------------------*/
    public function getDurationSecondsAttribute(): ?int
    {
        return ($this->start_at && $this->end_at)
            ? $this->end_at->diffInSeconds($this->start_at)
            : null;
    }

    /*--------------------------------------------------------------
    | アクセサ：休憩時間（mm:ss 形式）
    |--------------------------------------------------------------*/
    public function getDurationFormattedAttribute(): ?string
    {
        $sec = $this->duration_seconds;
        return $sec === null
            ? null
            : Carbon::createFromTimestampUTC($sec)->format('H:i');
    }
}
