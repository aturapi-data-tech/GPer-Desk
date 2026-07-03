<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul Operasional — mencatat gangguan hardware: teknisi, status, solusi.
     * Nama FK/index dibuat eksplisit & pendek (Oracle 10g, identifier <= 30).
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no', 30);                 // nomor tiket human-readable, mis. TIC-2026-0001
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 50)->nullable();      // Hardware / Network / AC / dll
            $table->string('priority', 20)->default('medium'); // low | medium | high | critical
            $table->string('status', 20)->default('open');     // open | in_progress | resolved | closed
            $table->string('location')->nullable();          // ruangan / lokasi gangguan

            $table->unsignedBigInteger('reported_by')->nullable(); // pelapor (users)
            $table->unsignedBigInteger('technician_id')->nullable(); // teknisi penanggung jawab (users)

            $table->text('resolution')->nullable();          // solusi teknis
            $table->decimal('part_cost', 15, 2)->default(0); // biaya part pengganti
            $table->string('attachment_path')->nullable();   // lampiran file

            $table->timestamp('reported_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique('ticket_no', 'tic_ticket_no_uq');
            $table->index('status', 'tic_status_idx');
            $table->index('category', 'tic_category_idx');

            $table->foreign('reported_by', 'tic_reported_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('technician_id', 'tic_technician_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
