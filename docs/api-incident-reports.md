# API Pelaporan Insiden (Whistleblowing) — Bridging

REST API untuk integrasi/bridging modul **Pengaduan & Aspirasi Karyawan** (etika & perilaku, keselamatan kerja, kecurangan, layanan) dengan sistem lain. Sistem eksternal dapat **mengirim** laporan ke GPER-Desk dan **membaca / memperbarui status** laporan.

- Base URL: `{APP_URL}/api/incident-reports`
- Format: JSON (kirim header `Accept: application/json`)
- Auth: token statis via header **`Authorization: Bearer <TOKEN>`** (atau `X-Api-Token: <TOKEN>`)

---

## 1. Setup di program kita

1. **Token** disimpan di `.env`:
   ```env
   INCIDENT_API_TOKEN=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
   ```
   Ganti nilainya dengan token rahasia (mis. hasil `openssl rand -hex 24`). Setelah mengubah `.env`, jalankan `php artisan config:clear`.

2. **Config**: `config/services.php` → `services.incident_bridge.token` membaca env di atas.

3. **Middleware**: `App\Http\Middleware\VerifyApiToken` (alias `api.token`, didaftarkan di `bootstrap/app.php`). Menolak `401` bila token tidak cocok (`hash_equals`).

4. **Route**: `routes/api.php` (didaftarkan di `bootstrap/app.php` → `withRouting(api: ...)`). Semua endpoint diprefix otomatis `/api`.

5. **Tabel**: `incident_reports` (migration `2026_07_05_100012`). Kolom: `report_no` (unik, `WBR-YYYY-0001`), `category`, `title`, `description`, `location`, `incident_date`, `reporter_name`, `reporter_contact`, `is_anonymous`, `status`, `source` (`web`|`api`), timestamps.

6. Logika dipusatkan di `App\Services\IncidentReportService` (dipakai bersama web & API).

**Nilai enum:**
- `category`: `etika` · `k3` · `kecurangan` · `layanan` · `lainnya`
- `status`: `baru` · `diproses` · `selesai` · `ditutup`

---

## 2. Endpoint

### 2.1 Buat laporan — `POST /api/incident-reports`
Sistem lain mengirim laporan insiden.

**Body (JSON):**
| Field | Wajib | Ket |
|---|---|---|
| `category` | ✅ | salah satu enum category |
| `title` | ✅ | max 255 |
| `description` | ✅ | max 5000 |
| `location` | — | max 255 |
| `incident_date` | — | tanggal (`YYYY-MM-DD`) |
| `is_anonymous` | — | boolean; jika `true`, `reporter_*` diabaikan |
| `reporter_name` | — | max 150 |
| `reporter_contact` | — | max 150 |

**Respons `201`:**
```json
{ "success": true, "message": "Laporan tersimpan.", "report_no": "WBR-2026-0001", "id": 1 }
```

### 2.2 Daftar laporan — `GET /api/incident-reports`
Query opsional: `status`, `category`, `per_page` (default 25, maks 100).
```json
{ "success": true, "data": [ { "report_no": "WBR-2026-0001", "category": "etika", "status": "baru", ... } ],
  "meta": { "current_page": 1, "per_page": 25, "total": 1, "last_page": 1 } }
```

### 2.3 Detail — `GET /api/incident-reports/{report_no}`
```json
{ "success": true, "data": { "report_no": "WBR-2026-0001", "title": "...", "description": "...", ... } }
```
`404` bila tidak ditemukan.

### 2.4 Update status — `PATCH /api/incident-reports/{report_no}/status`
Body: `{ "status": "diproses" }` (salah satu enum status).
```json
{ "success": true, "message": "Status diperbarui.", "status": "diproses" }
```

**Kode error:** `401` token salah · `404` tidak ditemukan · `422` validasi gagal (`{ "message": "...", "errors": { ... } }`).

---

## 3. Contoh cURL

```bash
TOKEN="<INCIDENT_API_TOKEN>"
BASE="http://127.0.0.1:8000/api/incident-reports"

# Buat laporan
curl -X POST "$BASE" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"category":"etika","title":"Ketidaknyamanan di unit X","description":"Kronologi...","is_anonymous":true}'

# Daftar (filter status baru)
curl "$BASE?status=baru&per_page=10" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

# Detail
curl "$BASE/WBR-2026-0001" -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

# Update status
curl -X PATCH "$BASE/WBR-2026-0001/status" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"status":"diproses"}'
```

---

## 4. Di dalam aplikasi (web)
- Form publik (bisa anonim): **`/lapor-insiden`** (`insiden.publik`). Laporan tersimpan dengan `source=web`.
- Rekap & evaluasi (admin/manager): menu **Pelaporan Insiden → Rekap Insiden** (`/insiden/rekap`) — statistik per status/kategori/sumber, ubah status, hapus, detail. Laporan dari API (`source=api`) & web tampil bersama.
