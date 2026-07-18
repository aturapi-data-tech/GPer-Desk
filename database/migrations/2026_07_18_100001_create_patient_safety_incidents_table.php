<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pelaporan Insiden Keselamatan Pasien (IKP) — mengacu Permenkes 11/2017 &
     * standar PMKP. Jenis insiden: KPC | KNC | KTC | KTD | Sentinel. Grading
     * matriks risiko (biru/hijau/kuning/merah) diisi tim mutu setelah laporan
     * masuk. Bisa anonim. `source` = web | api (bridging sistem lain / EMR).
     */
    public function up(): void
    {
        Schema::create('patient_safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('report_no', 30)->unique('psi_no_uq');
            $table->string('incident_type', 20);            // kpc | knc | ktc | ktd | sentinel
            $table->string('title', 255);
            $table->text('chronology');                     // kronologi kejadian
            $table->text('patient_impact')->nullable();     // dampak / akibat pada pasien
            $table->text('immediate_action')->nullable();   // tindakan segera setelah kejadian
            $table->string('grading', 10)->nullable();      // biru | hijau | kuning | merah (diisi tim mutu)
            $table->string('location', 255)->nullable();    // unit / lokasi kejadian
            $table->date('incident_date')->nullable();
            $table->string('incident_time', 5)->nullable(); // jam kejadian, format HH:MM
            $table->string('patient_ref', 100)->nullable(); // No. RM / inisial pasien (opsional, privasi)
            $table->string('reporter_name', 150)->nullable();
            $table->string('reporter_role', 100)->nullable(); // profesi / jabatan pelapor
            $table->string('reporter_contact', 150)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->string('status', 30)->default('baru');  // baru | investigasi | selesai | ditutup
            $table->string('source', 20)->default('web');   // web | api
            $table->timestamps();
            $table->index('status', 'psi_status_idx');
            $table->index('incident_type', 'psi_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_safety_incidents');
    }
};
