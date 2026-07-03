<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Langkah strategis konkret turunan dari strategic_plan.
     * Mis. "Ganti semua HDD server" — nanti di-link ke tiket via action_ticket_link.
     */
    public function up(): void
    {
        Schema::create('action_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('strategic_plan_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable(); // PIC (users)
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('todo');   // todo | in_progress | done | blocked
            $table->unsignedTinyInteger('progress')->default(0); // persen 0-100
            $table->timestamps();

            $table->index('strategic_plan_id', 'ait_plan_idx');

            $table->foreign('strategic_plan_id', 'ait_plan_fk')
                ->references('id')->on('strategic_plans')->cascadeOnDelete();
            $table->foreign('assigned_to', 'ait_assignee_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_items');
    }
};
