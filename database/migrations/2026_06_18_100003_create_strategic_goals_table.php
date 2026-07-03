<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul Strategis (level tertinggi) — tujuan perusahaan.
     * strategic_goals -> strategic_plans -> action_items.
     */
    public function up(): void
    {
        Schema::create('strategic_goals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('fiscal_year', 9)->nullable();    // mis. 2026 atau 2026/2027
            $table->unsignedBigInteger('owner_id')->nullable(); // penanggung jawab / direktur (users)
            $table->string('status', 20)->default('active');  // active | achieved | dropped
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->foreign('owner_id', 'sgo_owner_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_goals');
    }
};
