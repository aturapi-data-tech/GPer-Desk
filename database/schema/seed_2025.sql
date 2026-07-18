-- ============================================================
-- SIGAP — CONTOH DATA (SEED) TAHUN 2025
-- Schema/owner: GPER
-- Mencakup seluruh modul: user & role, master data, tiket,
-- perencanaan strategis, suara pasien, survei, pengaduan, IKP.
--
-- Catatan:
--  * FK memakai subquery (SELECT id ... WHERE <kunci natural>), jadi
--    ID dibuat otomatis oleh trigger — sequence tetap sinkron.
--  * Kolom reserved word dikutip: "LEVEL", "COMMENT", "VALUE".
--  * Jalankan berurutan dari atas ke bawah. Diakhiri COMMIT.
--  * Password semua user = "password" (hash bcrypt di bawah).
-- ============================================================

-- ============================================================
-- 1) ROLES (spatie) + USERS
-- ============================================================
INSERT INTO GPER.roles (name, guard_name, created_at, updated_at) VALUES ('admin',    'web', TIMESTAMP '2025-01-01 08:00:00', TIMESTAMP '2025-01-01 08:00:00');
INSERT INTO GPER.roles (name, guard_name, created_at, updated_at) VALUES ('manager',  'web', TIMESTAMP '2025-01-01 08:00:00', TIMESTAMP '2025-01-01 08:00:00');
INSERT INTO GPER.roles (name, guard_name, created_at, updated_at) VALUES ('teknisi',  'web', TIMESTAMP '2025-01-01 08:00:00', TIMESTAMP '2025-01-01 08:00:00');
INSERT INTO GPER.roles (name, guard_name, created_at, updated_at) VALUES ('direktur', 'web', TIMESTAMP '2025-01-01 08:00:00', TIMESTAMP '2025-01-01 08:00:00');

