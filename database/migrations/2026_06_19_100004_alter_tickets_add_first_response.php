<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catat siapa & kapan respon pertama diberikan (selain log per-komentar).
     * "Yang mengerjakan" tetap di tickets.technician_id (auto-assign saat
     * teknisi merespon pertama kali).
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('first_responded_by')->nullable()->after('technician_id');
            $table->timestamp('first_responded_at')->nullable()->after('first_responded_by');

            $table->foreign('first_responded_by', 'tic_firstresp_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign('tic_firstresp_fk');
            $table->dropColumn(['first_responded_by', 'first_responded_at']);
        });
    }
};
