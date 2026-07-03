<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Master status tiket (Open, In Progress, Resolved, Closed, dll) + warna & urutan alur. */
    public function up(): void
    {
        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 30)->nullable();
            $table->string('color', 20)->default('#6b7280'); // warna badge (hex)
            $table->unsignedInteger('sort_order')->default(0); // urutan alur status
            $table->boolean('is_closed')->default(false);     // status final (tiket dianggap selesai)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name', 'tst_name_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_statuses');
    }
};
