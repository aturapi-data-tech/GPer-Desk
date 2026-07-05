@props([
    'label'  => '',           // keterangan bawah angka
    'value'  => '0',          // angka utama
    'accent' => '#157547',    // warna aksen (bar kiri + ikon) — default brand green
    'hint'   => null,         // baris kecil opsional (mis. "SLA terlampaui")
    'href'   => null,         // jika diisi → kartu jadi link
])

{{--
    KPI tile ala dashboard referensi: angka besar + label + ikon dalam
    lingkaran ter-tint + accent bar kiri.

    Slot `icon` opsional (SVG). Contoh:
        <x-stat-tile label="Tiket Open" value="12" accent="#d4a017">
            <x-slot:icon><svg ...></svg></x-slot:icon>
        </x-stat-tile>
--}}
@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'group relative flex items-center gap-3 overflow-hidden rounded-2xl border border-hairline bg-canvas p-4 shadow-sm transition dark:border-gray-700 dark:bg-gray-800' . ($href ? ' hover:shadow-md hover:-translate-y-0.5' : '')]) }}>

    <span class="absolute inset-y-0 left-0 w-1" style="background: {{ $accent }}"></span>

    @isset($icon)
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl"
              style="background: {{ $accent }}1a; color: {{ $accent }}">
            {{ $icon }}
        </span>
    @endisset

    <div class="min-w-0">
        <p class="text-2xl font-bold leading-none text-ink dark:text-gray-100">{{ $value }}</p>
        <p class="mt-1 truncate text-xs font-medium text-muted dark:text-gray-400">{{ $label }}</p>
        @if ($hint)
            <p class="mt-0.5 text-[11px] text-muted-soft">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
