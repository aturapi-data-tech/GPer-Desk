<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nama pelapor untuk tiket dari form publik/anonim (tanpa login).
     * reported_by tetap null saat anonim; nama disimpan di sini.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('reporter_name', 150)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('reporter_name');
        });
    }
};
