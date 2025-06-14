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
        Schema::create('correction_request_items', function (Blueprint $table) {
            $table->id();

            // ── リレーション ──────────────────────────
            $table->foreignId('correction_id')
                ->constrained('correction_requests')
                ->cascadeOnDelete();                                 // ヘッダ削除 → 明細削除

            $table->foreignId('break_time_id')
                ->nullable()
                ->constrained('break_times')
                ->nullOnDelete();                                    // 休憩削除 → NULL

            // ── 修正内容 ────────────────────────────
            $table->enum('field', [
                'start_time',
                'end_time',
                'break_start',
                'break_end',
                'note',
            ]);

            $table->string('before_value', 255);
            $table->string('after_value', 255);

            // ── タイムスタンプ (NOT NULL)────────────
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            // ── インデックス & 一意制約 ───────────────
            $table->index('correction_id');

            $table->unique(
                ['correction_id', 'field', 'break_time_id'],
                'correction_request_items_unique'        
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correction_request_items');
    }
};
