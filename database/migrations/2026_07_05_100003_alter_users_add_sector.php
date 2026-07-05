<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sektor tempat teknisi bertugas (departemen is_sector=1). Menentukan tiket
     * sektor mana yang tampil untuknya di "Kelola Tiket". Null utk non-teknisi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('sector_id')->nullable()->after('name');
            $table->foreign('sector_id', 'usr_sector_fk')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('usr_sector_fk');
            $table->dropColumn('sector_id');
        });
    }
};
