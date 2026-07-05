<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Opsi jawaban untuk pertanyaan tipe 'choice' (radio) — 1 opsi per baris. */
    public function up(): void
    {
        Schema::table('survey_questions', function (Blueprint $table) {
            $table->text('options')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('survey_questions', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
