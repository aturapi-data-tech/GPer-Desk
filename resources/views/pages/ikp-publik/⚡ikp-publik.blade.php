<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Services\PatientSafetyIncidentService;

new #[Layout('layouts.public-form')] class extends Component {
    public string $incident_type    = '';
    public string $title            = '';
    public string $chronology       = '';
    public string $patient_impact   = '';
    public string $immediate_action = '';
    public string $location         = '';
    public string $incident_date    = '';
    public string $incident_time    = '';
    public string $patient_ref      = '';
    public bool   $is_anonymous     = false;
    public string $reporter_name    = '';
    public string $reporter_role    = '';
    public string $reporter_contact = '';

    public ?string $successNo = null;

    public function submit(): void
    {
        $this->validate(
            [
                'incident_type'    => 'required|in:' . implode(',', PatientSafetyIncidentService::TYPES),
                'title'            => 'required|string|max:255',
                'chronology'       => 'required|string|max:5000',
                'location'         => 'required|string|max:255',
                'incident_date'    => 'required|date|before_or_equal:today',
                'incident_time'    => 'required|string|max:5',
                'immediate_action' => 'required|string|max:5000',
                // Dampak wajib hanya bila pasien benar-benar cedera (KTD/Sentinel).
                'patient_impact'   => 'required_if:incident_type,ktd,sentinel|nullable|string|max:5000',
                'patient_ref'      => 'nullable|string|max:100',
                'is_anonymous'     => 'boolean',
                // Identitas pelapor wajib bila tidak anonim.
                'reporter_name'    => 'required_unless:is_anonymous,true|nullable|string|max:150',
                'reporter_role'    => 'required_unless:is_anonymous,true|nullable|string|max:100',
                'reporter_contact' => 'nullable|string|max:150',
            ],
            [
                'incident_type.required'    => 'Jenis insiden wajib dipilih.',
                'title.required'            => 'Judul laporan wajib diisi.',
                'chronology.required'       => 'Kronologi kejadian wajib diisi.',
                'location.required'         => 'Lokasi / unit kejadian wajib diisi.',
                'incident_date.required'    => 'Tanggal kejadian wajib diisi.',
                'incident_date.before_or_equal' => 'Tanggal kejadian tidak boleh melebihi hari ini.',
                'incident_time.required'    => 'Jam kejadian wajib diisi.',
                'immediate_action.required' => 'Tindakan segera yang dilakukan wajib diisi.',
                'patient_impact.required_if' => 'Dampak pada pasien wajib diisi untuk insiden KTD/Sentinel.',
                'reporter_name.required_unless' => 'Nama pelapor wajib diisi (atau aktifkan mode anonim).',
                'reporter_role.required_unless' => 'Profesi / jabatan pelapor wajib diisi (atau aktifkan mode anonim).',
            ],
        );

        $res = PatientSafetyIncidentService::create([
            'incident_type'    => $this->incident_type,
            'title'            => $this->title,
            'chronology'       => $this->chronology,
            'patient_impact'   => $this->patient_impact,
            'immediate_action' => $this->immediate_action,
            'location'         => $this->location,
            'incident_date'    => $this->incident_date,
            'incident_time'    => $this->incident_time,
            'patient_ref'      => $this->patient_ref,
            'is_anonymous'     => $this->is_anonymous,
            'reporter_name'    => $this->reporter_name,
            'reporter_role'    => $this->reporter_role,
            'reporter_contact' => $this->reporter_contact,
        ], 'web');

        $this->successNo = $res['report_no'];
        $this->reset([
            'incident_type', 'title', 'chronology', 'patient_impact', 'immediate_action',
            'location', 'incident_date', 'incident_time', 'patient_ref',
            'reporter_name', 'reporter_role', 'reporter_contact',
        ]);
        $this->is_anonymous = false;
    }

    public function laporLagi(): void
    {
        $this->successNo = null;
        $this->resetValidation();
    }
};
?>

@php
    $types = [
        'kpcs'     => 'KPCS — Kondisi Potensial Cedera Signifikan (belum terjadi insiden)',
        'knc'      => 'KNC — Kejadian Nyaris Cedera (nyaris kena pasien)',
        'ktc'      => 'KTC — Kejadian Tidak Cedera (kena pasien, tak cedera)',
        'ktd'      => 'KTD — Kejadian Tidak Diharapkan (pasien cedera)',
        'sentinel' => 'Kejadian Sentinel (kematian / cedera berat / permanen)',
    ];
@endphp

