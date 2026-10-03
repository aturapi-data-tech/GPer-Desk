{{-- Nomor dokumen di header penyusun: nomor resmi bila sudah terbit; bila belum, perkiraan nomor menurut
     model & pola jenisnya (DokumenService::pratinjauNomor) + tombol terbitkan. Variabel: $dokId, $nomor.
     Komponen pemakai wajib punya aksi terbitkanNomor(). Partial tanpa ⚡ — bukan komponen. --}}
@php
    use App\Services\DokumenService;
    $pn = ($dokId && ! $nomor) ? DokumenService::pratinjauNomor((int) $dokId) : null;
@endphp
<div class="flex flex-wrap items-center gap-2 mt-1 text-sm">
    @if ($nomor)
        <span class="text-muted">Nomor</span>
        <span class="font-mono font-semibold text-ink dark:text-gray-100">{{ $nomor }}</span>
    @elseif ($pn && $pn['nomor'])
        <span class="text-muted">Nomor</span>
        <span class="px-2 py-0.5 font-mono border border-dashed rounded-lg border-hairline text-muted dark:border-gray-600 dark:text-gray-300"
            title="{{ $pn['catatan'] }}">{{ $pn['nomor'] }}</span>
        <x-badge variant="gray">perkiraan · belum terbit</x-badge>
        @if (DokumenService::bolehUbah())
            <x-confirm-button variant="secondary" action="terbitkanNomor" title="Terbitkan nomor"
                message="Nomor {{ $pn['nomor'] }} akan diterbitkan dan tidak bisa ditarik kembali. Lanjutkan?"
                confirmText="Terbitkan">Terbitkan nomor</x-confirm-button>
        @endif
    @else
        <span class="italic text-muted">belum bernomor</span>
        @if ($pn)<span class="text-xs text-muted">— {{ $pn['catatan'] }}</span>@endif
    @endif
</div>
