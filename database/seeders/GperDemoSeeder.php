<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Contoh data demo agar tiap fitur tampil terisi:
 * - Survei: 1 DITUTUP + 1 AKTIF (masing-masing punya 3 tipe pertanyaan: rating/choice/teks + banyak responden).
 * - Suara Pasien: tambahan ulasan.
 * Idempotent: demo lama dihapus dulu (berdasarkan judul/nama yang dikenal).
 */
class GperDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now     = Carbon::now();
        $adminId = DB::table('users')->where('email', 'admin@gper.com')->value('id');

        // ── SURVEI ────────────────────────────────────────────────────
        $demoTitles = [
            'Survei Uji Kepuasan',
            'Survei Kepuasan Rawat Jalan (Juni 2026)',
            'Survei Kepuasan Layanan (Juli 2026)',
        ];
        $oldIds = DB::table('surveys')->whereIn('title', $demoTitles)->pluck('id');
        if ($oldIds->count()) {
            DB::table('surveys')->whereIn('id', $oldIds)->delete(); // cascade ke questions/responses/answers
        }
        // Pastikan tidak ada 'active' lain yang tersisa saat kita buat yang aktif.
        DB::table('surveys')->where('status', 'active')->update(['status' => 'closed', 'closed_at' => $now, 'updated_at' => $now]);

        $ratings = [5, 4, 5, 3, 4, 5, 4, 5, 4, 5];
        $choices = ['Sangat Ramah', 'Ramah', 'Ramah', 'Biasa', 'Sangat Ramah', 'Ramah', 'Sangat Ramah', 'Ramah', 'Biasa', 'Sangat Ramah'];

        $makeSurvey = function (string $title, string $status, array $texts) use ($now, $adminId, $ratings, $choices) {
            $sid = DB::table('surveys')->insertGetId([
                'title'       => $title,
                'description' => 'Bantu kami meningkatkan mutu pelayanan — isi survei singkat berikut.',
                'status'      => $status,
                'opened_at'   => $status !== 'draft' ? $now : null,
                'closed_at'   => $status === 'closed' ? $now : null,
                'created_by'  => $adminId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);

            $q1 = DB::table('survey_questions')->insertGetId(['survey_id' => $sid, 'question' => 'Seberapa puas Anda dengan pelayanan secara keseluruhan?', 'type' => 'rating', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $q2 = DB::table('survey_questions')->insertGetId(['survey_id' => $sid, 'question' => 'Bagaimana keramahan petugas?', 'type' => 'choice', 'options' => "Sangat Ramah\nRamah\nBiasa\nKurang Ramah", 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now]);
            $q3 = DB::table('survey_questions')->insertGetId(['survey_id' => $sid, 'question' => 'Saran / masukan untuk kami?', 'type' => 'text', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now]);

            foreach ($texts as $i => $txt) {
                $rid = DB::table('survey_responses')->insertGetId(['survey_id' => $sid, 'respondent_name' => null, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('survey_answers')->insert(['response_id' => $rid, 'question_id' => $q1, 'value' => (string) $ratings[$i % count($ratings)], 'created_at' => $now, 'updated_at' => $now]);
                DB::table('survey_answers')->insert(['response_id' => $rid, 'question_id' => $q2, 'value' => $choices[$i % count($choices)], 'created_at' => $now, 'updated_at' => $now]);
                if ($txt !== null) {
                    DB::table('survey_answers')->insert(['response_id' => $rid, 'question_id' => $q3, 'value' => $txt, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
            return $sid;
        };

        // Survei A — sudah DITUTUP (arsip, data tetap ada).
        $makeSurvey('Survei Kepuasan Rawat Jalan (Juni 2026)', 'closed', [
            'Pelayanan cepat, terima kasih.', 'Ruang tunggu nyaman.', 'Dokter komunikatif.',
            'Antrean agak lama.', null, 'Perawat ramah sekali.', 'Sudah baik.', 'Toilet perlu diperbaiki.',
        ]);

        // Survei B — sedang AKTIF (tampil ke publik).
        $makeSurvey('Survei Kepuasan Layanan (Juli 2026)', 'active', [
            'Overall memuaskan.', 'Petugas informatif.', null, 'Parkir agak sempit.',
            'Bagus dan rapi.', 'Terima kasih pelayanannya.',
        ]);

        // ── SUARA PASIEN (tambahan ulasan) ────────────────────────────
        $reviews = [
            ['Pak Hasan',   5, 'Dokternya teliti dan sabar menjelaskan.'],
            ['Bu Dewi',     4, 'Cukup baik, hanya parkir yang penuh.'],
            ['Agus P.',     5, 'Perawat IGD sigap, terima kasih!'],
            ['Siti R.',     3, 'Antrean pendaftaran lama.'],
            ['Pak Yusuf',   5, 'Fasilitas bersih dan nyaman.'],
            ['Nina',        4, 'Pelayanan farmasi ramah.'],
        ];
        // comment = CLOB → jangan dipakai di WHERE. Idempoten: hapus demo by nama lalu insert.
        $demoNames = array_column($reviews, 0);
        DB::table('patient_reviews')->whereIn('reviewer_name', $demoNames)->delete();
        foreach ($reviews as $r) {
            DB::table('patient_reviews')->insert([
                'reviewer_name' => $r[0], 'rating' => $r[1], 'comment' => $r[2],
                'is_visible' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->command?->info('Demo: 2 survei (1 ditutup + 1 aktif) + ulasan pasien dibuat.');
    }
}
