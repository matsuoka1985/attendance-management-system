<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_logs', function (Blueprint $table) {
            $table->id();

            /* ── 勤怠ID ───────────────────────────────
               承認前のドラフトは NULL、承認後に UPDATE で紐付け。
               ON DELETE SET NULL で勤怠削除時にログは残す。          */
            $table->foreignId('attendance_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /* ── 打刻情報 ───────────────────────────── */
            $table->dateTime('logged_at');                                     // 打刻時刻

            $table->enum('type', ['clock_in', 'clock_out', 'break_start', 'break_end']);

            /* ── 修正申請ID（通常は NULL）──────────── */
            $table->foreignId('correction_request_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            /* ── タイムスタンプ ───────────────────── */
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

        });

        /*  片方必須の CHECK 制約（MySQL 8.0 以上） */
        // 以下はアプリ層でチェックする
        // DB::statement("
        //     ALTER TABLE time_logs
        //     ADD CONSTRAINT chk_time_logs_link
        //     CHECK (
        //         attendance_id IS NOT NULL OR correction_request_id IS NOT NULL
        //     )
        // ");
    }

    public function down(): void
    {
        Schema::dropIfExists('time_logs');
    }
};
