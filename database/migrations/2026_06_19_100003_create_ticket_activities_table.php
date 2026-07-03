<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak/timeline tiket — log kejadian + waktu (dibuat, ganti status, assign,
     * dll). Dipakai untuk menampilkan footprint & menghitung durasi/SLA.
     */
    public function up(): void
    {
        Schema::create('ticket_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 30);            // created | status_changed | assigned | reopened
            $table->string('from_value', 100)->nullable();
            $table->string('to_value', 100)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('ticket_id', 'tac_ticket_idx');

            $table->foreign('ticket_id', 'tac_ticket_fk')->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('user_id', 'tac_user_fk')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_activities');
    }
};
