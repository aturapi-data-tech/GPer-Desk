<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul Survei:
     * - surveys           : judul + status (draft/active/closed). Hanya 1 'active' tampil publik.
     * - survey_questions  : pertanyaan per survei (type: rating 1-5 / text).
     * - survey_responses  : satu pengisian oleh 1 responden (publik, nama opsional).
     * - survey_answers    : jawaban per pertanyaan.
     * Data semua survei (A, B, …) + responden tetap tersimpan.
     */
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft'); // draft | active | closed
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->foreign('created_by', 'srv_creator_fk')->references('id')->on('users')->nullOnDelete();
            $table->index('status', 'srv_status_idx');
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('question', 500);
            $table->string('type', 20)->default('rating'); // rating | text
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->foreign('survey_id', 'sq_survey_fk')->references('id')->on('surveys')->cascadeOnDelete();
            $table->index('survey_id', 'sq_survey_idx');
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('respondent_name', 150)->nullable();
            $table->timestamps();
            $table->foreign('survey_id', 'sr_survey_fk')->references('id')->on('surveys')->cascadeOnDelete();
            $table->index('survey_id', 'sr_survey_idx');
        });

        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('response_id');
            $table->unsignedBigInteger('question_id');
            $table->text('value')->nullable(); // rating -> angka (string) · text -> isi
            $table->timestamps();
            $table->foreign('response_id', 'sa_response_fk')->references('id')->on('survey_responses')->cascadeOnDelete();
            $table->foreign('question_id', 'sa_question_fk')->references('id')->on('survey_questions')->cascadeOnDelete();
            $table->index('question_id', 'sa_question_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }
};
