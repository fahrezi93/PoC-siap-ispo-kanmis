@extends('layouts.admin')
@section('title', 'Data pegawai')
@section('page_heading', 'Data pegawai')
@section('content')
<div class="page-heading"><div><p class="eyebrow">TIM INTERNAL</p><h1>Kenali tim di balik toko.</h1><p class="intro">Kelola informasi pegawai, jabatan, dan gaji dalam aplikasi A.</p></div><span class="record-count">{{ number_format($pegawai->total(), 0, ',', '.') }} pegawai sesuai filter</span></div>
<details class="entry-panel" id="tambah" @if($errors->any()) open @endif><summary>Tambah pegawai <span aria-hidden="true">+</span></summary><form class="entry-form" action="{{ url('/admin/pegawai') }}" method="POST">@csrf
    <p class="form-intro">Lengkapi informasi pegawai yang akan ditambahkan.</p>
    <div class="form-grid"><div><label for="nama_pegawai">Nama pegawai</label><input id="nama_pegawai" name="nama_pegawai" value="{{ old('nama_pegawai') }}" maxlength="100" placeholder="Contoh: Ahmad Fauzi" required @error('nama_pegawai') aria-invalid="true" aria-describedby="nama-error" @enderror>@error('nama_pegawai')<p class="field-error" id="nama-error">{{ $message }}</p>@enderror</div>
    <div><label for="jabatan">Jabatan</label><input id="jabatan" name="jabatan" value="{{ old('jabatan') }}" maxlength="50" placeholder="Contoh: Kasir" required @error('jabatan') aria-invalid="true" aria-describedby="jabatan-error" @enderror>@error('jabatan')<p class="field-error" id="jabatan-error">{{ $message }}</p>@enderror</div>
    <div><label for="gaji">Gaji (Rp)</label><input id="gaji" name="gaji" type="number" min="0" step="0.01" value="{{ old('gaji') }}" placeholder="5000000.00" required @error('gaji') aria-invalid="true" aria-describedby="gaji-error" @enderror>@error('gaji')<p class="field-error" id="gaji-error">{{ $message }}</p>@enderror</div></div>
    <div class="form-actions"><button class="button button-primary" type="submit">Simpan pegawai</button><span>Data baru disimpan ke aplikasi A.</span></div>
</form></details>
<section class="ledger" aria-labelledby="employee-list"><div class="section-heading"><div><h2 id="employee-list">Daftar pegawai</h2><p>Informasi pegawai yang terdaftar di Toko ABC.</p></div></div>
<form class="filters" method="GET" action="{{ url('/admin/pegawai') }}"><div class="search-field"><label for="q">Cari nama atau jabatan</label><input id="q" name="q" type="search" maxlength="100" value="{{ request('q') }}" placeholder="Masukkan nama atau jabatan">@error('q')<p class="field-error">{{ $message }}</p>@enderror</div><button class="button button-primary" type="submit">Cari pegawai</button><a class="reset-link" href="{{ url('/admin/pegawai') }}">Reset</a></form>
<div class="table-scroll" role="region" aria-label="Tabel pegawai" tabindex="0"><table><caption class="sr-only">Data internal pegawai Toko ABC</caption><thead><tr><th scope="col">No.</th><th scope="col">Nama pegawai</th><th scope="col">Jabatan</th><th scope="col" class="align-right">Gaji</th></tr></thead><tbody>
@forelse($pegawai as $item)<tr><td>{{ $pegawai->firstItem() + $loop->index }}</td><th scope="row" class="transaction-code">{{ $item->nama_pegawai }}</th><td>{{ $item->jabatan }}</td><td class="align-right nominal"><x-rupiah :value="$item->gaji" /></td></tr>@empty<tr><td colspan="4" class="empty-state"><span class="empty-symbol" aria-hidden="true">≡</span><h3>Belum ada pegawai yang cocok</h3><p>Tambah pegawai baru atau coba pencarian lainnya.</p><a href="{{ url('/admin/pegawai') }}#tambah">Tambah pegawai →</a></td></tr>@endforelse
</tbody></table></div>
@include('admin.partials.pagination', ['records' => $pegawai])
</section>
@endsection