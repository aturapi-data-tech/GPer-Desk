<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Komentar / log progres tiket (diskusi teknis antar teknisi).
     */
    public function up(): void
    {
        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('comment');
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->index('ticket_id', 'tcm_ticket_idx');

            $table->foreign('ticket_id', 'tcm_ticket_fk')
                ->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('user_id', 'tcm_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
    }
};
