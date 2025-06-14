<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();

            // ── 対象勤怠 ────────────────────────────
            $table->foreignId('attendance_id')
                ->constrained()
                ->cascadeOnDelete();                                 // 勤怠削除 → 申請も削除

            // ── 申請内容 ────────────────────────────
            $table->string('reason', 255);                             // 申請理由
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending');                                // 申請状態

            // ── 承認情報 ────────────────────────────
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')                               // users.id を参照
                ->nullOnDelete();                                    // 承認者削除時は NULL
            $table->dateTime('reviewed_at')->nullable();               // 承認日時

            // ── タイムスタンプ (NOT NULL)────────────
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            // ── インデックス ──────────────────────────
            $table->index('attendance_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correction_requests');
    }
};
