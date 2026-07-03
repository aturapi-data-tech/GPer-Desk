<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * JEMBATAN (arah ke bawah) — menghubungkan action_item strategis ke
     * tiket-tiket teknis yang mengeksekusinya. Satu action bisa banyak tiket.
     */
    public function up(): void
    {
        Schema::create('action_ticket_link', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('action_item_id');
            $table->unsignedBigInteger('ticket_id');
            $table->timestamps();

            $table->unique(['action_item_id', 'ticket_id'], 'atl_action_ticket_uq');

            $table->foreign('action_item_id', 'atl_action_fk')
                ->references('id')->on('action_items')->cascadeOnDelete();
            $table->foreign('ticket_id', 'atl_ticket_fk')
                ->references('id')->on('tickets')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_ticket_link');
    }
};
