<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logika pelaporan insiden (whistleblowing) — dipakai bersama oleh web (Livewire)
 * dan REST API bridging supaya konsisten.
 */
class IncidentReportService
{
    public const CATEGORIES = ['etika', 'k3', 'kecurangan', 'layanan', 'lainnya'];
    public const STATUSES   = ['baru', 'diproses', 'selesai', 'ditutup'];

    /** Nomor laporan unik: WBR-YYYY-0001. */
    public static function generateNo(): string
    {
        $tahun  = date('Y');
        $urutan = DB::table('incident_reports')->where('report_no', 'like', "WBR-{$tahun}-%")->count() + 1;
        do {
            $nomor = 'WBR-' . $tahun . '-' . str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
            $urutan++;
        } while (DB::table('incident_reports')->where('report_no', $nomor)->exists());

        return $nomor;
    }

    /**
     * Simpan laporan. Bila anonim, identitas pelapor tidak disimpan.
     * @return array{id:int, report_no:string}
     */
    public static function create(array $input, string $source = 'web'): array
    {
        $now      = Carbon::now();
        $anonim   = ! empty($input['is_anonymous']);
        $nomor    = self::generateNo();

        // Hindari insertGetId (oci8 error di HTTP) — insert lalu ambil id via report_no unik.
        DB::table('incident_reports')->insert([
            'report_no'        => $nomor,
            'category'         => $input['category'],
            'title'            => trim($input['title']),
            'description'      => trim($input['description']),
            'location'         => !empty($input['location']) ? trim($input['location']) : null,
            'incident_date'    => !empty($input['incident_date']) ? Carbon::parse($input['incident_date']) : null,
            'reporter_name'    => $anonim ? null : (!empty($input['reporter_name']) ? trim($input['reporter_name']) : null),
            'reporter_contact' => $anonim ? null : (!empty($input['reporter_contact']) ? trim($input['reporter_contact']) : null),
            'is_anonymous'     => $anonim ? 1 : 0,
            'status'           => 'baru',
            'source'           => $source,
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);
        $id = (int) DB::table('incident_reports')->where('report_no', $nomor)->value('id');

        return ['id' => $id, 'report_no' => $nomor];
    }
}
