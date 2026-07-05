<?php

use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component {
    public function mount(): void
    {
        // Halaman menampilkan token rahasia → khusus admin.
        abort_unless(auth()->user()?->hasAnyRole(['admin']), 403);
    }

    /** Base URL endpoint API (ikut APP_URL, fallback ke host request). */
    #[Computed]
    public function baseUrl(): string
    {
        $asal = config('app.url') ?: request()->getSchemeAndHttpHost();
        return rtrim($asal, '/') . '/api/incident-reports';
    }

    /** Token statis dari config (services.incident_bridge.token ← INCIDENT_API_TOKEN). */
    #[Computed]
    public function apiToken(): string
    {
        return (string) config('services.incident_bridge.token');
    }
};
?>

@php
    // Data tabel dokumentasi (mapping display — key = nama field body, bukan variabel).
    $daftarField = [
        ['field' => 'category', 'wajib' => true, 'ket' => 'salah satu: etika · k3 · kecurangan · layanan · lainnya'],
        ['field' => 'title', 'wajib' => true, 'ket' => 'judul / ringkasan, maks 255'],
        ['field' => 'description', 'wajib' => true, 'ket' => 'uraian kejadian, maks 5000'],
        ['field' => 'location', 'wajib' => false, 'ket' => 'lokasi kejadian, maks 255'],
        ['field' => 'incident_date', 'wajib' => false, 'ket' => 'tanggal kejadian (YYYY-MM-DD)'],
        ['field' => 'is_anonymous', 'wajib' => false, 'ket' => 'boolean; bila true, reporter_* diabaikan'],
        ['field' => 'reporter_name', 'wajib' => false, 'ket' => 'nama pelapor, maks 150'],
        ['field' => 'reporter_contact', 'wajib' => false, 'ket' => 'kontak pelapor, maks 150'],
    ];

    // kelasBadge = string kelas Tailwind penuh (statik) agar pasti digenerate — jangan dirakit dinamis.
    $kelasPost  = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
    $kelasGet   = 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300';
    $kelasPatch = 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300';
    $daftarEndpoint = [
        ['metode' => 'POST', 'kelasBadge' => $kelasPost, 'path' => '/api/incident-reports', 'judul' => 'Buat laporan', 'ket' => 'Sistem lain mengirim laporan baru (tersimpan dengan source=api).'],
        ['metode' => 'GET', 'kelasBadge' => $kelasGet, 'path' => '/api/incident-reports', 'judul' => 'Daftar laporan', 'ket' => 'Query opsional: status, category, per_page (default 25, maks 100).'],
        ['metode' => 'GET', 'kelasBadge' => $kelasGet, 'path' => '/api/incident-reports/{report_no}', 'judul' => 'Detail laporan', 'ket' => 'Ambil satu laporan berdasarkan nomor (WBR-YYYY-0001). 404 bila tidak ada.'],
        ['metode' => 'PATCH', 'kelasBadge' => $kelasPatch, 'path' => '/api/incident-reports/{report_no}/status', 'judul' => 'Ubah status', 'ket' => 'Body: { "status": "diproses" } — enum: baru · diproses · selesai · ditutup.'],
    ];
@endphp

