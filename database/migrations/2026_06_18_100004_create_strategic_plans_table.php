<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rencana + anggaran turunan dari sebuah strategic_goal.
     */
    public function up(): void
    {
        Schema::create('strategic_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('strategic_goal_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('budget', 18, 2)->default(0);    // rencana anggaran
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('planned'); // planned | ongoing | done | cancelled
            $table->timestamps();

            $table->index('strategic_goal_id', 'spl_goal_idx');

            $table->foreign('strategic_goal_id', 'spl_goal_fk')
                ->references('id')->on('strategic_goals')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategic_plans');
    }
};
