@props([
    'row',                          // baris hasil query (stdClass): ticket_no, title, status, status_color,
                                    // priority, priority_color, category, location, technician, reported_at,
                                    // age_label, overdue, is_closed, comment_count, id
    'editable'      => true,        // tampilkan tombol edit + hapus
    'showLocation'  => false,       // tampilkan lokasi (tampilan teknisi)
    'lockedNote'    => 'terkunci (diproses)',
    'deleteTitle'   => 'Hapus Tiket',
    'deleteMessage' => null,        // default: "Yakin hapus tiket <no>?"
])

{{--
    Kartu tiket bergaya "tiket" (accent bar prioritas · header no+status ·
    judul+chip · journey row Dilapor⇢Umur/SLA · meta lokasi/teknisi ·
    footer aksi bertakik). Dipakai di halaman "Tiket Saya" & "Kelola Tiket".

    Aksi (wire:click) menargetkan komponen Livewire induk:
    openRespond(id) · openEdit(id) · requestDelete(id).
--}}
@php
    $deleteMessage ??= 'Yakin hapus tiket ' . $row->ticket_no . '?';
@endphp

<div {{ $attributes->merge(['class' => 'group relative flex flex-col overflow-hidden bg-canvas border border-hairline rounded-2xl shadow-sm transition duration-200 hover:shadow-lg hover:-translate-y-0.5 dark:bg-gray-800 dark:border-gray-700']) }}>

    {{-- accent bar prioritas --}}
    <span class="absolute inset-y-0 left-0 w-1.5" style="background: {{ $row->priority_color ?: '#cbd5e1' }}"></span>

    {{-- header: chip (kiri) · status + no. tiket (kanan) --}}
    <div class="flex items-start justify-between gap-3 pt-4 pl-6 pr-4">
        <div class="flex flex-wrap items-center gap-1.5 min-w-0">
            @if (!empty($row->sector ?? null))
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-sm font-bold bg-brand-green/10 text-brand-green ring-1 ring-brand-green/25 dark:bg-brand-lime/15 dark:text-brand-lime dark:ring-brand-lime/25">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                    {{ $row->sector }}
                </span>
            @endif
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-surface-soft text-muted dark:bg-gray-700 dark:text-gray-300">{{ $row->category ?: 'Tanpa kategori' }}</span>
            @if ($row->priority)
                <x-color-pill :color="$row->priority_color" size="sm">{{ $row->priority }}</x-color-pill>
            @endif
        </div>
        <div class="flex items-center gap-2 shrink-0">
            @if ($row->status)
                <x-color-pill :color="$row->status_color" dot>{{ $row->status }}</x-color-pill>
            @endif
            <span class="ds-td-token text-caption tracking-wide">{{ $row->ticket_no }}</span>
        </div>
    </div>

    {{-- judul --}}
    <div class="pt-2 pl-6 pr-4">
        <h3 class="text-base font-semibold leading-snug text-ink line-clamp-2 dark:text-gray-100">{{ $row->title }}</h3>
    </div>

    {{-- journey row: Dilapor ✈ Umur/SLA --}}
    <div class="mx-4 mt-3 rounded-xl bg-surface-soft/70 px-4 py-3 dark:bg-gray-900/40">
        <div class="flex items-center gap-2">
            <div class="text-left">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted dark:text-gray-400">Dilapor</p>
                <p class="text-sm font-bold text-ink dark:text-gray-100">{{ $row->reported_at ?: '—' }}</p>
            </div>
            <div class="flex flex-1 items-center">
                <span class="h-px flex-1 border-t border-dashed border-hairline dark:border-gray-600"></span>
                <span class="mx-1 grid h-7 w-7 place-items-center rounded-full bg-canvas border border-hairline text-brand-green dark:bg-gray-800 dark:border-gray-600 dark:text-brand-lime">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26" /></svg>
                </span>
                <span class="h-px flex-1 border-t border-dashed border-hairline dark:border-gray-600"></span>
            </div>
            <div class="text-right">
                <p title="Lama sejak dilapor · merah = lewat batas waktu (SLA)" class="text-[11px] font-semibold uppercase tracking-wide text-muted dark:text-gray-400">Waktu Berjalan</p>
                @if ($row->overdue)
                    <p class="text-sm font-bold text-error-deep dark:text-red-300">⚠ {{ $row->age_label }}</p>
                @else
                    <p class="text-sm font-bold {{ $row->is_closed ? 'text-emerald-600 dark:text-emerald-400' : 'text-ink dark:text-gray-100' }}">{{ $row->age_label }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- meta: (lokasi) + teknisi --}}
    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-3 pl-6 pr-4 text-[13px] font-medium text-body dark:text-gray-300">
        @if ($showLocation)
            <span class="inline-flex items-center gap-1.5" @if(!empty($row->location_detail ?? null)) title="{{ $row->location_detail }}" @endif>
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                <span class="truncate">{{ $row->location ?: '—' }}@if (!empty($row->location_detail ?? null)) <span class="text-muted">· {{ $row->location_detail }}</span>@endif</span>
            </span>
        @endif
        @if ($row->technician)
            <span class="inline-flex items-center gap-1.5">
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                <span class="truncate">{{ $row->technician }}</span>
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-bold text-warning-deep bg-warning-tint ring-1 ring-warning/30 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-500/30">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                Belum ditugaskan
            </span>
        @endif
    </div>

    {{-- footer aksi (dengan takik tiket) --}}
    <div class="relative mt-3 flex items-center justify-between gap-2 border-t border-dashed border-hairline px-4 py-3 dark:border-gray-700">
        <span class="absolute -left-2 -top-2 h-4 w-4 rounded-full bg-surface-soft dark:bg-gray-900"></span>
        <span class="absolute -right-2 -top-2 h-4 w-4 rounded-full bg-surface-soft dark:bg-gray-900"></span>
        <div class="flex items-center gap-2">
            @if ($editable)
                <x-action-edit wire:click="openEdit({{ $row->id }})" />
                <x-action-delete :action="'requestDelete(' . $row->id . ')'"
                    :title="$deleteTitle" :message="$deleteMessage" />
            @else
                <span class="text-[11px] italic text-muted-soft">{{ $lockedNote }}</span>
            @endif
        </div>
        <x-outline-button type="button" wire:click="openRespond({{ $row->id }})">
            Respon
            @if ($row->comment_count > 0)
                <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-green text-white dark:bg-brand-lime dark:text-gray-900">{{ $row->comment_count }}</span>
            @endif
        </x-outline-button>
    </div>
</div>