<div class="w-full max-w-2xl mx-auto">

        <div class="flex items-start gap-3 mb-6">
            <div class="flex items-center justify-center shrink-0 w-12 h-12 rounded-2xl bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" /></svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold leading-tight text-ink dark:text-gray-100">Pelaporan Insiden Keselamatan Pasien</h1>
                <p class="mt-1 text-sm leading-relaxed text-muted dark:text-gray-400">
                    Laporkan setiap <strong>insiden keselamatan pasien</strong> (KPCS, KNC, KTC, KTD, atau Sentinel) untuk ditindaklanjuti tim mutu.
                    Pelaporan bersifat <strong class="text-brand-green dark:text-brand-lime">no blame</strong> — tujuannya pembelajaran &amp; perbaikan sistem, bukan menyalahkan.
                </p>
            </div>
        </div>

        @if ($successNo)
            <div class="p-8 text-center bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-full bg-success-tint text-success-deep dark:bg-green-900/30 dark:text-green-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h2 class="text-lg font-bold text-ink dark:text-gray-100">Laporan insiden terkirim &amp; tercatat.</h2>
                <p class="mt-1 text-sm text-muted dark:text-gray-400">Nomor laporan (simpan untuk penelusuran tindak lanjut):</p>
                <p class="my-4 font-mono text-2xl font-bold tracking-wide text-brand-green dark:text-brand-lime">{{ $successNo }}</p>
                <x-primary-button type="button" wire:click="laporLagi">Laporkan insiden lain</x-primary-button>
            </div>
        @else
            <form wire:submit="submit" class="p-6 space-y-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div>
                    <x-input-label value="Jenis Insiden *" />
                    <x-select-input wire:model="incident_type" class="w-full mt-1" :error="$errors->has('incident_type')">
                        <option value="">— pilih jenis insiden —</option>
                        @foreach ($types as $val => $lbl)
                            <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </x-select-input>
                    <x-input-error :messages="$errors->get('incident_type')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Judul / Ringkasan Insiden *" />
                    <x-text-input wire:model="title" maxlength="255" placeholder="mis. Pasien nyaris menerima obat salah dosis" :error="$errors->has('title')" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Kronologi Kejadian *" />
                    <textarea wire:model="chronology" rows="4" maxlength="5000"
                        class="w-full mt-1 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                        placeholder="Uraikan kronologi: apa yang terjadi, urutan kejadian, siapa yang terlibat..."></textarea>
                    <x-input-error :messages="$errors->get('chronology')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label value="Dampak pada Pasien" :required="in_array($incident_type, ['ktd', 'sentinel'], true)" />
                        <textarea wire:model="patient_impact" rows="2" maxlength="5000"
                            class="w-full mt-1 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                            placeholder="Akibat yang dialami pasien (wajib bila KTD/Sentinel)..."></textarea>
                        <x-input-error :messages="$errors->get('patient_impact')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Tindakan Segera yang Dilakukan *" />
                        <textarea wire:model="immediate_action" rows="2" maxlength="5000"
                            class="w-full mt-1 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                            placeholder="Penanganan yang sudah dilakukan setelah kejadian..."></textarea>
                        <x-input-error :messages="$errors->get('immediate_action')" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label value="Lokasi / Unit *" />
                        <x-text-input wire:model="location" maxlength="255" placeholder="mis. Ruang Rawat Inap Lt.3" :error="$errors->has('location')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('location')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Kejadian *" />
                        <x-text-input wire:model="incident_date" type="date" :error="$errors->has('incident_date')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('incident_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Jam Kejadian *" />
                        <x-text-input wire:model="incident_time" type="time" :error="$errors->has('incident_time')" class="w-full mt-1" />
                        <x-input-error :messages="$errors->get('incident_time')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label value="No. RM / Inisial Pasien (opsional)" />
                    <x-text-input wire:model="patient_ref" maxlength="100" placeholder="mis. 00-12-34 / Tn. A" class="w-full mt-1" />
                    <p class="mt-1 text-xs text-muted-soft">Identitas pasien dijaga kerahasiaannya — hanya untuk penelusuran tim mutu.</p>
                </div>

                <div class="p-3 border rounded-xl border-hairline bg-surface-soft/50 dark:border-gray-700 dark:bg-gray-900/40">
                    <x-toggle wire:model="is_anonymous" :trueValue="true" :falseValue="false"
                        label="Laporkan secara anonim (identitas pelapor tidak disimpan)" />

                    @if ($is_anonymous)
                        <p class="mt-2 text-xs text-muted dark:text-gray-400">Identitas pelapor tidak akan dicatat. Matikan bila ingin dihubungi untuk klarifikasi.</p>
                    @else
                        <div class="grid grid-cols-1 gap-4 mt-3 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Nama Pelapor *" />
                                <x-text-input wire:model="reporter_name" maxlength="150" placeholder="Nama Anda" :error="$errors->has('reporter_name')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('reporter_name')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Profesi / Jabatan *" />
                                <x-text-input wire:model="reporter_role" maxlength="100" placeholder="mis. Perawat / Apoteker" :error="$errors->has('reporter_role')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('reporter_role')" class="mt-1" />
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

        <p class="mt-4 text-xs text-center text-muted-soft">GPER-Desk · Keselamatan Pasien (IKP) · budaya <em>no blame</em></p>
    </div>
