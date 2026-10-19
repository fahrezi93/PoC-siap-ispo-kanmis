<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaksi · Aplikasi B</title>
    <link rel="stylesheet" href="{{ asset('css/finance-b.css') }}">
</head>
<body>
<a class="skip-link" href="#main">Lewati ke transaksi</a>
<div class="workspace">
    <aside class="sidebar" aria-label="Navigasi aplikasi B">
        <a class="brand" href="{{ route('provider.dashboard') }}"><span class="brand-mark" aria-hidden="true">B</span><span>Keuangan<span class="brand-sub">APLIKASI B</span></span></a>
        <div class="nav-label">Ruang kerja</div>
        <nav><a class="nav-active" href="{{ route('provider.dashboard') }}" aria-current="page"><span aria-hidden="true">↗</span> Ringkasan transaksi</a></nav>
        <div class="sidebar-note"><span class="small-label">SUMBER DATA</span><strong>Aplikasi B → C</strong><p>Database salinan A dan penyedia API untuk C.</p><span class="readonly">Akses baca saja</span></div>
    </aside>
    <div class="main-area">
        <header class="topbar"><span>Keuangan <span aria-hidden="true">/</span> <strong>Transaksi</strong></span><span class="app-label">APLIKASI B</span></header>
        <main id="main">
            <div class="page-heading"><div><p class="eyebrow">PUSAT DATA KEUANGAN</p><h1>Data toko, siap diteruskan.</h1><p class="intro">Pantau hasil clone A dan kesiapan paket data untuk Aplikasi C.</p></div><a class="button button-secondary" href="{{ request()->fullUrl() }}">Muat ulang <span aria-hidden="true">↻</span></a></div>

            <section class="sync-strip" aria-label="Status sinkronisasi">
                <div><span class="status-dot {{ $unavailable || !$lastSync || $stale ? 'status-muted' : '' }}" aria-hidden="true"></span><strong>{{ $unavailable ? 'Koneksi belum tersedia' : (!$lastSync ? 'Snapshot belum diterbitkan' : ($stale ? 'Snapshot perlu diperbarui' : 'Snapshot siap untuk C')) }}</strong></div>
                <p>{{ $lastSync ? 'Snapshot diterbitkan '.$lastSync->format('d M Y, H:i').' · '.$timezone : 'Snapshot disiapkan setelah proses clone A selesai.' }}</p><span class="sync-frequency">Clone A → B setiap jam</span>
            </section>

            @if($unavailable)
                <div class="notice" role="status"><strong>Data hasil clone belum dapat dimuat.</strong><p>Koneksi database B belum siap atau clone A belum tersedia. Coba muat ulang setelah clone selesai.</p></div>
            @endif

            <section class="delivery-note" aria-label="Tujuan data">
                <div><strong>Aplikasi B</strong><span>MSSQL · hasil clone A</span></div><span aria-hidden="true">→</span>
                <div><strong>API Gateway</strong><span>Paket data terlindungi API key</span></div><span aria-hidden="true">→</span>
                <div><strong>Aplikasi C</strong><span>PostgreSQL · dashboard transaksi</span></div>
            </section>
            <section class="stats" aria-label="Ringkasan sesuai filter">
                <div><span>Total transaksi</span><strong>{{ $unavailable ? '—' : number_format($summary['count'], 0, ',', '.') }}</strong><small>Sesuai filter yang dipilih</small></div>
                <div><span>Transaksi berhasil</span><strong>{{ $unavailable ? '—' : number_format($summary['success'], 0, ',', '.') }}</strong><small>Berstatus SUCCESS</small></div>
                <div class="stat-amount"><span>Nominal berhasil</span><strong>{{ $unavailable ? '—' : $summary['amount'] }}</strong><small>Akumulasi transaksi SUCCESS</small></div>
            </section>

            <section class="ledger" aria-labelledby="ledger-title">
                <div class="section-heading"><div><h2 id="ledger-title">Daftar transaksi</h2><p>Data terbaru ditampilkan terlebih dahulu.</p></div><span class="record-count">{{ number_format($transactions->total(), 0, ',', '.') }} data</span></div>
                <form class="filters" method="GET" action="{{ route('provider.dashboard') }}">
                    <div class="search-field"><label for="q">Cari transaksi</label><input id="q" name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Masukkan kode transaksi"></div>
                    <div><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option><option value="SUCCESS" @selected(($filters['status'] ?? '') === 'SUCCESS')>Berhasil</option><option value="FAILED" @selected(($filters['status'] ?? '') === 'FAILED')>Gagal</option></select></div>
                    <div><label for="from">Dari tanggal</label><input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}"></div>
                    <div><label for="to">Sampai tanggal</label><input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}"></div>
                    <button class="button button-primary" type="submit">Terapkan</button><a class="reset-link" href="{{ route('provider.dashboard') }}">Reset</a>
                </form>
                @if($errors->any())<div class="filter-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                <div class="table-scroll" role="region" aria-label="Tabel transaksi" tabindex="0">
                    <table><caption class="sr-only">Transaksi pada database B hasil clone A. Waktu ditampilkan dalam {{ $timezone }}.</caption><thead><tr><th scope="col">Kode transaksi</th><th scope="col">Waktu transaksi</th><th scope="col">Status</th><th scope="col" class="align-right">Nominal</th></tr></thead><tbody>
                    @forelse($transactions as $transaction)
                        <tr><th scope="row" class="transaction-code">{{ $transaction->kode_transaksi }}</th><td>{{ $transaction->date_display }}</td><td><span class="badge {{ $transaction->status === 'SUCCESS' ? 'badge-success' : 'badge-neutral' }}">{{ $transaction->status === 'SUCCESS' ? 'Berhasil' : ($transaction->status === 'FAILED' ? 'Gagal' : ($transaction->status ?? 'Tanpa status')) }}</span></td><td class="align-right nominal">{{ $transaction->nominal_display }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="empty-state"><span class="empty-symbol" aria-hidden="true">≡</span><h3>{{ $unavailable ? 'Menunggu database hasil clone' : ($state ? 'Belum ada transaksi yang cocok' : 'Belum ada transaksi') }}</h3><p>{{ $state ? 'Coba kata pencarian atau rentang tanggal lainnya.' : 'Transaksi akan tampil setelah database A berhasil di-clone ke B.' }}</p><a href="{{ route('provider.dashboard') }}">{{ $state ? 'Hapus filter' : 'Periksa kembali' }} <span aria-hidden="true">→</span></a></td></tr>
                    @endforelse
                    </tbody></table>
                </div>
                <div class="table-footer"><p>{{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }} dari {{ number_format($transactions->total(), 0, ',', '.') }} transaksi</p><nav class="pagination" aria-label="Halaman transaksi">@if($transactions->onFirstPage())<span aria-disabled="true">← Sebelumnya</span>@else<a rel="prev" href="{{ $transactions->previousPageUrl() }}">← Sebelumnya</a>@endif<span class="page-number">{{ $transactions->currentPage() }} / {{ $transactions->lastPage() }}</span>@if($transactions->hasMorePages())<a rel="next" href="{{ $transactions->nextPageUrl() }}">Berikutnya →</a>@else<span aria-disabled="true">Berikutnya →</span>@endif</nav></div>
            </section>
            <footer class="page-footer"><span>Aplikasi B · Penyedia data keuangan</span><span>Waktu transaksi: {{ $timezone }}</span></footer>
        </main>
    </div>
</div>
</body>
</html>
