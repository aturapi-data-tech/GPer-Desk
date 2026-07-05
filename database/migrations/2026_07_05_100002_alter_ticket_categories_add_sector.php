<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori tiket ditangani oleh SATU sektor (departemen is_sector=1).
     * Dipakai untuk memfilter pilihan kategori setelah pelapor memilih sektor.
     */
    public function up(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('sector_id')->nullable()->after('code');
            $table->foreign('sector_id', 'tcat_sector_fk')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table) {
            $table->dropForeign('tcat_sector_fk');
            $table->dropColumn('sector_id');
        });
    }
};
