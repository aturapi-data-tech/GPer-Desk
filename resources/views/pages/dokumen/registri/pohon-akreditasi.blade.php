{{-- Tampilan pohon akreditasi registri: Pokja → Standar → EP → dokumen. Variabel: $p (computed pohonAkr), $bolehUbah.
     Dokumen memakai baris-registri.blade.php (kedalaman 3); satu dokumen bisa tampil di beberapa EP → $kunci membedakan wire:key. --}}
@php
    $buka = $p['buka'] ?? [];
    $panah = fn ($k) => in_array($k, $buka, true) ? '▾' : '▸';
@endphp
@foreach (config('dokumen.pokja') as $bab => $namaBab)
    @continue(empty($p['pokja'][$bab]))
    <tr wire:key="akr-{{ $bab }}" class="bg-surface-soft dark:bg-gray-800/60">
        <td colspan="7" class="!py-2">
            <button type="button" wire:click="toggleAkr('{{ $bab }}')" class="flex items-center w-full gap-2 text-left">
                <span class="w-5 text-brand-green dark:text-brand-lime">{{ $panah($bab) }}</span>
                <x-badge variant="brand">{{ $bab }}</x-badge>
                <span class="font-semibold text-ink dark:text-gray-100">{{ $namaBab }}</span>
                <span class="ml-auto text-xs text-muted dark:text-gray-400">{{ number_format($p['pokja'][$bab], 0, ',', '.') }} dokumen</span>
            </button>
        </td>
    </tr>
    @if (in_array($bab, $buka, true))
        @foreach ($p['standar'][$bab] ?? [] as $s)
            @php $kStd = "{$bab}|{$s->standar}"; @endphp
            <tr wire:key="akr-{{ $kStd }}">
                <td colspan="7" class="!py-1.5">
                    <button type="button" wire:click="toggleAkr('{{ $kStd }}')" class="flex items-center w-full gap-2 text-left" style="padding-left: 1.5rem">
                        <span class="w-5 text-brand-green dark:text-brand-lime">{{ $panah($kStd) }}</span>
                        <span class="font-medium ds-td-token">{{ $s->standar }}</span>
                        <span class="text-sm text-ink dark:text-gray-200">{{ $p['judulStandar'][$s->standar] ?? '' }}</span>
                        <span class="ml-auto text-xs text-muted dark:text-gray-400">{{ $s->n }} dokumen</span>
                    </button>
                </td>
            </tr>
            @if (in_array($kStd, $buka, true))
                @foreach ($p['ep'][$kStd] ?? [] as $e)
                    @php $kEp = "{$kStd}|{$e->ep}"; @endphp
                    <tr wire:key="akr-{{ $kEp }}">
                        <td colspan="7" class="!py-1.5">
                          <div class="flex items-center gap-2">
                            <button type="button" wire:click="toggleAkr('{{ $kEp }}')" class="flex items-center flex-1 min-w-0 gap-2 text-left" style="padding-left: 3rem">
                                <span class="w-5 text-brand-green dark:text-brand-lime">{{ $panah($kEp) }}</span>
                                <span class="text-sm font-medium text-ink dark:text-gray-200">EP {{ $e->ep }}</span>
                                @if ($e->elemen)
                                    <x-badge variant="gray">{{ $e->elemen }}</x-badge>
                                @endif
                                <span class="text-sm text-muted dark:text-gray-300">{{ $e->judul }}</span>
                                <span class="ml-auto text-xs text-muted dark:text-gray-400">{{ $e->n }} dokumen</span>
                            </button>
                            @if ($bolehUbah)
                                <button type="button" wire:click="tambahDiEp('{{ $kEp }}')" title="Tambah dokumen ke EP ini"
                                    class="px-2 py-0.5 text-xs font-medium rounded-lg text-brand-green bg-brand-green/10 hover:bg-brand-green hover:text-white dark:text-brand-lime dark:bg-brand-lime/10">+ Dokumen</button>
                            @endif
                          </div>
                        </td>
                    </tr>
                    @if (in_array($kEp, $buka, true))
                        @foreach ($p['dokumen'][$kEp] ?? [] as $row)
                            @include('pages.dokumen.registri.baris-registri', [
                                'row' => $row, 'kedalaman' => 3, 'turunan' => [], 'terbuka' => [], 'pohon' => false,
                                'bolehUbah' => $bolehUbah, 'kunci' => $kEp, 'tanpaInduk' => true,
                            ])
                        @endforeach
                    @endif
                @endforeach
            @endif
        @endforeach
    @endif
@endforeach

@if (($p['tanpaN'] ?? 0) > 0)
    <tr wire:key="akr-tanpa" class="bg-surface-soft dark:bg-gray-800/60">
        <td colspan="7" class="!py-2">
            <button type="button" wire:click="toggleAkr('_tanpa')" class="flex items-center w-full gap-2 text-left">
                <span class="w-5 text-brand-green dark:text-brand-lime">{{ $panah('_tanpa') }}</span>
                <span class="font-semibold text-muted dark:text-gray-300">Tanpa data akreditasi</span>
                <span class="ml-auto text-xs text-muted dark:text-gray-400">{{ $p['tanpaN'] }} dokumen</span>
            </button>
        </td>
    </tr>
    @foreach ($p['tanpa'] ?? [] as $row)
        @include('pages.dokumen.registri.baris-registri', [
            'row' => $row, 'kedalaman' => 1, 'turunan' => [], 'terbuka' => [], 'pohon' => false,
            'bolehUbah' => $bolehUbah, 'kunci' => '_tanpa',
        ])
    @endforeach
@endif

@if (empty($p['pokja']) && empty($p['tanpaN']))
    <tr>
        <td colspan="7" class="px-6 py-10 text-center text-muted dark:text-gray-400">Belum ada dokumen yang cocok.</td>
    </tr>
@endif
