<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Master kategori tiket (Hardware, Network, Software, AC, dll). */
    public function up(): void
    {
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name', 'tcat_name_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_categories');
    }
};
