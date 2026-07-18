<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logika Pelaporan Insiden Keselamatan Pasien (IKP) — dipakai bersama oleh web
 * (Livewire) dan REST API bridging (mis. dari EMR/SIMRS) supaya konsisten.
 *
 * Acuan: Permenkes 11/2017 tentang Keselamatan Pasien & standar PMKP.
 */
class PatientSafetyIncidentService
{
    /** Jenis insiden keselamatan pasien. */
    public const TYPES = ['kpc', 'knc', 'ktc', 'ktd', 'sentinel'];

    /** Grading matriks risiko (band warna) — diisi tim mutu setelah investigasi. */
    public const GRADINGS = ['biru', 'hijau', 'kuning', 'merah'];

    /** Alur status penanganan laporan. */
    public const STATUSES = ['baru', 'investigasi', 'selesai', 'ditutup'];

    /** Nomor laporan unik: IKP-YYYY-0001. */
    public static function generateNo(): string
    {
        $tahun  = date('Y');
        $urutan = DB::table('patient_safety_incidents')->where('report_no', 'like', "IKP-{$tahun}-%")->count() + 1;
        do {
            $nomor = 'IKP-' . $tahun . '-' . str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
            $urutan++;
        } while (DB::table('patient_safety_incidents')->where('report_no', $nomor)->exists());

        return $nomor;
    }

    /**
     * Simpan laporan IKP. Bila anonim, identitas pelapor tidak disimpan.
     * Grading tidak diisi saat lapor (default null) — ditetapkan tim mutu.
     * @return array{id:int, report_no:string}
     */
    public static function create(array $input, string $source = 'web'): array
    {
        $now    = Carbon::now();
        $anonim = ! empty($input['is_anonymous']);
        $nomor  = self::generateNo();

        // Hindari insertGetId (oci8 error di HTTP) — insert lalu ambil id via report_no unik.
        DB::table('patient_safety_incidents')->insert([
            'report_no'        => $nomor,
            'incident_type'    => $input['incident_type'],
            'title'            => trim($input['title']),
            'chronology'       => trim($input['chronology']),
            'patient_impact'   => !empty($input['patient_impact'])   ? trim($input['patient_impact'])   : null,
            'immediate_action' => !empty($input['immediate_action']) ? trim($input['immediate_action']) : null,
            'grading'          => !empty($input['grading']) && in_array($input['grading'], self::GRADINGS, true) ? $input['grading'] : null,
            'location'         => !empty($input['location'])     ? trim($input['location'])     : null,
            'incident_date'    => !empty($input['incident_date']) ? Carbon::parse($input['incident_date']) : null,
            'incident_time'    => !empty($input['incident_time']) ? trim($input['incident_time']) : null,
            // patient_ref = identitas pasien (No. RM/inisial), beda dari pelapor → tetap disimpan walau pelapor anonim.
            'patient_ref'      => !empty($input['patient_ref']) ? trim($input['patient_ref']) : null,
            'reporter_name'    => $anonim ? null : (!empty($input['reporter_name'])    ? trim($input['reporter_name'])    : null),
            'reporter_role'    => $anonim ? null : (!empty($input['reporter_role'])    ? trim($input['reporter_role'])    : null),
            'reporter_contact' => $anonim ? null : (!empty($input['reporter_contact']) ? trim($input['reporter_contact']) : null),
            'is_anonymous'     => $anonim ? 1 : 0,
            'status'           => 'baru',
            'source'           => $source,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        $id = (int) DB::table('patient_safety_incidents')->where('report_no', $nomor)->value('id');

        return ['id' => $id, 'report_no' => $nomor];
    }
}
