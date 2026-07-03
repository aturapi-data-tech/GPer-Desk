<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Master lokasi / ruangan tempat gangguan terjadi. */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->nullable();
            $table->string('building', 100)->nullable();
            $table->string('floor', 30)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name', 'loc_name_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
