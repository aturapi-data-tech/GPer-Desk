{{-- Kotak unit di bagan. Variabel: $u {id, nama, jenis, kepala}, $warna, $boleh.
     Tombol kecil: tambah unit di bawahnya · ubah · hapus (konfirmasi lewat modal di halaman induk). Partial tanpa ⚡. --}}
<div class="org-kotak" style="border-top-color: {{ $warna[$u['jenis']] ?? '#6b7280' }}">
    <div class="org-nama">{{ $u['nama'] }}</div>
    <div class="org-kepala {{ $u['kepala'] ? '' : 'org-kosong' }}">{{ $u['kepala'] ?: 'belum diisi' }}</div>
    @if ($boleh)
        <div class="org-aksi">
            <button type="button" class="org-btn org-btn-tambah" wire:click="openCreate({{ $u['id'] }})" title="Tambah unit di bawah {{ $u['nama'] }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            </button>
            <button type="button" class="org-btn" wire:click="openEdit({{ $u['id'] }})" title="Ubah {{ $u['nama'] }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </button>
            <button type="button" class="org-btn org-btn-hapus" wire:click="tanyaHapus({{ $u['id'] }})" title="Hapus {{ $u['nama'] }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
        </div>
    @endif
</div>
