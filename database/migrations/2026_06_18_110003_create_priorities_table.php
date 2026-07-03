<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Master prioritas tiket (low/medium/high/critical) + SLA & warna badge. */
    public function up(): void
    {
        Schema::create('priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->unsignedInteger('level')->default(0);    // urutan / bobot (makin tinggi makin penting)
            $table->string('color', 20)->default('#6b7280'); // warna badge (hex)
            $table->unsignedInteger('sla_hours')->nullable(); // target penyelesaian (jam)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name', 'prio_name_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priorities');
    }
};
