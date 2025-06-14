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
        Schema::create('break_times', function (Blueprint $table) {
            $table->id();
            
            // ── 親勤怠 ───────────────────────────────
            $table->foreignId('attendance_id')
                ->constrained()                                      // attendances.id を参照
                ->cascadeOnDelete();                                 // 勤怠削除→休憩も削除

            // ── 休憩開始・終了 ────────────────────────
            $table->dateTime('start_at');                              // 休憩開始 (NOT NULL)
            $table->dateTime('end_at')->nullable();                    // 休憩終了 (NULL = 未終了)

            // ── タイムスタンプ (NOT NULL) ─────────────
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
        Schema::dropIfExists('break_times');
    }
};
