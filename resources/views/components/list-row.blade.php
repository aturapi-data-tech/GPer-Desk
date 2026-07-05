@props([
    'title' => '',
    'meta'  => null,      // baris kedua (sub-teks)
    'href'  => null,      // jika diisi → baris jadi link + chevron
])

{{--
    Baris daftar ala referensi: ikon depan → judul/meta → trailing (badge) → chevron.
    Slot `leading` & `trailing` opsional.

    Contoh:
        <x-list-row title="Tiket #TKT-001" meta="AC bocor · Ruang IT" :href="route(...)">
            <x-slot:leading><svg ...></svg></x-slot:leading>
            <x-slot:trailing><x-color-pill :color="$c" dot>Open</x-color-pill></x-slot:trailing>
        </x-list-row>
--}}
@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'group flex items-center gap-3 rounded-xl border border-hairline bg-canvas px-4 py-3 shadow-sm transition hover:shadow-md hover:border-brand-green/40 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-brand-lime/40']) }}>

    @isset($leading)
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-surface-soft text-brand-green dark:bg-gray-700 dark:text-brand-lime">
            {{ $leading }}
        </span>
    @endisset

    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold text-ink dark:text-gray-100">{{ $title }}</p>
        @if ($meta)
            <p class="truncate text-xs text-muted dark:text-gray-400">{{ $meta }}</p>
        @endif
        {{ $slot }}
    </div>

    @isset($trailing)
        <div class="shrink-0">{{ $trailing }}</div>
    @endisset

    @if ($href)
        <svg class="h-4 w-4 shrink-0 text-muted-soft transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
        </svg>
    @endif
</{{ $tag }}>
