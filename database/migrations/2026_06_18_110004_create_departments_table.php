<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Master departemen / unit kerja pelapor tiket. */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->nullable();
            $table->string('head_name', 100)->nullable(); // nama kepala unit
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name', 'dept_name_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
