<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rapat evaluasi GPER — notulen & keputusan.
     */
    public function up(): void
    {
        Schema::create('review_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('session_date');
            $table->unsignedBigInteger('chaired_by')->nullable(); // pemimpin rapat (users)
            $table->text('minutes')->nullable();             // notulen
            $table->text('decisions')->nullable();           // keputusan rapat
            $table->string('status', 20)->default('draft');   // draft | finalized
            $table->timestamps();

            $table->foreign('chaired_by', 'rvs_chair_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_sessions');
    }
};
