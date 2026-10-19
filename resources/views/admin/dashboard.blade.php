@extends('layouts.admin')
@section('title', 'Ringkasan toko')
@section('page_heading', 'Ringkasan toko')
@section('content')
<div class="page-heading"><div><p class="eyebrow">PUSAT AKTIVITAS TOKO</p><h1>Semua aktivitas, satu ringkasan.</h1><p class="intro">Kelola transaksi, pantau nominal, dan lihat data pegawai Toko ABC.</p></div><a class="button button-primary" href="{{ url('/admin/transaksi') }}#tambah">+ Tambah transaksi</a></div>
<section class="sync-strip" aria-label="Peran aplikasi"><div><span class="status-dot" aria-hidden="true"></span><strong>Sumber data utama</strong></div><p>Data toko dikelola di A, lalu disalin ke B untuk diteruskan ke C.</p><span class="sync-frequency">Admin toko</span></section>
<section class="stats" aria-label="Ringkasan toko">
    <div><span>Total transaksi</span><strong>{{ number_format($jumlahTransaksi, 0, ',', '.') }}</strong><small><a href="{{ url('/admin/transaksi') }}">Lihat semua transaksi →</a></small></div>
    <div><span>Transaksi berhasil</span><strong>{{ number_format($jumlahTransaksiBerhasil, 0, ',', '.') }}</strong><small>Berstatus SUCCESS</small></div>
    <div class="stat-amount"><span>Nominal berhasil</span><strong><x-rupiah :value="$totalNominalBerhasil" /></strong><small>Akumulasi transaksi SUCCESS</small></div>
</section>
<section class="ledger" aria-labelledby="latest-transactions"><div class="section-heading"><div><h2 id="latest-transactions">Daftar transaksi</h2><p>Data terbaru ditampilkan terlebih dahulu.</p></div><a class="reset-link" href="{{ url('/admin/transaksi') }}">Cari dan filter →</a></div>@include('admin.partials.transactions', ['rows' => $transaksiTerbaru])@include('admin.partials.pagination', ['records' => $transaksiTerbaru])</section>
<div class="quick-links"><a href="{{ url('/admin/transaksi') }}"><span class="small-label">OPERASIONAL TOKO</span><h2>Catat transaksi baru</h2><p>Simpan kode, nominal, dan status transaksi toko.</p><span class="quick-arrow" aria-hidden="true">→</span></a><a href="{{ url('/admin/pegawai') }}"><span class="small-label">TIM INTERNAL</span><h2>Kelola data pegawai</h2><p>Tambahkan pegawai, jabatan, dan informasi gaji.</p><span class="quick-arrow" aria-hidden="true">→</span></a></div>
@endsection
