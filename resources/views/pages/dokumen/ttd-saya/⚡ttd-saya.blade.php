<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\TtdUser;

/**
 * Spesimen tanda tangan pengguna — disimpan di storage/app/public/UserTtd/ dan dicatat di
 * users.myuser_ttd_image (format nama berkas saja, sama dengan "Kelola User" sirus; dibaca TtdUser).
 * Spesimen hanya dibubuhkan ke dokumen saat pemiliknya sendiri menekan "Tandatangani".
 */
new class extends Component {
    use WithFileUploads;

    public $unggahan = null;

    private function kode(): string
    {
        $u = auth()->user();

        return (string) ($u->myuser_code ?: $u->id);
    }

    public function getUrlProperty(): string
    {
        return TtdUser::urlDariKode($this->kode());
    }

    private function simpanBerkas(string $isiPng): void
    {
        $nama = $this->kode() . '-' . now()->format('YmdHis') . '.png';
        Storage::disk('public')->put(TtdUser::FOLDER . '/' . $nama, $isiPng);
        DB::table('users')->where('id', auth()->id())->update(['myuser_ttd_image' => $nama, 'myuser_code' => $this->kode()]);
        $this->dispatch('toast', type: 'success', message: 'Spesimen tanda tangan tersimpan.');
    }

    /** Dari signature pad (data URL PNG). */
    public function simpanGoresan(string $dataUrl): void
    {
        if (! preg_match('#^data:image/png;base64,(.+)$#', $dataUrl, $m)) {
            $this->dispatch('toast', type: 'error', message: 'Goresan tidak terbaca.');
            return;
        }
        $png = base64_decode($m[1], true);
        if (! $png || strlen($png) < 1500) {
            $this->dispatch('toast', type: 'error', message: 'Goresan terlalu sedikit — ulangi tanda tangan.');
            return;
        }
        $this->simpanBerkas($png);
    }

    public function simpanUnggahan(): void
    {
        $this->validate(['unggahan' => 'required|image|mimes:png,jpg,jpeg|max:2048'],
            ['unggahan.image' => 'Berkas harus gambar PNG/JPG (latar putih atau transparan).']);
        $img = imagecreatefromstring(file_get_contents($this->unggahan->getRealPath()));
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        $this->simpanBerkas($png);
        $this->unggahan = null;
    }

    public function hapus(): void
    {
        DB::table('users')->where('id', auth()->id())->update(['myuser_ttd_image' => null]);
        $this->dispatch('toast', type: 'success', message: 'Spesimen dihapus. Dokumen yang sudah ditandatangani tetap menyimpan rekam TTD-nya.');
    }
};
?>

<div>
    <x-page-title title="Tanda Tangan Saya" subtitle="Spesimen tanda tangan untuk menandatangani dokumen di registri" />

    <div class="max-w-3xl px-6 pt-4 pb-10 space-y-5">
        <x-border-form title="Spesimen saat ini">
            @if ($this->url)
                <div class="flex items-end gap-4">
                    <img src="{{ $this->url }}" alt="Spesimen TTD" class="h-32 p-2 bg-white border rounded-xl border-hairline">
                    <div class="text-sm text-muted">
                        {{ auth()->user()->name }} · kode {{ auth()->user()->myuser_code ?: auth()->id() }}<br>
                        Dibubuhkan hanya saat Anda sendiri menekan <b>Tandatangani</b> pada dokumen.
                        <div class="mt-2"><x-confirm-button variant="danger-soft" action="hapus" title="Hapus spesimen" message="Hapus spesimen tanda tangan Anda?">Hapus spesimen</x-confirm-button></div>
                    </div>
                </div>
            @else
                <p class="text-sm text-muted">Belum ada spesimen. Gores atau unggah di bawah.</p>
            @endif
        </x-border-form>

        <x-border-form title="Gores tanda tangan">
            <x-signature.signature-pad wireMethod="simpanGoresan" />
        </x-border-form>

        <x-border-form title="Atau unggah gambar (PNG/JPG, latar putih/transparan, maks. 2 MB)">
            <div class="flex items-end gap-2">
                <input type="file" wire:model="unggahan" accept="image/png,image/jpeg" class="text-sm" />
                <x-primary-button type="button" wire:click="simpanUnggahan" wire:loading.attr="disabled">Simpan</x-primary-button>
            </div>
            <x-input-error :messages="$errors->get('unggahan')" class="mt-1" />
        </x-border-form>
    </div>
</div>
