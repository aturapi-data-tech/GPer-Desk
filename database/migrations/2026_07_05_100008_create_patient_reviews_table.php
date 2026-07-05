<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Suara Pasien" — ulasan/penilaian publik ala Google Review.
     * Bintang (1-5) + ulasan + nama. is_visible untuk moderasi admin.
     */
    public function up(): void
    {
        Schema::create('patient_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reviewer_name', 150);
            $table->unsignedTinyInteger('rating');       // 1..5
            $table->text('comment')->nullable();
            $table->boolean('is_visible')->default(true); // admin bisa sembunyikan
            $table->timestamps();

            $table->index('is_visible', 'prev_visible_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_reviews');
    }
};
