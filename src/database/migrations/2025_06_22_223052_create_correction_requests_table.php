<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('correction_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained();

            // ── 元の勤怠 ─────────────────────────────
            $table->foreignId('attendance_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete(); // 勤怠削除時に修正申請も削除

            // ── 修正理由 ─────────────────────────────
            $table->string('reason', 255); // 修正理由

            // ── ステータスとレビュワー情報 ─────────────
            $table->enum('status', ['pending', 'approved'])
                ->default('pending'); // 修正状態

            $table->foreignId('reviewed_by') // 承認者ユーザID（NULL許可）
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('reviewed_at')->nullable(); // 承認日時

            // ── 自動タイムスタンプ（NULL不可）────────
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correction_requests');
    }
};
