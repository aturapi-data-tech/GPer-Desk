@props([
    'title'     => null,
    'subtitle'  => null,
    'bodyClass' => 'p-5',   // set 'p-0' bila isi berupa daftar edge-to-edge
])

{{--
    Kartu section putih rounded dengan header opsional (judul/subjudul + aksi).
    Slot `actions` opsional di kanan header.

    Contoh:
        <x-section-card title="Tiket menunggu respon" subtitle="5 tiket">
            <x-slot:actions><x-outline-button>Lihat semua</x-outline-button></x-slot:actions>
            ...isi...
        </x-section-card>
--}}
<div {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-2xl border border-hairline bg-canvas shadow-sm dark:border-gray-700 dark:bg-gray-800']) }}>

    @if ($title || $subtitle || isset($actions))
        <div class="flex items-center justify-between gap-3 border-b border-hairline px-5 py-4 dark:border-gray-700">
            <div class="min-w-0">
                @if ($title)
                    <h3 class="text-sm font-semibold text-ink dark:text-gray-100">{{ $title }}</h3>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-muted dark:text-gray-400">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $bodyClass }}">
        {{ $slot }}
    </div>
</div>
