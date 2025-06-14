<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    /*--------------------------------------------------------------
    | 一括代入を許可する属性
    |--------------------------------------------------------------*/
    protected $fillable = [
        'user_id',
        'work_date',
        'start_at',
        'end_at',
    ];

    /*--------------------------------------------------------------
    | 型キャスト
    |--------------------------------------------------------------*/
    protected $casts = [
        'work_date' => 'date',        // Y-m-d
        'start_at'  => 'datetime',    // Y-m-d H:i:s
        'end_at'    => 'datetime',
    ];

    /*--------------------------------------------------------------
    | リレーション
    |--------------------------------------------------------------*/
    /** 勤怠は 1 人のユーザに属する */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 勤怠は複数の休憩行を持つ */
    public function breakTimes(): HasMany
    {
        return $this->hasMany(BreakTime::class);
    }

    /** 勤怠に対して送られた修正申請（ヘッダ） */
    public function correctionRequests(): HasMany
    {
        return $this->hasMany(CorrectionRequest::class);
    }

    /*--------------------------------------------------------------
    | アクセサ：総勤務時間（秒）
    |--------------------------------------------------------------*/
    public function getTotalWorkSecondsAttribute(): ?int
    {
        if (!$this->start_at || !$this->end_at) {
            return null; // 未退勤
        }

        $breakSeconds = $this->breakTimes
            ->filter(fn($b) => $b->start_at && $b->end_at)
            ->sum(fn($b) => $b->end_at->diffInSeconds($b->start_at));

        return $this->end_at->diffInSeconds($this->start_at) - $breakSeconds;
    }

    /*--------------------------------------------------------------
    | アクセサ：総勤務時間（hh:mm 文字列）
    |--------------------------------------------------------------*/
    public function getTotalWorkFormattedAttribute(): ?string
    {
        $sec = $this->total_work_seconds;
        return $sec === null
            ? null
            : Carbon::createFromTimestampUTC($sec)->format('H:i');
    }
}
