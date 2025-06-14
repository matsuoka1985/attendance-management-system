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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();

            // ── 関連ユーザ ─────────────────────────────
            $table->foreignId('user_id')
                ->constrained()                                      // users.id を参照
                ->cascadeOnDelete();                                 // ユーザ削除時に勤怠も削除（運用ポリシーで調整可）

            // ── 打刻情報 ─────────────────────────────
            $table->date('work_date');                                 // 勤務日
            $table->dateTime('start_at');                              // 出勤打刻
            $table->dateTime('end_at')->nullable();                    // 退勤打刻（未退勤時は NULL）。出勤後まだ退勤していない状態であればNULLとなる。

            // ── 自動タイムスタンプ（NULL不可）────────
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            // ── 複合ユニーク：同一ユーザ×同一日は1行 ──
            $table->unique(['user_id', 'work_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
