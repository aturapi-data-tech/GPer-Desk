<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaduan & aspirasi karyawan (whistleblowing): etika & perilaku,
     * keselamatan kerja, kecurangan, layanan, dll. Bisa anonim. `source` =
     * web | api (bridging dari sistem lain). Diakses juga lewat REST API.
     */
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_no', 30)->unique('increp_no_uq');
            $table->string('category', 50);          // etika | k3 | kecurangan | layanan | lainnya
            $table->string('title', 255);
            $table->text('description');
            $table->string('location', 255)->nullable();
            $table->date('incident_date')->nullable();
            $table->string('reporter_name', 150)->nullable();
            $table->string('reporter_contact', 150)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('status', 30)->default('baru'); // baru | diproses | selesai | ditutup
            $table->string('source', 20)->default('web');  // web | api
            $table->timestamps();
            $table->index('status', 'increp_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
    }
};