-- Departemen dulu (dipakai sbagai sektor & FK user) -- lihat bagian 2, tapi kita butuh untuk sector_id user.
INSERT INTO GPER.departments (name, code, head_name, description, is_active, is_sector, created_at, updated_at) VALUES ('IT',           'IT',   'Andi Pratama',  'Teknologi Informasi (sektor penanganan)', '1', '1', TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');
INSERT INTO GPER.departments (name, code, head_name, description, is_active, is_sector, created_at, updated_at) VALUES ('CSSD',         'CSSD', 'Rina Wulandari','Central Sterile Supply Department (sektor)', '1', '1', TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');
INSERT INTO GPER.departments (name, code, head_name, description, is_active, is_sector, created_at, updated_at) VALUES ('IPS',          'IPS',  'Joko Susilo',   'Instalasi Pemeliharaan Sarana (sektor)',     '1', '1', TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');
INSERT INTO GPER.departments (name, code, head_name, description, is_active, is_sector, created_at, updated_at) VALUES ('Keperawatan',  'KEP',  'Siti Aminah',   'Bidang Keperawatan',                          '1', '0', TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');
INSERT INTO GPER.departments (name, code, head_name, description, is_active, is_sector, created_at, updated_at) VALUES ('Farmasi',      'FAR',  'Dewi Lestari',  'Instalasi Farmasi',                           '1', '0', TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');
INSERT INTO GPER.departments (name, code, head_name, description, is_active, is_sector, created_at, updated_at) VALUES ('Rekam Medis',  'RM',   'Hasan Basri',   'Unit Rekam Medis',                            '1', '0', TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');

-- Users (password = "password")
INSERT INTO GPER.users (name, email, email_verified_at, password, sector_id, created_at, updated_at)
VALUES ('Admin Sistem', 'admin@sigap.local', TIMESTAMP '2025-01-02 08:00:00', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');
INSERT INTO GPER.users (name, email, email_verified_at, password, sector_id, created_at, updated_at)
VALUES ('Budi Santoso (Manager)', 'manager@sigap.local', TIMESTAMP '2025-01-02 08:00:00', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');
INSERT INTO GPER.users (name, email, email_verified_at, password, sector_id, created_at, updated_at)
VALUES ('dr. Maya Direktur', 'direktur@sigap.local', TIMESTAMP '2025-01-02 08:00:00', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');
INSERT INTO GPER.users (name, email, email_verified_at, password, sector_id, created_at, updated_at)
VALUES ('Teknisi IT', 'teknisi.it@sigap.local', TIMESTAMP '2025-01-02 08:00:00', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM GPER.departments WHERE name='IT'), TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');
INSERT INTO GPER.users (name, email, email_verified_at, password, sector_id, created_at, updated_at)
VALUES ('Teknisi CSSD', 'teknisi.cssd@sigap.local', TIMESTAMP '2025-01-02 08:00:00', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM GPER.departments WHERE name='CSSD'), TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');
INSERT INTO GPER.users (name, email, email_verified_at, password, sector_id, created_at, updated_at)
VALUES ('Teknisi IPS', 'teknisi.ips@sigap.local', TIMESTAMP '2025-01-02 08:00:00', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', (SELECT id FROM GPER.departments WHERE name='IPS'), TIMESTAMP '2025-01-02 08:00:00', TIMESTAMP '2025-01-02 08:00:00');

-- Assign role (model_has_roles: role_id, model_type, model_id)
INSERT INTO GPER.model_has_roles (role_id, model_type, model_id) VALUES ((SELECT id FROM GPER.roles WHERE name='admin'    AND guard_name='web'), 'App\Models\User', (SELECT id FROM GPER.users WHERE email='admin@sigap.local'));
INSERT INTO GPER.model_has_roles (role_id, model_type, model_id) VALUES ((SELECT id FROM GPER.roles WHERE name='manager'  AND guard_name='web'), 'App\Models\User', (SELECT id FROM GPER.users WHERE email='manager@sigap.local'));
INSERT INTO GPER.model_has_roles (role_id, model_type, model_id) VALUES ((SELECT id FROM GPER.roles WHERE name='direktur' AND guard_name='web'), 'App\Models\User', (SELECT id FROM GPER.users WHERE email='direktur@sigap.local'));
INSERT INTO GPER.model_has_roles (role_id, model_type, model_id) VALUES ((SELECT id FROM GPER.roles WHERE name='teknisi'  AND guard_name='web'), 'App\Models\User', (SELECT id FROM GPER.users WHERE email='teknisi.it@sigap.local'));
INSERT INTO GPER.model_has_roles (role_id, model_type, model_id) VALUES ((SELECT id FROM GPER.roles WHERE name='teknisi'  AND guard_name='web'), 'App\Models\User', (SELECT id FROM GPER.users WHERE email='teknisi.cssd@sigap.local'));
INSERT INTO GPER.model_has_roles (role_id, model_type, model_id) VALUES ((SELECT id FROM GPER.roles WHERE name='teknisi'  AND guard_name='web'), 'App\Models\User', (SELECT id FROM GPER.users WHERE email='teknisi.ips@sigap.local'));

-- ============================================================
-- 2) MASTER DATA TIKET (kategori, prioritas, status, lokasi)
-- ============================================================
INSERT INTO GPER.ticket_categories (name, code, description, is_active, sector_id, created_at, updated_at) VALUES ('Jaringan / Internet', 'NET',  'Gangguan koneksi jaringan/internet', '1', (SELECT id FROM GPER.departments WHERE name='IT'),   TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.ticket_categories (name, code, description, is_active, sector_id, created_at, updated_at) VALUES ('Hardware / Komputer', 'HW',   'Kerusakan komputer/printer',          '1', (SELECT id FROM GPER.departments WHERE name='IT'),   TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.ticket_categories (name, code, description, is_active, sector_id, created_at, updated_at) VALUES ('Sterilisasi Alat',    'STR',  'Permintaan/kendala sterilisasi',      '1', (SELECT id FROM GPER.departments WHERE name='CSSD'), TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.ticket_categories (name, code, description, is_active, sector_id, created_at, updated_at) VALUES ('Listrik / Genset',    'LIS',  'Gangguan kelistrikan',                '1', (SELECT id FROM GPER.departments WHERE name='IPS'),  TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.ticket_categories (name, code, description, is_active, sector_id, created_at, updated_at) VALUES ('AC / Pendingin',      'AC',   'Kerusakan AC/pendingin ruangan',      '1', (SELECT id FROM GPER.departments WHERE name='IPS'),  TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');

INSERT INTO GPER.priorities (name, "LEVEL", color, sla_hours, is_active, created_at, updated_at) VALUES ('Rendah', 1, '#22c55e', 72, '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.priorities (name, "LEVEL", color, sla_hours, is_active, created_at, updated_at) VALUES ('Sedang', 2, '#eab308', 48, '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.priorities (name, "LEVEL", color, sla_hours, is_active, created_at, updated_at) VALUES ('Tinggi', 3, '#f97316', 24, '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.priorities (name, "LEVEL", color, sla_hours, is_active, created_at, updated_at) VALUES ('Kritis', 4, '#ef4444', 4,  '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');

INSERT INTO GPER.ticket_statuses (name, code, color, sort_order, is_closed, is_active, created_at, updated_at) VALUES ('Baru',     'baru',    '#3b82f6', 1, '0', '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.ticket_statuses (name, code, color, sort_order, is_closed, is_active, created_at, updated_at) VALUES ('Diproses', 'proses',  '#eab308', 2, '0', '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.ticket_statuses (name, code, color, sort_order, is_closed, is_active, created_at, updated_at) VALUES ('Selesai',  'selesai', '#22c55e', 3, '1', '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.ticket_statuses (name, code, color, sort_order, is_closed, is_active, created_at, updated_at) VALUES ('Ditutup',  'tutup',   '#6b7280', 4, '1', '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');

INSERT INTO GPER.locations (name, code, building, floor, description, is_active, created_at, updated_at) VALUES ('Gedung A - Lantai 1', 'A1',  'Gedung A', '1', 'Pendaftaran & Poli',   '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.locations (name, code, building, floor, description, is_active, created_at, updated_at) VALUES ('IGD',                 'IGD', 'Gedung A', '1', 'Instalasi Gawat Darurat','1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.locations (name, code, building, floor, description, is_active, created_at, updated_at) VALUES ('Ruang Server',        'SRV', 'Gedung B', '2', 'Data center',           '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.locations (name, code, building, floor, description, is_active, created_at, updated_at) VALUES ('Ruang CSSD',          'CSD', 'Gedung B', '1', 'Sterilisasi',           '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');
INSERT INTO GPER.locations (name, code, building, floor, description, is_active, created_at, updated_at) VALUES ('Ruang Genset',        'GEN', 'Utilitas', '1', 'Kelistrikan',           '1', TIMESTAMP '2025-01-03 08:00:00', TIMESTAMP '2025-01-03 08:00:00');

-- ============================================================
-- 3) TIKET + KOMENTAR + AKTIVITAS
-- ============================================================
INSERT INTO GPER.tickets (ticket_no, title, description, reported_by, technician_id, resolution, part_cost, reported_at, resolved_at, created_at, updated_at, category_id, priority_id, status_id, location_id, department_id, sector_id, first_responded_by, first_responded_at, location_detail, reporter_name)
VALUES ('TKT-2025-0001', 'Internet poli mati total', 'Koneksi internet di poli umum putus sejak pagi.', (SELECT id FROM GPER.users WHERE email='admin@sigap.local'), (SELECT id FROM GPER.users WHERE email='teknisi.it@sigap.local'), 'Restart switch & ganti kabel LAN.', 150000, TIMESTAMP '2025-02-10 08:15:00', TIMESTAMP '2025-02-10 11:30:00', TIMESTAMP '2025-02-10 08:15:00', TIMESTAMP '2025-02-10 11:30:00', (SELECT id FROM GPER.ticket_categories WHERE name='Jaringan / Internet'), (SELECT id FROM GPER.priorities WHERE name='Tinggi'), (SELECT id FROM GPER.ticket_statuses WHERE name='Selesai'), (SELECT id FROM GPER.locations WHERE name='Gedung A - Lantai 1'), (SELECT id FROM GPER.departments WHERE name='Keperawatan'), (SELECT id FROM GPER.departments WHERE name='IT'), (SELECT id FROM GPER.users WHERE email='teknisi.it@sigap.local'), TIMESTAMP '2025-02-10 08:40:00', 'Meja pendaftaran no. 2', 'Perawat Poli');

INSERT INTO GPER.tickets (ticket_no, title, description, reported_by, technician_id, part_cost, reported_at, created_at, updated_at, category_id, priority_id, status_id, location_id, department_id, sector_id, first_responded_by, first_responded_at, reporter_name)
VALUES ('TKT-2025-0002', 'Printer IGD tidak bisa cetak', 'Printer struk di IGD error paper jam.', (SELECT id FROM GPER.users WHERE email='manager@sigap.local'), (SELECT id FROM GPER.users WHERE email='teknisi.it@sigap.local'), 0, TIMESTAMP '2025-03-05 09:00:00', TIMESTAMP '2025-03-05 09:00:00', TIMESTAMP '2025-03-05 09:20:00', (SELECT id FROM GPER.ticket_categories WHERE name='Hardware / Komputer'), (SELECT id FROM GPER.priorities WHERE name='Sedang'), (SELECT id FROM GPER.ticket_statuses WHERE name='Diproses'), (SELECT id FROM GPER.locations WHERE name='IGD'), (SELECT id FROM GPER.departments WHERE name='Keperawatan'), (SELECT id FROM GPER.departments WHERE name='IT'), (SELECT id FROM GPER.users WHERE email='teknisi.it@sigap.local'), TIMESTAMP '2025-03-05 09:20:00', 'Petugas IGD');

INSERT INTO GPER.tickets (ticket_no, title, description, reported_by, technician_id, resolution, part_cost, reported_at, resolved_at, created_at, updated_at, category_id, priority_id, status_id, location_id, department_id, sector_id, first_responded_by, first_responded_at, reporter_name)
VALUES ('TKT-2025-0003', 'Autoclave CSSD suhu tidak stabil', 'Mesin autoclave gagal mencapai suhu sterilisasi.', (SELECT id FROM GPER.users WHERE email='admin@sigap.local'), (SELECT id FROM GPER.users WHERE email='teknisi.cssd@sigap.local'), 'Kalibrasi sensor suhu & ganti gasket.', 850000, TIMESTAMP '2025-04-12 07:30:00', TIMESTAMP '2025-04-13 15:00:00', TIMESTAMP '2025-04-12 07:30:00', TIMESTAMP '2025-04-13 15:00:00', (SELECT id FROM GPER.ticket_categories WHERE name='Sterilisasi Alat'), (SELECT id FROM GPER.priorities WHERE name='Kritis'), (SELECT id FROM GPER.ticket_statuses WHERE name='Selesai'), (SELECT id FROM GPER.locations WHERE name='Ruang CSSD'), (SELECT id FROM GPER.departments WHERE name='CSSD'), (SELECT id FROM GPER.departments WHERE name='CSSD'), (SELECT id FROM GPER.users WHERE email='teknisi.cssd@sigap.local'), TIMESTAMP '2025-04-12 08:00:00', 'Staf CSSD');

INSERT INTO GPER.tickets (ticket_no, title, description, reported_by, technician_id, part_cost, reported_at, created_at, updated_at, category_id, priority_id, status_id, location_id, department_id, sector_id, reporter_name)
VALUES ('TKT-2025-0004', 'AC ruang rawat inap bocor', 'AC menetes ke lantai ruang rawat inap lantai 2.', (SELECT id FROM GPER.users WHERE email='manager@sigap.local'), (SELECT id FROM GPER.users WHERE email='teknisi.ips@sigap.local'), 0, TIMESTAMP '2025-05-20 10:00:00', TIMESTAMP '2025-05-20 10:00:00', TIMESTAMP '2025-05-20 10:05:00', (SELECT id FROM GPER.ticket_categories WHERE name='AC / Pendingin'), (SELECT id FROM GPER.priorities WHERE name='Sedang'), (SELECT id FROM GPER.ticket_statuses WHERE name='Baru'), (SELECT id FROM GPER.locations WHERE name='Gedung A - Lantai 1'), (SELECT id FROM GPER.departments WHERE name='Keperawatan'), (SELECT id FROM GPER.departments WHERE name='IPS'), 'Perawat Rawat Inap');

INSERT INTO GPER.tickets (ticket_no, title, description, reported_by, technician_id, resolution, part_cost, reported_at, resolved_at, created_at, updated_at, category_id, priority_id, status_id, location_id, department_id, sector_id, first_responded_by, first_responded_at, reporter_name)
VALUES ('TKT-2025-0005', 'Genset gagal auto-start saat listrik padam', 'Saat PLN padam, genset tidak menyala otomatis.', (SELECT id FROM GPER.users WHERE email='admin@sigap.local'), (SELECT id FROM GPER.users WHERE email='teknisi.ips@sigap.local'), 'Ganti aki & perbaiki modul ATS.', 1250000, TIMESTAMP '2025-06-18 19:00:00', TIMESTAMP '2025-06-19 09:00:00', TIMESTAMP '2025-06-18 19:00:00', TIMESTAMP '2025-06-19 09:00:00', (SELECT id FROM GPER.ticket_categories WHERE name='Listrik / Genset'), (SELECT id FROM GPER.priorities WHERE name='Kritis'), (SELECT id FROM GPER.ticket_statuses WHERE name='Selesai'), (SELECT id FROM GPER.locations WHERE name='Ruang Genset'), (SELECT id FROM GPER.departments WHERE name='IPS'), (SELECT id FROM GPER.departments WHERE name='IPS'), (SELECT id FROM GPER.users WHERE email='teknisi.ips@sigap.local'), TIMESTAMP '2025-06-18 19:20:00', 'Security Malam');

INSERT INTO GPER.tickets (ticket_no, title, description, reported_by, part_cost, reported_at, created_at, updated_at, category_id, priority_id, status_id, location_id, department_id, sector_id, location_detail, reporter_name)
VALUES ('TKT-2025-0006', 'Wifi lambat di ruang server', 'Akses ke SIMRS lambat dari ruang server.', (SELECT id FROM GPER.users WHERE email='admin@sigap.local'), 0, TIMESTAMP '2025-08-01 13:00:00', TIMESTAMP '2025-08-01 13:00:00', TIMESTAMP '2025-08-01 13:00:00', (SELECT id FROM GPER.ticket_categories WHERE name='Jaringan / Internet'), (SELECT id FROM GPER.priorities WHERE name='Rendah'), (SELECT id FROM GPER.ticket_statuses WHERE name='Baru'), (SELECT id FROM GPER.locations WHERE name='Ruang Server'), (SELECT id FROM GPER.departments WHERE name='Rekam Medis'), (SELECT id FROM GPER.departments WHERE name='IT'), 'Rak server B2', 'Staf IT');

-- Komentar tiket
INSERT INTO GPER.ticket_comments (ticket_id, user_id, "COMMENT", created_at, updated_at) VALUES ((SELECT id FROM GPER.tickets WHERE ticket_no='TKT-2025-0001'), (SELECT id FROM GPER.users WHERE email='teknisi.it@sigap.local'), 'Sudah cek switch, kabel LAN putus. Sedang diganti.', TIMESTAMP '2025-02-10 09:00:00', TIMESTAMP '2025-02-10 09:00:00');
INSERT INTO GPER.ticket_comments (ticket_id, user_id, "COMMENT", created_at, updated_at) VALUES ((SELECT id FROM GPER.tickets WHERE ticket_no='TKT-2025-0001'), (SELECT id FROM GPER.users WHERE email='admin@sigap.local'), 'Terima kasih, internet sudah normal kembali.', TIMESTAMP '2025-02-10 11:35:00', TIMESTAMP '2025-02-10 11:35:00');
INSERT INTO GPER.ticket_comments (ticket_id, user_id, "COMMENT", created_at, updated_at) VALUES ((SELECT id FROM GPER.tickets WHERE ticket_no='TKT-2025-0003'), (SELECT id FROM GPER.users WHERE email='teknisi.cssd@sigap.local'), 'Menunggu gasket pengganti dari gudang.', TIMESTAMP '2025-04-12 13:00:00', TIMESTAMP '2025-04-12 13:00:00');

-- Aktivitas tiket
INSERT INTO GPER.ticket_activities (ticket_id, user_id, action, from_value, to_value, note, created_at) VALUES ((SELECT id FROM GPER.tickets WHERE ticket_no='TKT-2025-0001'), (SELECT id FROM GPER.users WHERE email='teknisi.it@sigap.local'), 'status', 'Baru', 'Diproses', 'Diambil teknisi IT', TIMESTAMP '2025-02-10 08:40:00');
INSERT INTO GPER.ticket_activities (ticket_id, user_id, action, from_value, to_value, note, created_at) VALUES ((SELECT id FROM GPER.tickets WHERE ticket_no='TKT-2025-0001'), (SELECT id FROM GPER.users WHERE email='teknisi.it@sigap.local'), 'status', 'Diproses', 'Selesai', 'Perbaikan selesai', TIMESTAMP '2025-02-10 11:30:00');
INSERT INTO GPER.ticket_activities (ticket_id, user_id, action, from_value, to_value, note, created_at) VALUES ((SELECT id FROM GPER.tickets WHERE ticket_no='TKT-2025-0003'), (SELECT id FROM GPER.users WHERE email='teknisi.cssd@sigap.local'), 'status', 'Baru', 'Selesai', 'Kalibrasi & ganti gasket', TIMESTAMP '2025-04-13 15:00:00');

-- ============================================================
-- 4) PERENCANAAN STRATEGIS
-- ============================================================
INSERT INTO GPER.strategic_goals (title, description, fiscal_year, owner_id, status, start_date, end_date, created_at, updated_at) VALUES ('Peningkatan Kepuasan Pasien', 'Menaikkan skor kepuasan pasien menjadi 90%.', '2025', (SELECT id FROM GPER.users WHERE email='direktur@sigap.local'), 'active', DATE '2025-01-01', DATE '2025-12-31', TIMESTAMP '2025-01-05 08:00:00', TIMESTAMP '2025-01-05 08:00:00');
INSERT INTO GPER.strategic_goals (title, description, fiscal_year, owner_id, status, start_date, end_date, created_at, updated_at) VALUES ('Transformasi Digital RS', 'Digitalisasi layanan & integrasi SATUSEHAT.', '2025', (SELECT id FROM GPER.users WHERE email='manager@sigap.local'), 'active', DATE '2025-01-01', DATE '2025-12-31', TIMESTAMP '2025-01-05 08:00:00', TIMESTAMP '2025-01-05 08:00:00');

INSERT INTO GPER.strategic_plans (strategic_goal_id, title, description, budget, start_date, end_date, status, created_at, updated_at) VALUES ((SELECT id FROM GPER.strategic_goals WHERE title='Peningkatan Kepuasan Pasien'), 'Program Survei Kepuasan Berkala', 'Survei tiap triwulan.', 15000000, DATE '2025-02-01', DATE '2025-11-30', 'in_progress', TIMESTAMP '2025-01-06 08:00:00', TIMESTAMP '2025-01-06 08:00:00');
INSERT INTO GPER.strategic_plans (strategic_goal_id, title, description, budget, start_date, end_date, status, created_at, updated_at) VALUES ((SELECT id FROM GPER.strategic_goals WHERE title='Transformasi Digital RS'), 'Upgrade Infrastruktur Jaringan', 'Peremajaan switch & access point.', 120000000, DATE '2025-03-01', DATE '2025-09-30', 'planned', TIMESTAMP '2025-01-06 08:00:00', TIMESTAMP '2025-01-06 08:00:00');

INSERT INTO GPER.action_items (strategic_plan_id, title, description, assigned_to, due_date, status, progress, created_at, updated_at) VALUES ((SELECT id FROM GPER.strategic_plans WHERE title='Program Survei Kepuasan Berkala'), 'Susun kuesioner survei', 'Finalisasi pertanyaan survei kepuasan.', (SELECT id FROM GPER.users WHERE email='manager@sigap.local'), DATE '2025-02-15', 'done', 100, TIMESTAMP '2025-01-07 08:00:00', TIMESTAMP '2025-02-15 08:00:00');
INSERT INTO GPER.action_items (strategic_plan_id, title, description, assigned_to, due_date, status, progress, created_at, updated_at) VALUES ((SELECT id FROM GPER.strategic_plans WHERE title='Program Survei Kepuasan Berkala'), 'Sebar survei Q1', 'Distribusi survei triwulan pertama.', (SELECT id FROM GPER.users WHERE email='admin@sigap.local'), DATE '2025-03-31', 'in_progress', 60, TIMESTAMP '2025-01-07 08:00:00', TIMESTAMP '2025-03-10 08:00:00');
INSERT INTO GPER.action_items (strategic_plan_id, title, description, assigned_to, due_date, status, progress, created_at, updated_at) VALUES ((SELECT id FROM GPER.strategic_plans WHERE title='Upgrade Infrastruktur Jaringan'), 'Inventarisasi perangkat lama', 'Data perangkat jaringan existing.', (SELECT id FROM GPER.users WHERE email='teknisi.it@sigap.local'), DATE '2025-03-20', 'in_progress', 40, TIMESTAMP '2025-01-07 08:00:00', TIMESTAMP '2025-03-05 08:00:00');
INSERT INTO GPER.action_items (strategic_plan_id, title, description, assigned_to, due_date, status, progress, created_at, updated_at) VALUES ((SELECT id FROM GPER.strategic_plans WHERE title='Upgrade Infrastruktur Jaringan'), 'Pengadaan switch baru', 'Proses pengadaan switch managed.', (SELECT id FROM GPER.users WHERE email='manager@sigap.local'), DATE '2025-06-30', 'todo', 0, TIMESTAMP '2025-01-07 08:00:00', TIMESTAMP '2025-01-07 08:00:00');

INSERT INTO GPER.review_sessions (title, session_date, chaired_by, minutes, decisions, status, created_at, updated_at) VALUES ('Rapat Tinjauan Manajemen Q1 2025', DATE '2025-04-05', (SELECT id FROM GPER.users WHERE email='direktur@sigap.local'), 'Pembahasan capaian sasaran Q1.', 'Percepat pengadaan switch; tambah teknisi IPS.', 'final', TIMESTAMP '2025-04-05 13:00:00', TIMESTAMP '2025-04-05 15:00:00');
INSERT INTO GPER.review_sessions (title, session_date, chaired_by, minutes, decisions, status, created_at, updated_at) VALUES ('Rapat Tinjauan Manajemen Q2 2025', DATE '2025-07-05', (SELECT id FROM GPER.users WHERE email='direktur@sigap.local'), 'Evaluasi tiket & kepuasan Q2.', 'Fokus SLA tiket kritis < 4 jam.', 'draft', TIMESTAMP '2025-07-05 13:00:00', TIMESTAMP '2025-07-05 15:00:00');

INSERT INTO GPER.review_ticket_summaries (review_session_id, category, period_start, period_end, total_tickets, resolved_tickets, avg_duration_hours, total_cost, notes, created_at, updated_at) VALUES ((SELECT id FROM GPER.review_sessions WHERE title='Rapat Tinjauan Manajemen Q1 2025'), 'IT', DATE '2025-01-01', DATE '2025-03-31', 12, 10, 5.50, 450000, 'Mayoritas gangguan jaringan.', TIMESTAMP '2025-04-05 13:00:00', TIMESTAMP '2025-04-05 13:00:00');
INSERT INTO GPER.review_ticket_summaries (review_session_id, category, period_start, period_end, total_tickets, resolved_tickets, avg_duration_hours, total_cost, notes, created_at, updated_at) VALUES ((SELECT id FROM GPER.review_sessions WHERE title='Rapat Tinjauan Manajemen Q2 2025'), 'IPS', DATE '2025-04-01', DATE '2025-06-30', 8, 6, 12.00, 2100000, 'Perbaikan genset & AC.', TIMESTAMP '2025-07-05 13:00:00', TIMESTAMP '2025-07-05 13:00:00');

-- Kaitkan action item ke tiket
INSERT INTO GPER.action_ticket_link (action_item_id, ticket_id, created_at, updated_at) VALUES ((SELECT id FROM GPER.action_items WHERE title='Inventarisasi perangkat lama'), (SELECT id FROM GPER.tickets WHERE ticket_no='TKT-2025-0006'), TIMESTAMP '2025-08-01 14:00:00', TIMESTAMP '2025-08-01 14:00:00');

-- ============================================================
-- 5) SUARA PASIEN (patient_reviews)
-- ============================================================
INSERT INTO GPER.patient_reviews (reviewer_name, rating, "COMMENT", is_visible, created_at, updated_at) VALUES ('Nina',        5, 'Pelayanan farmasi ramah.',                      '1', TIMESTAMP '2025-02-11 10:00:00', TIMESTAMP '2025-02-11 10:00:00');
INSERT INTO GPER.patient_reviews (reviewer_name, rating, "COMMENT", is_visible, created_at, updated_at) VALUES ('Pak Yusuf',   4, 'Fasilitas bersih dan nyaman.',                  '1', TIMESTAMP '2025-03-02 11:00:00', TIMESTAMP '2025-03-02 11:00:00');
INSERT INTO GPER.patient_reviews (reviewer_name, rating, "COMMENT", is_visible, created_at, updated_at) VALUES ('Siti R.',     2, 'Antrean pendaftaran lama.',                     '1', TIMESTAMP '2025-03-15 09:30:00', TIMESTAMP '2025-03-15 09:30:00');
INSERT INTO GPER.patient_reviews (reviewer_name, rating, "COMMENT", is_visible, created_at, updated_at) VALUES ('Agus P.',     5, 'Perawat IGD sigap, terima kasih!',              '1', TIMESTAMP '2025-04-20 14:00:00', TIMESTAMP '2025-04-20 14:00:00');
INSERT INTO GPER.patient_reviews (reviewer_name, rating, "COMMENT", is_visible, created_at, updated_at) VALUES ('Bu Dewi',     4, 'Cukup baik, hanya parkir yang penuh.',          '1', TIMESTAMP '2025-05-08 08:45:00', TIMESTAMP '2025-05-08 08:45:00');
INSERT INTO GPER.patient_reviews (reviewer_name, rating, "COMMENT", is_visible, created_at, updated_at) VALUES ('Pak Hasan',   5, 'Dokternya teliti dan sabar menjelaskan.',       '1', TIMESTAMP '2025-06-11 13:15:00', TIMESTAMP '2025-06-11 13:15:00');
INSERT INTO GPER.patient_reviews (reviewer_name, rating, "COMMENT", is_visible, created_at, updated_at) VALUES ('Anonim',      3, 'Ruang tunggu perlu diperbanyak kursinya.',      '1', TIMESTAMP '2025-07-19 10:20:00', TIMESTAMP '2025-07-19 10:20:00');
INSERT INTO GPER.patient_reviews (reviewer_name, rating, "COMMENT", is_visible, created_at, updated_at) VALUES ('Bapak Andi',  4, 'Cukup baik, tapi antrean agak lama di farmasi.','1', TIMESTAMP '2025-09-03 15:40:00', TIMESTAMP '2025-09-03 15:40:00');

-- ============================================================
-- 6) SURVEI (surveys + questions + responses + answers)
-- ============================================================
INSERT INTO GPER.surveys (title, description, status, opened_at, closed_at, created_by, created_at, updated_at) VALUES ('Survei Kepuasan Layanan (Juli 2025)', 'Survei kepuasan pasien rawat jalan.', 'active', TIMESTAMP '2025-07-01 08:00:00', NULL, (SELECT id FROM GPER.users WHERE email='manager@sigap.local'), TIMESTAMP '2025-06-28 08:00:00', TIMESTAMP '2025-07-01 08:00:00');

INSERT INTO GPER.survey_questions (survey_id, question, type, sort_order, created_at, updated_at) VALUES ((SELECT id FROM GPER.surveys WHERE title='Survei Kepuasan Layanan (Juli 2025)'), 'Keramahan petugas',   'rating', 1, TIMESTAMP '2025-06-28 08:00:00', TIMESTAMP '2025-06-28 08:00:00');
INSERT INTO GPER.survey_questions (survey_id, question, type, sort_order, created_at, updated_at) VALUES ((SELECT id FROM GPER.surveys WHERE title='Survei Kepuasan Layanan (Juli 2025)'), 'Kebersihan ruangan',  'rating', 2, TIMESTAMP '2025-06-28 08:00:00', TIMESTAMP '2025-06-28 08:00:00');
INSERT INTO GPER.survey_questions (survey_id, question, type, sort_order, created_at, updated_at) VALUES ((SELECT id FROM GPER.surveys WHERE title='Survei Kepuasan Layanan (Juli 2025)'), 'Saran perbaikan',     'text',   3, TIMESTAMP '2025-06-28 08:00:00', TIMESTAMP '2025-06-28 08:00:00');

INSERT INTO GPER.survey_responses (survey_id, respondent_name, created_at, updated_at) VALUES ((SELECT id FROM GPER.surveys WHERE title='Survei Kepuasan Layanan (Juli 2025)'), 'Responden A', TIMESTAMP '2025-07-10 09:00:00', TIMESTAMP '2025-07-10 09:00:00');
INSERT INTO GPER.survey_responses (survey_id, respondent_name, created_at, updated_at) VALUES ((SELECT id FROM GPER.surveys WHERE title='Survei Kepuasan Layanan (Juli 2025)'), 'Responden B', TIMESTAMP '2025-07-11 10:30:00', TIMESTAMP '2025-07-11 10:30:00');
INSERT INTO GPER.survey_responses (survey_id, respondent_name, created_at, updated_at) VALUES ((SELECT id FROM GPER.surveys WHERE title='Survei Kepuasan Layanan (Juli 2025)'), 'Responden C', TIMESTAMP '2025-07-12 08:15:00', TIMESTAMP '2025-07-12 08:15:00');

INSERT INTO GPER.survey_answers (response_id, question_id, "VALUE", created_at, updated_at) VALUES ((SELECT id FROM GPER.survey_responses WHERE respondent_name='Responden A'), (SELECT id FROM GPER.survey_questions WHERE question='Keramahan petugas'),  '5', TIMESTAMP '2025-07-10 09:00:00', TIMESTAMP '2025-07-10 09:00:00');
INSERT INTO GPER.survey_answers (response_id, question_id, "VALUE", created_at, updated_at) VALUES ((SELECT id FROM GPER.survey_responses WHERE respondent_name='Responden A'), (SELECT id FROM GPER.survey_questions WHERE question='Kebersihan ruangan'), '4', TIMESTAMP '2025-07-10 09:00:00', TIMESTAMP '2025-07-10 09:00:00');
INSERT INTO GPER.survey_answers (response_id, question_id, "VALUE", created_at, updated_at) VALUES ((SELECT id FROM GPER.survey_responses WHERE respondent_name='Responden A'), (SELECT id FROM GPER.survey_questions WHERE question='Saran perbaikan'),    'Tambah kursi ruang tunggu.', TIMESTAMP '2025-07-10 09:00:00', TIMESTAMP '2025-07-10 09:00:00');
INSERT INTO GPER.survey_answers (response_id, question_id, "VALUE", created_at, updated_at) VALUES ((SELECT id FROM GPER.survey_responses WHERE respondent_name='Responden B'), (SELECT id FROM GPER.survey_questions WHERE question='Keramahan petugas'),  '4', TIMESTAMP '2025-07-11 10:30:00', TIMESTAMP '2025-07-11 10:30:00');
INSERT INTO GPER.survey_answers (response_id, question_id, "VALUE", created_at, updated_at) VALUES ((SELECT id FROM GPER.survey_responses WHERE respondent_name='Responden B'), (SELECT id FROM GPER.survey_questions WHERE question='Kebersihan ruangan'), '5', TIMESTAMP '2025-07-11 10:30:00', TIMESTAMP '2025-07-11 10:30:00');
INSERT INTO GPER.survey_answers (response_id, question_id, "VALUE", created_at, updated_at) VALUES ((SELECT id FROM GPER.survey_responses WHERE respondent_name='Responden B'), (SELECT id FROM GPER.survey_questions WHERE question='Saran perbaikan'),    'Sudah baik, pertahankan.', TIMESTAMP '2025-07-11 10:30:00', TIMESTAMP '2025-07-11 10:30:00');
INSERT INTO GPER.survey_answers (response_id, question_id, "VALUE", created_at, updated_at) VALUES ((SELECT id FROM GPER.survey_responses WHERE respondent_name='Responden C'), (SELECT id FROM GPER.survey_questions WHERE question='Keramahan petugas'),  '5', TIMESTAMP '2025-07-12 08:15:00', TIMESTAMP '2025-07-12 08:15:00');
INSERT INTO GPER.survey_answers (response_id, question_id, "VALUE", created_at, updated_at) VALUES ((SELECT id FROM GPER.survey_responses WHERE respondent_name='Responden C'), (SELECT id FROM GPER.survey_questions WHERE question='Kebersihan ruangan'), '3', TIMESTAMP '2025-07-12 08:15:00', TIMESTAMP '2025-07-12 08:15:00');
INSERT INTO GPER.survey_answers (response_id, question_id, "VALUE", created_at, updated_at) VALUES ((SELECT id FROM GPER.survey_responses WHERE respondent_name='Responden C'), (SELECT id FROM GPER.survey_questions WHERE question='Saran perbaikan'),    'Toilet perlu lebih sering dibersihkan.', TIMESTAMP '2025-07-12 08:15:00', TIMESTAMP '2025-07-12 08:15:00');

-- ============================================================
-- 7) PENGADUAN & ASPIRASI KARYAWAN (incident_reports)
-- ============================================================
INSERT INTO GPER.incident_reports (report_no, category, title, description, location, incident_date, reporter_name, reporter_contact, is_anonymous, status, source, created_at, updated_at) VALUES ('ADU-2025-001', 'etika', 'Perilaku tidak sopan rekan kerja', 'Terjadi perselisihan verbal di ruang ganti.', 'Ruang Ganti Staf', DATE '2025-03-01', 'Karyawan A', '08120000001', '0', 'proses', 'web', TIMESTAMP '2025-03-01 16:00:00', TIMESTAMP '2025-03-02 09:00:00');
INSERT INTO GPER.incident_reports (report_no, category, title, description, location, incident_date, is_anonymous, status, source, created_at, updated_at) VALUES ('ADU-2025-002', 'k3', 'Lantai licin dekat dapur gizi', 'Lantai basah tanpa tanda peringatan berpotensi jatuh.', 'Dapur Gizi', DATE '2025-04-10', '1', 'baru', 'web', TIMESTAMP '2025-04-10 11:00:00', TIMESTAMP '2025-04-10 11:00:00');
INSERT INTO GPER.incident_reports (report_no, category, title, description, location, incident_date, reporter_name, reporter_contact, is_anonymous, status, source, created_at, updated_at) VALUES ('ADU-2025-003', 'layanan', 'Usulan penambahan jam layanan farmasi', 'Antrean farmasi sore menumpuk, usul tambah shift.', 'Farmasi', DATE '2025-06-05', 'Karyawan B', 'karyawanb@sigap.local', '0', 'selesai', 'api', TIMESTAMP '2025-06-05 15:00:00', TIMESTAMP '2025-06-20 10:00:00');

-- ============================================================
-- 8) INSIDEN KESELAMATAN PASIEN / IKP (patient_safety_incidents)
-- ============================================================
INSERT INTO GPER.patient_safety_incidents (report_no, incident_type, title, chronology, patient_impact, immediate_action, grading, location, incident_date, incident_time, patient_ref, reporter_name, reporter_role, reporter_contact, is_anonymous, status, source, created_at, updated_at)
VALUES ('IKP-2025-001', 'knc', 'Salah label sampel darah nyaris', 'Petugas hampir menyerahkan sampel dengan label tertukar, diketahui sebelum diproses.', 'Tidak ada dampak ke pasien (nyaris cedera).', 'Sampel dilabel ulang & verifikasi ganda.', 'hijau', 'Laboratorium', DATE '2025-02-14', '10:30', 'RM-102345', 'Analis Lab', 'Analis Laboratorium', 'lab@sigap.local', '0', 'selesai', 'web', TIMESTAMP '2025-02-14 10:45:00', TIMESTAMP '2025-02-16 09:00:00');
INSERT INTO GPER.patient_safety_incidents (report_no, incident_type, title, chronology, patient_impact, immediate_action, grading, location, incident_date, incident_time, patient_ref, reporter_name, reporter_role, is_anonymous, status, source, created_at, updated_at)
VALUES ('IKP-2025-002', 'ktc', 'Pasien terpeleset di kamar mandi', 'Pasien terpeleset namun tidak mengalami cedera berarti.', 'Cedera ringan, tidak perlu tindakan lanjut.', 'Pasang pegangan & tanda lantai licin.', 'kuning', 'Rawat Inap Lt.2', DATE '2025-05-22', '06:15', 'RM-110987', 'Perawat Jaga', 'Perawat', '1', 'investigasi', 'web', TIMESTAMP '2025-05-22 06:40:00', TIMESTAMP '2025-05-23 08:00:00');
INSERT INTO GPER.patient_safety_incidents (report_no, incident_type, title, chronology, patient_impact, immediate_action, grading, location, incident_date, incident_time, patient_ref, reporter_name, reporter_role, reporter_contact, is_anonymous, status, source, created_at, updated_at)
VALUES ('IKP-2025-003', 'ktd', 'Reaksi alergi obat tak terdeteksi', 'Pasien mendapat antibiotik tanpa cek riwayat alergi, timbul ruam.', 'Ruam kulit, dirawat & diberi antihistamin.', 'Hentikan obat, beri antihistamin, update rekam alergi.', 'merah', 'IGD', DATE '2025-08-09', '21:00', 'RM-120456', 'dr. Jaga IGD', 'Dokter IGD', 'igd@sigap.local', '0', 'investigasi', 'web', TIMESTAMP '2025-08-09 21:30:00', TIMESTAMP '2025-08-10 08:00:00');
INSERT INTO GPER.patient_safety_incidents (report_no, incident_type, title, chronology, patient_impact, immediate_action, grading, location, incident_date, incident_time, patient_ref, is_anonymous, status, source, created_at, updated_at)
VALUES ('IKP-2025-004', 'kpcs', 'Tabung oksigen hampir habis tanpa cadangan', 'Kondisi berpotensi cedera: stok tabung O2 cadangan menipis di ruang tindakan.', NULL, 'Segera lakukan pengisian & atur stok minimum.', 'biru', 'Ruang Tindakan', DATE '2025-10-02', '14:00', NULL, '1', 'baru', 'web', TIMESTAMP '2025-10-02 14:20:00', TIMESTAMP '2025-10-02 14:20:00');

COMMIT;

-- ============================================================
-- SELESAI. Total contoh data 2025:
--   6 user (+4 role), 6 departemen, 5 kategori, 4 prioritas,
--   4 status, 5 lokasi, 6 tiket (+3 komentar, +3 aktivitas),
--   2 sasaran, 2 rencana, 4 aksi, 2 rapat, 2 rekap, 1 link,
--   8 ulasan pasien, 1 survei (3 soal, 3 responden, 9 jawaban),
--   3 pengaduan, 4 insiden IKP.
-- Login contoh: admin@sigap.local / password  (semua user pw = "password")
-- ============================================================
