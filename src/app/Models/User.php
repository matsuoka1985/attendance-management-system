<?php

namespace App\Models;


use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;


class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /* ───── リレーション ───── */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function correctionRequestsReviewed(): HasMany
    {
        return $this->hasMany(CorrectionRequest::class, 'reviewed_by');
    }

    /* ───── 便利メソッド ───── */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isClockedOutToday(): bool
    {

        $attendance = $this->attendances()
            ->whereDate('work_date', today())
            ->first();
        if (!$attendance) {
            return false;
        }

        $approvedRequest = $attendance->correctionRequests()
            ->where('status', 'approved')
            ->latest('created_at')
            ->first();

        $logs = $attendance->timeLogs()
            ->when(
                $approvedRequest,
                fn($query) =>
                $query->where('created_at', '>=', $approvedRequest->created_at)
            )
            ->orderBy('logged_at')
            ->get();

        return $logs->last()?->type === 'clock_out';
    }
}
