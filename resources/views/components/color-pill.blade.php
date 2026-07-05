@props([
    'color' => '#6b7280', // warna hex dari DB (status_color / priority_color)
    'dot'   => false,     // titik indikator di depan (dipakai utk status)
    'size'  => 'md',      // md = status pill (rounded-full) · sm = tag prioritas (rounded-md)
])

{{--
    Pill berwarna dinamis (hex) untuk status & prioritas tiket.
    Beda dari <x-badge> yang pakai varian semantik tetap — di sini warna
    datang dari kolom DB (RGB/hex), jadi diterapkan lewat inline style.

    Contoh:
        <x-color-pill :color="$row->status_color" dot>{{ $row->status }}</x-color-pill>
        <x-color-pill :color="$row->priority_color" size="sm">{{ $row->priority }}</x-color-pill>
--}}
@php
    $sizeCls = $size === 'sm'
        ? 'gap-1 px-2 py-0.5 rounded-md text-[11px]'
        : 'gap-1.5 px-2.5 py-1 rounded-full text-[11px] shadow-sm';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center font-semibold text-white $sizeCls"]) }}
      style="background: {{ $color }}">
    @if ($dot)
        <span class="w-1.5 h-1.5 rounded-full bg-white/80"></span>
    @endif
    {{ $slot }}
</span>
