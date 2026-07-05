<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keterangan bebas lokasi/ruangan (opsional) — menggantikan pemilihan
     * "Departemen Pelapor". Pelapor menjelaskan ruangan/detail kejadian.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('location_detail', 500)->nullable()->after('location_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('location_detail');
        });
    }
};
