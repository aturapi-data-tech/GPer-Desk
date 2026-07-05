<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tandai departemen yang merupakan SEKTOR PENANGANAN (IT, CSSD, IPS).
     * Departemen non-teknis (HRD, Keuangan, dll) tetap is_sector=0 dan tidak
     * muncul di dropdown "Sektor" saat pelapor membuat tiket.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('is_sector')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('is_sector');
        });
    }
};
