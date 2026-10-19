@extends('layouts.admin')
@section('title', 'Data transaksi')
@section('page_heading', 'Data transaksi')
@section('content')
<div class="page-heading"><div><p class="eyebrow">OPERASIONAL TOKO</p><h1>Transaksi tertata, toko terpantau.</h1><p class="intro">Catat aktivitas baru dan telusuri riwayat transaksi toko.</p></div><span class="record-count">{{ number_format($transaksi->total(), 0, ',', '.') }} transaksi sesuai filter</span></div>
<details class="entry-panel" id="tambah" @if($errors->any()) open @endif><summary>Tambah transaksi <span aria-hidden="true">+</span></summary><form class="entry-form" action="{{ url('/admin/transaksi') }}" method="POST">@csrf
    <p class="form-intro">Isi transaksi baru untuk dicatat di aplikasi A.</p>
    <div class="form-grid"><div><label for="kode_transaksi">Kode transaksi</label><input id="kode_transaksi" name="kode_transaksi" value="{{ old('kode_transaksi') }}" maxlength="50" placeholder="Contoh: TRX-004" required @error('kode_transaksi') aria-invalid="true" aria-describedby="kode-error" @enderror>@error('kode_transaksi')<p class="field-error" id="kode-error">{{ $message }}</p>@enderror</div>
    <div><label for="total_nominal">Nominal (Rp)</label><input id="total_nominal" name="total_nominal" type="number" min="1" step="0.01" value="{{ old('total_nominal') }}" placeholder="150000.00" required @error('total_nominal') aria-invalid="true" aria-describedby="nominal-error" @enderror>@error('total_nominal')<p class="field-error" id="nominal-error">{{ $message }}</p>@enderror</div>
    <div><label for="status-baru">Status transaksi</label><select id="status-baru" name="status" required><option value="SUCCESS" @selected(old('status', 'SUCCESS') === 'SUCCESS')>Berhasil (SUCCESS)</option><option value="FAILED" @selected(old('status') === 'FAILED')>Gagal (FAILED)</option></select>@error('status')<p class="field-error">{{ $message }}</p>@enderror</div></div>
    <div class="form-actions"><button class="button button-primary" type="submit">Simpan transaksi</button><span>Data baru disimpan ke aplikasi A.</span></div>
</form></details>
<section class="ledger" aria-labelledby="transaction-list"><div class="section-heading"><div><h2 id="transaction-list">Riwayat transaksi</h2><p>Transaksi terbaru ditampilkan terlebih dahulu.</p></div></div>
<form class="filters" method="GET" action="{{ url('/admin/transaksi') }}"><div class="search-field"><label for="q">Cari kode transaksi</label><input id="q" name="q" type="search" maxlength="100" value="{{ request('q') }}" placeholder="Masukkan kode transaksi">@error('q')<p class="field-error">{{ $message }}</p>@enderror</div><div><label for="status-filter">Status</label><select id="status-filter" name="status"><option value="">Semua status</option><option value="SUCCESS" @selected(request('status') === 'SUCCESS')>Berhasil</option><option value="FAILED" @selected(request('status') === 'FAILED')>Gagal</option></select></div><button class="button button-primary" type="submit">Terapkan</button><a class="reset-link" href="{{ url('/admin/transaksi') }}">Reset</a></form>
@include('admin.partials.transactions', ['rows' => $transaksi])
@include('admin.partials.pagination', ['records' => $transaksi])
</section>
@endsection