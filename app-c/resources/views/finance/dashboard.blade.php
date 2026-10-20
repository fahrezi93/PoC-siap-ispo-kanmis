<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaksi · Aplikasi C</title>
    <link rel="stylesheet" href="{{ asset('css/finance-c.css') }}">
</head>
<body>
<a class="skip-link" href="#main">Lewati ke transaksi</a>
<div class="workspace">
    <aside class="sidebar" aria-label="Navigasi aplikasi C">
        <a class="brand" href="{{ route('finance.dashboard') }}"><span class="brand-mark" aria-hidden="true">C</span><span>Keuangan<span class="brand-sub">APLIKASI C</span></span></a>
        <div class="nav-label">Ruang kerja</div>
        <nav><a class="nav-active" href="{{ route('finance.dashboard') }}" aria-current="page"><span aria-hidden="true">↗</span> Ringkasan transaksi</a></nav>
        <div class="sidebar-note"><span class="small-label">SUMBER DATA</span><strong>Aplikasi B → C</strong><p>Salinan transaksi untuk pemantauan keuangan.</p><span class="readonly">Akses baca saja</span></div>
    </aside>
    <div class="main-area">
        <header class="topbar"><span>Keuangan <span aria-hidden="true">/</span> <strong>Transaksi</strong></span><span class="app-label">APLIKASI C</span></header>
        <main id="main">
            <div class="page-heading"><div><p class="eyebrow">MONITOR KEUANGAN</p><h1>Transaksi, dalam satu pandangan.</h1><p class="intro">Tarik data dari Aplikasi B saat dibutuhkan, lalu pantau transaksi di sini.</p></div><form method="POST" action="{{ route('finance.sync') }}">@csrf<button type="submit" class="button button-primary">Tarik data dari B</button></form></div>
            @if(session('sync_success'))<div class="notice" role="status">{{ session('sync_success') }}</div>@endif
            @if(session('sync_error'))<div class="notice" role="alert">{{ session('sync_error') }}</div>@endif
            @if(session('access_success'))<div class="notice" role="status">{{ session('access_success') }}</div>@endif
            @if(session('access_error'))<div class="notice" role="alert">{{ session('access_error') }}</div>@endif

            <section class="sync-strip" aria-label="Status sinkronisasi">
                <div><span class="status-dot {{ $unavailable || !$lastSync || $stale ? 'status-muted' : '' }}" aria-hidden="true"></span><strong>{{ $unavailable ? 'Koneksi belum tersedia' : (!$lastSync ? 'Menunggu penarikan pertama' : ($stale ? 'Data terakhir tersimpan' : 'Data tersinkron')) }}</strong></div>
                <p>{{ $lastSync ? 'Terakhir ditarik '.$lastSync->format('d M Y, H:i').' · '.$timezone : 'Klik Tarik data dari B untuk mengambil data.' }}</p><span class="sync-frequency">Penarikan manual</span>
            </section>

            <section class="ledger data-access" aria-labelledby="access-title">
                <div class="section-heading">
                    <div><h2 id="access-title">Izin data dari B</h2><p>{{ $accessCheckedAt ? 'Terakhir diperiksa '.$accessCheckedAt.' · '.$timezone : 'Periksa izin untuk mengetahui data yang boleh ditarik.' }}</p></div>
                    <form method="POST" action="{{ route('finance.access') }}">@csrf<button class="button button-secondary" type="submit">Periksa izin dari B</button></form>
                </div>
                <dl class="access-list">
                    @foreach(['transaksi' => 'Transaksi', 'pegawai' => 'Pegawai & gaji'] as $resource => $label)
                        <div class="access-row">
                            <dt>{{ $label }}</dt>
                            <dd><span class="badge {{ $dataAccess === null ? 'badge-neutral' : ($dataAccess[$resource] ? 'badge-success' : 'badge-blocked') }}">{{ $dataAccess === null ? 'Belum diperiksa' : ($dataAccess[$resource] ? 'Diizinkan oleh B' : 'Diblokir oleh B') }}</span></dd>
                            <dd class="access-description">{{ $dataAccess === null ? 'Status belum diketahui.' : ($dataAccess[$resource] ? 'Tersedia dalam paket data dari B.' : 'Tidak tersedia untuk penarikan dari B.') }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="access-note">Izin mengikuti data yang tersedia pada snapshot B. Pemeriksaan ini tidak menarik atau menghapus data di C.</p>
            </section>

            @if($unavailable)
                <div class="notice" role="status"><strong>Data transaksi belum dapat dimuat.</strong><p>Koneksi database C belum siap atau tabel salinan belum tersedia. Coba muat ulang setelah koneksi disiapkan.</p></div>
            @endif

            <section class="stats" aria-label="Ringkasan sesuai filter">
                <div><span>Total transaksi</span><strong>{{ $unavailable ? '—' : number_format($summary['count'], 0, ',', '.') }}</strong><small>Sesuai filter yang dipilih</small></div>
                <div><span>Transaksi berhasil</span><strong>{{ $unavailable ? '—' : number_format($summary['success'], 0, ',', '.') }}</strong><small>Berstatus SUCCESS</small></div>
                <div class="stat-amount"><span>Nominal berhasil</span><strong>{{ $unavailable ? '—' : $summary['amount'] }}</strong><small>Akumulasi transaksi SUCCESS</small></div>
            </section>

            <section class="ledger" aria-labelledby="ledger-title">
                <div class="section-heading"><div><h2 id="ledger-title">Daftar transaksi</h2><p>Data terbaru ditampilkan terlebih dahulu.</p></div><span class="record-count">{{ number_format($transactions->total(), 0, ',', '.') }} data</span></div>
                <form class="filters" method="GET" action="{{ route('finance.dashboard') }}">
                    <div class="search-field"><label for="q">Cari transaksi</label><input id="q" name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Masukkan kode transaksi"></div>
                    <div><label for="status">Status</label><select id="status" name="status"><option value="">Semua status</option><option value="SUCCESS" @selected(($filters['status'] ?? '') === 'SUCCESS')>Berhasil</option><option value="FAILED" @selected(($filters['status'] ?? '') === 'FAILED')>Gagal</option></select></div>
                    <div><label for="from">Dari tanggal</label><input id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}"></div>
                    <div><label for="to">Sampai tanggal</label><input id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}"></div>
                    <button class="button button-primary" type="submit">Terapkan</button><a class="reset-link" href="{{ route('finance.dashboard') }}">Reset</a>
                </form>
                @if($errors->any())<div class="filter-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                <div class="table-scroll" role="region" aria-label="Tabel transaksi" tabindex="0">
                    <table><caption class="sr-only">Transaksi dari salinan Aplikasi B. Waktu ditampilkan dalam {{ $timezone }}.</caption><thead><tr><th scope="col">Kode transaksi</th><th scope="col">Waktu transaksi</th><th scope="col">Status</th><th scope="col" class="align-right">Nominal</th></tr></thead><tbody>
                    @forelse($transactions as $transaction)
                        <tr><th scope="row" class="transaction-code">{{ $transaction->kode_transaksi }}</th><td>{{ $transaction->date_display }}</td><td><span class="badge {{ $transaction->status === 'SUCCESS' ? 'badge-success' : 'badge-neutral' }}">{{ $transaction->status === 'SUCCESS' ? 'Berhasil' : ($transaction->status === 'FAILED' ? 'Gagal' : ($transaction->status ?? 'Tanpa status')) }}</span></td><td class="align-right nominal">{{ $transaction->nominal_display }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="empty-state"><span class="empty-symbol" aria-hidden="true">≡</span><h3>{{ $unavailable ? 'Menunggu koneksi database C' : ($state ? 'Belum ada transaksi yang cocok' : 'Belum ada data tersinkron') }}</h3><p>{{ $state ? 'Coba kata pencarian atau rentang tanggal lainnya.' : 'Transaksi akan tampil di sini setelah data dari B berhasil diterima.' }}</p><a href="{{ route('finance.dashboard') }}">{{ $state ? 'Hapus filter' : 'Periksa kembali' }} <span aria-hidden="true">→</span></a></td></tr>
                    @endforelse
                    </tbody></table>
                </div>
                <div class="table-footer"><p>{{ $transactions->firstItem() ?? 0 }}–{{ $transactions->lastItem() ?? 0 }} dari {{ number_format($transactions->total(), 0, ',', '.') }} transaksi</p><nav class="pagination" aria-label="Halaman transaksi">@if($transactions->onFirstPage())<span aria-disabled="true">← Sebelumnya</span>@else<a rel="prev" href="{{ $transactions->previousPageUrl() }}">← Sebelumnya</a>@endif<span class="page-number">{{ $transactions->currentPage() }} / {{ $transactions->lastPage() }}</span>@if($transactions->hasMorePages())<a rel="next" href="{{ $transactions->nextPageUrl() }}">Berikutnya →</a>@else<span aria-disabled="true">Berikutnya →</span>@endif</nav></div>
            </section>
            <footer class="page-footer"><span>Aplikasi C · Monitor keuangan</span><span>Waktu transaksi: {{ $timezone }}</span></footer>
        </main>
    </div>
</div>
</body>
</html>
