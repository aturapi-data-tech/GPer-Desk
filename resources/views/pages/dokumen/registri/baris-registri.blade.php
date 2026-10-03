{{-- Satu baris registri (rekursif untuk tampilan pohon). Variabel: $row, $kedalaman, $turunan,
     $terbuka, $pohon, $bolehUbah. Partial tanpa ⚡ — bukan komponen. --}}
@php
    use App\Services\DokumenService;
    $buka = in_array((int) $row->dok_id, $terbuka, true);
@endphp
<tr wire:key="dok-{{ $row->dok_id }}-{{ $kedalaman }}-{{ $kunci ?? '' }}" class="{{ $kedalaman ? 'bg-surface-soft/40 dark:bg-gray-800/30' : '' }}">
    <td>
        <div class="flex flex-col gap-1">
            <x-badge variant="{{ ['purple','brand','info','success','warning','gray'][(int) $row->tingkat] ?? 'gray' }}">
                T{{ $row->tingkat }}
            </x-badge>
            <span class="text-xs text-muted dark:text-gray-400">{{ $row->jenis_nama }}</span>
        </div>
    </td>
    <td>
        <div class="flex items-start gap-2" style="padding-left: {{ $kedalaman * 1.5 }}rem">
            @if ($pohon && (int) $row->jml_turunan > 0)
                <button type="button" wire:click="toggle({{ $row->dok_id }})"
                    class="mt-0.5 w-5 text-sm text-brand-green dark:text-brand-lime" title="Turunan">{{ $buka ? '▾' : '▸' }}</button>
            @elseif ($pohon)
                <span class="w-5"></span>
            @endif
            <div class="min-w-0">
                <button type="button" wire:click="openDetail({{ $row->dok_id }})"
                    class="text-left ds-td-strong hover:underline">{{ $row->dok_judul }}</button>
                <div class="mt-0.5 text-xs text-muted dark:text-gray-400">
                    @if ($row->dok_no)
                        <span class="ds-td-token">{{ $row->dok_no }}</span>
                    @else
                        <span class="italic">belum bernomor</span>
                    @endif
                    @if ($row->unit_kode) · {{ $row->unit_kode }} @endif
                    @if ((int) $row->jml_turunan > 0) · {{ $row->jml_turunan }} turunan @endif
                </div>
                @if (! $pohon && $row->induk_judul)
                    <div class="mt-0.5 text-xs truncate text-muted-soft">↳ induk: {{ $row->induk_no ? $row->induk_no . ' — ' : '' }}{{ $row->induk_judul }}</div>
                @endif
            </div>
        </div>
    </td>
    <td class="text-sm">{{ DokumenService::tanggalIndonesia($row->tgl_terbit) ?: '—' }}</td>
    <td class="ds-c">{{ $row->no_revisi }}</td>
    <td>
        <x-badge :variant="DokumenService::STATUS_BADGE[$row->status] ?? 'gray'">{{ DokumenService::STATUS[$row->status] ?? $row->status }}</x-badge>
        @if ($row->status === 'BERLAKU' && $row->tgl_kaji_ulang && \Carbon\Carbon::parse($row->tgl_kaji_ulang)->lte(now()->addDays(60)))
            <div class="mt-1 text-xs text-amber-600">kaji ulang {{ DokumenService::tanggalIndonesia($row->tgl_kaji_ulang) }}</div>
        @endif
    </td>
    <td class="ds-c">
        <div class="flex justify-center gap-1">
            @if ($row->bisa_disusun === 'Y')
                <x-icon-button color="blue" type="button" wire:click="openSusun({{ $row->dok_id }})" title="Susun isi">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </x-icon-button>
                <x-icon-button color="gray" type="button" wire:click="lihatPdf({{ $row->dok_id }})" title="Lihat & unduh PDF">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2zM12 11v6m0 0l-2.5-2.5M12 17l2.5-2.5"/></svg>
                </x-icon-button>
            @endif
            @if ($bolehUbah)
                <x-action-delete :action="'requestDelete(' . $row->dok_id . ')'" title="Hapus Dokumen"
                    message="Hapus {{ $row->dok_judul }}? Isi naskahnya ikut terhapus." />
            @endif
        </div>
    </td>
</tr>
@if ($pohon && $buka)
    @foreach ($turunan[$row->dok_id] ?? [] as $anak)
        @include('pages.dokumen.registri.baris-registri', [
            'row' => $anak, 'kedalaman' => $kedalaman + 1, 'turunan' => $turunan,
            'terbuka' => $terbuka, 'pohon' => $pohon, 'bolehUbah' => $bolehUbah,
        ])
    @endforeach
@endif
