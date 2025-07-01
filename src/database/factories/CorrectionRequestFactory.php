<?php

namespace Database\Factories;

use App\Models\CorrectionRequest;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CorrectionRequestFactory extends Factory
{
    protected $model = CorrectionRequest::class;

    private array $reasons = [
        '打刻漏れ',
        '操作ミス',
        '早退',
        '残業申請',
        '障害発生',
    ];

    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'attendance_id' => Attendance::factory(),
            'reason'        => $this->faker->randomElement($this->reasons),
            'status'        => CorrectionRequest::STATUS_PENDING,
            'reviewed_by'   => null,
            'reviewed_at'   => null,
        ];
    }

    /* ---------- 承認／却下ステート ---------- */

    public function approved(User $admin = null): static
    {
        return $this->state(fn() => [
            'status'      => CorrectionRequest::STATUS_APPROVED,
            'reviewed_by' => $admin?->id ?? User::factory()->admin()->create()->id,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(User $admin = null): static
    {
        return $this->state(fn() => [
            'status'      => CorrectionRequest::STATUS_REJECTED,
            'reviewed_by' => $admin?->id ?? User::factory()->admin()->create()->id,
            'reviewed_at' => now(),
        ]);
    }
}
