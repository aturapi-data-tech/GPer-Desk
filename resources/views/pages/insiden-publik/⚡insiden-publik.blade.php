<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Services\IncidentReportService;

new #[Layout('layouts.public-form')] class extends Component {
    public string $category         = '';
    public string $title            = '';
    public string $description      = '';
    public string $location         = '';
    public string $incident_date    = '';
    public bool   $is_anonymous     = true;
    public string $reporter_name    = '';
    public string $reporter_contact = '';

    public ?string $successNo = null;

    public function submit(): void
    {
        $this->validate(
            [
                'category'         => 'required|in:' . implode(',', IncidentReportService::CATEGORIES),
                'title'            => 'required|string|max:255',
                'description'      => 'required|string|max:5000',
                'location'         => 'nullable|string|max:255',
                'incident_date'    => 'nullable|date',
                'is_anonymous'     => 'boolean',
                'reporter_name'    => 'nullable|string|max:150',
                'reporter_contact' => 'nullable|string|max:150',
            ],
            [
                'category.required'    => 'Kategori wajib dipilih.',
                'title.required'       => 'Judul laporan wajib diisi.',
                'description.required' => 'Uraian kejadian wajib diisi.',
            ],
        );

        $res = IncidentReportService::create([
            'category'         => $this->category,
            'title'            => $this->title,
            'description'      => $this->description,
            'location'         => $this->location,
            'incident_date'    => $this->incident_date,
            'is_anonymous'     => $this->is_anonymous,
            'reporter_name'    => $this->reporter_name,
            'reporter_contact' => $this->reporter_contact,
        ], 'web');

        $this->successNo = $res['report_no'];
        $this->reset(['category', 'title', 'description', 'location', 'incident_date', 'reporter_name', 'reporter_contact']);
        $this->is_anonymous = true;
    }

    public function laporLagi(): void
    {
        $this->successNo = null;
        $this->resetValidation();
    }
};
?>

@php
    $cats = [
        'etika'      => 'Etika & Perilaku',
        'k3'         => 'Keselamatan Kerja (K3)',
        'kecurangan' => 'Kecurangan / Gratifikasi',
        'layanan'    => 'Keluhan / Saran Layanan',
        'lainnya'    => 'Lainnya',
    ];
@endphp

<div class="w-full max-w-2xl mx-auto">

        <div class="flex items-start gap-3 mb-6">
            <div class="flex items-center justify-center shrink-0 w-12 h-12 rounded-2xl bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold leading-tight text-ink dark:text-gray-100">Pengaduan &amp; Aspirasi Karyawan</h1>
                <p class="mt-1 text-sm leading-relaxed text-muted dark:text-gray-400">
                    Sampaikan <strong>pengaduan, keluhan, atau aspirasi</strong> terkait etika &amp; perilaku, keselamatan kerja, kecurangan, atau layanan.
                    Bisa <strong class="text-brand-green dark:text-brand-lime">anonim</strong> — identitas dirahasiakan &amp; ditindaklanjuti secara rahasia.
                </p>
            </div>
        </div>

        @if ($successNo)
            <div class="p-8 text-center bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-full bg-success-tint text-success-deep dark:bg-green-900/30 dark:text-green-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h2 class="text-lg font-bold text-ink dark:text-gray-100">Pengaduan Anda terkirim &amp; tercatat.</h2>
                <p class="mt-1 text-sm text-muted dark:text-gray-400">Nomor referensi (simpan untuk keperluan rahasia):</p>
                <p class="my-4 font-mono text-2xl font-bold tracking-wide text-brand-green dark:text-brand-lime">{{ $successNo }}</p>
                <x-primary-button type="button" wire:click="laporLagi">Kirim pengaduan lain</x-primary-button>
            </div>
        @else
            <form wire:submit="submit" class="p-6 space-y-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div>
                    <x-input-label value="Kategori *" />
                    <x-select-input wire:model="category" class="w-full mt-1" :error="$errors->has('category')">
                        <option value="">— pilih kategori —</option>
                        @foreach ($cats as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('category')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Judul / Ringkasan *" />
                    <x-text-input wire:model="title" maxlength="255" placeholder="mis. Ketidaknyamanan di unit X" :error="$errors->has('title')" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Uraian Kejadian *" />
                    <textarea wire:model="description" rows="4" maxlength="5000"
                        class="w-full mt-1 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                        placeholder="Ceritakan kronologi: apa, siapa yang terlibat, kapan, di mana..."></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Lokasi Kejadian" />
                        <x-text-input wire:model="location" maxlength="255" placeholder="mis. Ruang IGD" class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Kejadian" />
                        <x-text-input wire:model="incident_date" type="date" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('incident_date')" class="mt-1" />
                    </div>
                </div>

                <div class="p-3 border rounded-xl border-hairline bg-surface-soft/50 dark:border-gray-700 dark:bg-gray-900/40">
                    <x-toggle wire:model="is_anonymous" :trueValue="true" :falseValue="false"
                        label="Laporkan secara anonim (identitas tidak disimpan)" />

                    @if ($is_anonymous)
                        <p class="mt-2 text-xs text-muted dark:text-gray-400">Identitas Anda tidak akan dicatat. Matikan bila ingin mencantumkan nama.</p>
                    @else
                        <div class="grid grid-cols-1 gap-4 mt-3 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Nama Pelapor" />
                                <x-text-input wire:model="reporter_name" maxlength="150" placeholder="Nama Anda" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Kontak (opsional)" />
                                <x-text-input wire:model="reporter_contact" maxlength="150" placeholder="No. HP / email" class="w-full mt-1" />
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end">
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit">Kirim Laporan</span>
                        <span wire:loading wire:target="submit">Mengirim...</span>
                    </x-primary-button>
                </div>
            </form>
        @endif

        <p class="mt-4 text-xs text-center text-muted-soft">GPER-Desk · Pengaduan &amp; Aspirasi (rahasia)</p>
    </div>

