<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * JEMBATAN (arah ke atas) — ringkasan agregat tiket untuk rapat GPER.
     * Direktur membaca summary ini (bukan tiket mentah). Prinsip data-driven
     * & need-to-know: statistik dihitung dulu sebelum rapat lalu disimpan.
     */
    public function up(): void
    {
        Schema::create('review_ticket_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('review_session_id');
            $table->string('category', 50)->nullable();      // kategori tiket yg diringkas (null = semua)
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->unsignedInteger('total_tickets')->default(0);
            $table->unsignedInteger('resolved_tickets')->default(0);
            $table->decimal('avg_duration_hours', 12, 2)->default(0); // rata-rata durasi penyelesaian
            $table->decimal('total_cost', 18, 2)->default(0);         // total biaya part
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('review_session_id', 'rts_session_idx');

            $table->foreign('review_session_id', 'rts_session_fk')
                ->references('id')->on('review_sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_ticket_summaries');
    }
};
