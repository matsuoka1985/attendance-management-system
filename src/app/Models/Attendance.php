<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
    ];

    protected $casts = [
        'work_date' => 'date',
    ];

    /**
     * 所属ユーザ
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 打刻ログ（出勤、退勤、休憩等すべて）
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    public function breaks(): HasMany
    {
        // break_start / break_end だけを抜く限定リレーション
        return $this->timeLogs()
            ->whereIn('type', ['break_start', 'break_end'])
            ->orderBy('logged_at');
    }

    /**
     * 修正申請一覧
     */
    public function correctionRequests(): HasMany
    {
        return $this->hasMany(CorrectionRequest::class);
    }
}
