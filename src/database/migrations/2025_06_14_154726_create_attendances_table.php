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
            $table->id(); // 勤怠ID

            // ── 関連ユーザ ─────────────────────────────
            $table->foreignId('user_id') // ユーザID
                ->constrained()
                ->cascadeOnDelete();

            // ── 勤務日 ───────────────────────────────
            $table->date('work_date'); // 勤務日（1ユーザ1日1件を保証）

            // ── 自動タイムスタンプ（NULL不可）────────
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // ── ユニーク制約 ───────────────────────
            $table->unique(['user_id', 'work_date']); // 同一ユーザ・同一勤務日は1件のみ
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
