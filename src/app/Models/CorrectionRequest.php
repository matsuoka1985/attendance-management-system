<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorrectionRequest extends Model
{
    use HasFactory;

    /*--------------------------------------------------------------
    | 定数：ステータス
    |--------------------------------------------------------------*/
    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /*--------------------------------------------------------------
    | 一括代入を許可する属性
    |--------------------------------------------------------------*/
    protected $fillable = [
        'attendance_id',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    /*--------------------------------------------------------------
    | 型キャスト
    |--------------------------------------------------------------*/
    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /*--------------------------------------------------------------
    | リレーション
    |--------------------------------------------------------------*/

    /** 対象勤怠 */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /** 承認者（管理者ユーザ） */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** 修正申請明細行 */
    public function items(): HasMany
    {
        // 第 2 引数に外部キー名を渡す
        return $this->hasMany(CorrectionRequestItem::class, 'correction_id');
    }

    /*--------------------------------------------------------------
    | ステータス判定ヘルパ
    |--------------------------------------------------------------*/
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
