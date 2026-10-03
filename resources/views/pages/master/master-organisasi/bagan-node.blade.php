{{-- Satu simpul bagan organisasi (rekursif). Variabel: $node {id, nama, jenis, kepala, anak[]}, $warna (kode jenis => warna CSS),
     $boleh (boleh ubah). Klik kotak = ubah unit; tombol + (muncul saat kursor di atas kotak) = tambah unit di bawahnya.
     Anak yang semuanya tanpa bawahan ditumpuk vertikal (seperti bagan Lampiran I); selain itu menyebar mendatar.
     Partial tanpa ⚡ — bukan komponen. --}}
@php
    $tumpuk = $node['anak'] && collect($node['anak'])->every(fn ($a) => empty($a['anak']));
@endphp
<li>
    @include('pages.master.master-organisasi.bagan-kotak', ['u' => $node, 'warna' => $warna, 'boleh' => $boleh])
    @if ($tumpuk)
        <div class="org-tumpuk">
            @foreach ($node['anak'] as $a)
                @include('pages.master.master-organisasi.bagan-kotak', ['u' => $a, 'warna' => $warna, 'boleh' => $boleh])
            @endforeach
        </div>
    @elseif ($node['anak'])
        <ul>
            @foreach ($node['anak'] as $a)
                @include('pages.master.master-organisasi.bagan-node', ['node' => $a, 'warna' => $warna, 'boleh' => $boleh])
            @endforeach
        </ul>
    @endif
</li>
