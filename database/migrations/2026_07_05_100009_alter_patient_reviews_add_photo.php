<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Foto opsional pada ulasan Suara Pasien. */
    public function up(): void
    {
        Schema::table('patient_reviews', function (Blueprint $table) {
            $table->string('photo_path', 500)->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('patient_reviews', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