<div>
    <x-page-title title="Dokumentasi API — Bridging Pengaduan" subtitle="Panduan integrasi sistem lain: token, endpoint & contoh cURL" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex-1 min-h-0 px-6 pt-2 pb-6 overflow-y-auto">
            <div class="max-w-4xl mx-auto mt-2 space-y-5">

                {{-- INTRO --}}
                <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <p class="text-sm leading-relaxed text-body dark:text-gray-300">
                        REST API untuk <strong>bridging</strong> modul <strong>Pengaduan &amp; Aspirasi Karyawan</strong> dengan sistem lain.
                        Sistem eksternal dapat <strong>mengirim</strong> laporan ke GPER-Desk serta <strong>membaca</strong> &amp; <strong>memperbarui status</strong> laporan.
                        Semua request memakai JSON — sertakan header <code class="px-1 rounded bg-surface-soft dark:bg-gray-900">Accept: application/json</code>.
                    </p>
                    <p class="mt-3 text-xs text-muted dark:text-gray-400">
                        Dokumen teknis versi berkas juga tersedia di repo: <code class="px-1 rounded bg-surface-soft dark:bg-gray-900">docs/api-incident-reports.md</code>.
                    </p>
                </div>

                {{-- KREDENSIAL --}}
                <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-muted dark:text-gray-400">Kredensial &amp; Base URL</h2>

                    {{-- Base URL --}}
                    <div class="mt-3">
                        <p class="text-xs font-semibold text-muted dark:text-gray-400">Base URL</p>
                        <div x-data="{ tersalin: false }" class="flex items-center gap-2 mt-1">
                            <code x-ref="baseUrl" class="flex-1 px-3 py-2 font-mono text-sm break-all rounded-lg bg-surface-soft text-ink dark:bg-gray-900 dark:text-gray-100">{{ $this->baseUrl }}</code>
                            <button type="button"
                                @click="navigator.clipboard.writeText($refs.baseUrl.innerText); tersalin = true; setTimeout(() => tersalin = false, 1500)"
                                class="px-3 py-2 text-xs font-semibold rounded-lg shrink-0 bg-brand-green/10 text-brand-green hover:bg-brand-green/20 dark:bg-brand-lime/15 dark:text-brand-lime">
                                <span x-show="!tersalin">Salin</span><span x-show="tersalin" x-cloak>Tersalin ✓</span>
                            </button>
                        </div>
                    </div>

                    {{-- Token --}}
                    <div class="mt-4">
                        <p class="text-xs font-semibold text-muted dark:text-gray-400">Token (header <code>Authorization: Bearer &lt;TOKEN&gt;</code> atau <code>X-Api-Token</code>)</p>
                        @if ($this->apiToken === '')
                            <div class="p-3 mt-1 text-sm border rounded-lg bg-warning-tint border-warning/30 text-warning-deep dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                                Belum ada token. Set <code>INCIDENT_API_TOKEN</code> di <code>.env</code> (mis. <code>openssl rand -hex 24</code>), lalu <code>php artisan config:clear</code>.
                            </div>
                        @else
                            <div x-data="{ tampil: false, tersalin: false }" class="flex items-center gap-2 mt-1">
                                <code class="flex-1 px-3 py-2 font-mono text-sm break-all rounded-lg bg-surface-soft text-ink dark:bg-gray-900 dark:text-gray-100">
                                    <span x-show="tampil" x-cloak>{{ $this->apiToken }}</span>
                                    <span x-show="!tampil">••••••••••••••••••••••••••••••••</span>
                                </code>
                                <button type="button" @click="tampil = !tampil"
                                    class="px-3 py-2 text-xs font-semibold rounded-lg shrink-0 bg-surface-soft text-muted hover:text-ink dark:bg-gray-900 dark:text-gray-300">
                                    <span x-show="!tampil">Lihat</span><span x-show="tampil" x-cloak>Sembunyikan</span>
                                </button>
                                <button type="button"
                                    @click="navigator.clipboard.writeText(@js($this->apiToken)); tersalin = true; setTimeout(() => tersalin = false, 1500)"
                                    class="px-3 py-2 text-xs font-semibold rounded-lg shrink-0 bg-brand-green/10 text-brand-green hover:bg-brand-green/20 dark:bg-brand-lime/15 dark:text-brand-lime">
                                    <span x-show="!tersalin">Salin</span><span x-show="tersalin" x-cloak>Tersalin ✓</span>
                                </button>
                            </div>
                            <p class="mt-1.5 text-[11px] text-muted-soft">Token rahasia — hanya admin yang bisa membuka halaman ini. Jangan bagikan di kanal publik.</p>
                        @endif
                    </div>
                </div>

                {{-- TES KONEKSI --}}
                <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-muted dark:text-gray-400">Tes Koneksi</h2>
                    <p class="mt-1 text-xs text-muted dark:text-gray-400">
                        Memanggil <code>GET {{ $this->baseUrl }}?per_page=1</code> langsung dari browser Anda memakai token di atas — meniru sistem eksternal &amp; menguji route + middleware + token sekaligus.
                    </p>

                    @if ($this->apiToken === '')
                        <div class="p-3 mt-3 text-sm border rounded-lg bg-warning-tint border-warning/30 text-warning-deep dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                            Token belum diset — atur <code>INCIDENT_API_TOKEN</code> dulu sebelum menguji koneksi.
                        </div>
                    @else
                        <div x-data="{
                                status: 'idle', kode: null, pesan: '', total: null,
                                async tesKoneksi() {
                                    this.status = 'loading'; this.kode = null; this.pesan = ''; this.total = null;
                                    try {
                                        const respon = await fetch(@js($this->baseUrl) + '?per_page=1', {
                                            headers: { 'Authorization': 'Bearer ' + @js($this->apiToken), 'Accept': 'application/json' }
                                        });
                                        this.kode = respon.status;
                                        let data = {};
                                        try { data = await respon.json(); } catch (e) {}
                                        if (respon.ok && data.success) {
                                            this.status = 'ok';
                                            this.total = (data.meta && data.meta.total != null) ? data.meta.total : '—';
                                        } else {
                                            this.status = 'fail';
                                            this.pesan = data.message || 'Permintaan ditolak.';
                                        }
                                    } catch (e) {
                                        this.status = 'error';
                                        this.pesan = 'Tidak bisa menghubungi endpoint: ' + e.message;
                                    }
                                }
                            }" class="mt-3">
                            <button type="button" @click="tesKoneksi()" :disabled="status === 'loading'"
                                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg bg-brand-green hover:bg-brand-green/90 disabled:opacity-60 dark:bg-brand-lime dark:text-gray-900">
                                <span x-show="status !== 'loading'">Tes koneksi</span>
                                <span x-show="status === 'loading'" x-cloak>Menguji…</span>
                            </button>

                            <div x-show="status === 'ok'" x-cloak class="p-3 mt-3 text-sm border rounded-lg bg-success-tint border-success/30 text-success-deep dark:bg-green-900/20 dark:border-green-700 dark:text-green-300">
                                ✓ Koneksi berhasil (HTTP <span x-text="kode"></span>). Token valid — total laporan terbaca: <strong x-text="total"></strong>.
                            </div>
                            <div x-show="status === 'fail'" x-cloak class="p-3 mt-3 text-sm border rounded-lg bg-red-50 border-red-200 text-red-700 dark:bg-red-900/20 dark:border-red-700 dark:text-red-300">
                                ✗ Ditolak (HTTP <span x-text="kode"></span>). <span x-text="pesan"></span> — periksa token &amp; base URL.
                            </div>
                            <div x-show="status === 'error'" x-cloak class="p-3 mt-3 text-sm border rounded-lg bg-warning-tint border-warning/30 text-warning-deep dark:bg-amber-900/20 dark:border-amber-700 dark:text-amber-300">
                                ⚠ <span x-text="pesan"></span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- ENDPOINT --}}
                <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-muted dark:text-gray-400">Endpoint</h2>
                    <div class="mt-3 space-y-2">
                        @foreach ($daftarEndpoint as $endpoint)
                            <div class="flex items-start gap-3 p-3 border rounded-xl border-hairline bg-surface-soft/50 dark:border-gray-700 dark:bg-gray-900/40">
                                <span class="mt-0.5 px-2 py-1 text-[11px] font-bold rounded-md shrink-0 w-16 text-center {{ $endpoint['kelasBadge'] }}">{{ $endpoint['metode'] }}</span>
                                <div class="min-w-0">
                                    <p class="font-mono text-sm break-all text-ink dark:text-gray-100">{{ $endpoint['path'] }}</p>
                                    <p class="text-xs font-semibold text-brand-green dark:text-brand-lime">{{ $endpoint['judul'] }}</p>
                                    <p class="mt-0.5 text-xs text-muted dark:text-gray-400">{{ $endpoint['ket'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- BODY POST --}}
                <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-muted dark:text-gray-400">Body — POST buat laporan</h2>
                    <div class="mt-3 overflow-x-auto">
                        <table class="ds-table">
                            <thead><tr><th>Field</th><th class="ds-c">Wajib</th><th>Keterangan</th></tr></thead>
                            <tbody>
                                @foreach ($daftarField as $baris)
                                    <tr>
                                        <td class="font-mono text-sm ds-td-strong">{{ $baris['field'] }}</td>
                                        <td class="ds-c">
                                            @if ($baris['wajib'])
                                                <x-badge variant="success">wajib</x-badge>
                                            @else
                                                <x-badge variant="gray">opsional</x-badge>
                                            @endif
                                        </td>
                                        <td class="text-sm text-body dark:text-gray-300">{{ $baris['ket'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-muted dark:text-gray-400">
                        Respons sukses <code>201</code>: <code class="px-1 rounded bg-surface-soft dark:bg-gray-900">{ "success": true, "report_no": "WBR-2026-0001", "id": 1 }</code>.
                        Error: <code>401</code> token salah · <code>404</code> tidak ditemukan · <code>422</code> validasi gagal.
                    </p>
                </div>

                {{-- CONTOH cURL --}}
                <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-muted dark:text-gray-400">Contoh cURL</h2>
                        <span class="text-[11px] text-muted-soft">Ganti <code>&lt;TOKEN&gt;</code> dengan token di atas.</span>
                    </div>
                    @php
                        $contohCurl = <<<BASH
                        TOKEN="<TOKEN>"
                        BASE="{$this->baseUrl}"

                        # Buat laporan
                        curl -X POST "\$BASE" \\
                          -H "Authorization: Bearer \$TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \\
                          -d '{"category":"etika","title":"Ketidaknyamanan di unit X","description":"Kronologi...","is_anonymous":true}'

                        # Daftar (filter status baru)
                        curl "\$BASE?status=baru&per_page=10" -H "Authorization: Bearer \$TOKEN" -H "Accept: application/json"

                        # Detail
                        curl "\$BASE/WBR-2026-0001" -H "Authorization: Bearer \$TOKEN" -H "Accept: application/json"

                        # Ubah status
                        curl -X PATCH "\$BASE/WBR-2026-0001/status" \\
                          -H "Authorization: Bearer \$TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \\
                          -d '{"status":"diproses"}'
                        BASH;
                    @endphp
                    <div x-data="{ tersalin: false }" class="relative mt-3">
                        <pre class="p-4 overflow-x-auto text-xs leading-relaxed text-gray-100 bg-gray-900 rounded-xl dark:bg-black/60"><code x-ref="curl">{{ $contohCurl }}</code></pre>
                        <button type="button"
                            @click="navigator.clipboard.writeText($refs.curl.innerText); tersalin = true; setTimeout(() => tersalin = false, 1500)"
                            class="absolute px-2.5 py-1 text-xs font-semibold text-gray-200 rounded-md top-2 right-2 bg-white/10 hover:bg-white/20">
                            <span x-show="!tersalin">Salin</span><span x-show="tersalin" x-cloak>Tersalin ✓</span>
                        </button>
                    </div>
                </div>

                {{-- CATATAN TEKNIS --}}
                <div class="p-5 border shadow-sm rounded-2xl border-hairline bg-surface-soft/60 dark:border-gray-700 dark:bg-gray-900/40">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-muted dark:text-gray-400">Komponen di aplikasi kita</h2>
                    <ul class="mt-2 space-y-1 text-xs text-muted dark:text-gray-400 list-disc list-inside">
                        <li>Token: <code>.env</code> → <code>INCIDENT_API_TOKEN</code>, dibaca <code>config/services.php</code> (<code>services.incident_bridge.token</code>).</li>
                        <li>Middleware: <code>App\Http\Middleware\VerifyApiToken</code> (alias <code>api.token</code>) — tolak <code>401</code> bila token salah (<code>hash_equals</code>).</li>
                        <li>Route: <code>routes/api.php</code> (prefix <code>/api</code>). Logika: <code>App\Services\IncidentReportService</code> (dipakai web &amp; API).</li>
                        <li>Data masuk (web/API) tampil bersama di menu <strong>Rekap Pengaduan</strong> — dibedakan kolom <em>Sumber</em>.</li>
                    </ul>
                    <div class="mt-4">
                        <a href="{{ route('insiden.rekap') }}" wire:navigate>
                            <x-secondary-button type="button">← Ke Rekap Pengaduan</x-secondary-button>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
