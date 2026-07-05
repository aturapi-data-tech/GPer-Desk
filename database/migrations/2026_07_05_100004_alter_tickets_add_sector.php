<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sektor tujuan penanganan tiket (IT/CSSD/IPS) — dipilih pelapor saat lapor,
     * seperti memilih jasa kirim. Menentukan tim teknisi yang menangani.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('sector_id')->nullable()->after('department_id');
            $table->index('sector_id', 'tic_sector_idx');
            $table->foreign('sector_id', 'tic_sector_fk')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign('tic_sector_fk');
            $table->dropIndex('tic_sector_idx');
            $table->dropColumn('sector_id');
        });
    }
};
