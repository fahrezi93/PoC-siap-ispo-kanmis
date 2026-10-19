<div class="table-scroll" role="region" aria-label="Tabel transaksi" tabindex="0">
    <table>
        <caption class="sr-only">Transaksi toko pada Aplikasi A</caption>
        <thead><tr><th scope="col">Kode transaksi</th><th scope="col">Waktu transaksi</th><th scope="col">Status</th><th scope="col" class="align-right">Nominal</th></tr></thead>
        <tbody>
        @forelse($rows as $item)
            <tr>
                <th scope="row" class="transaction-code">{{ $item->kode_transaksi }}</th>
                <td>{{ \Carbon\Carbon::parse($item->created_at)->format('d M Y · H:i') }}</td>
                <td><span class="badge {{ $item->status === 'SUCCESS' ? 'badge-success' : 'badge-neutral' }}">{{ $item->status === 'SUCCESS' ? 'Berhasil' : ($item->status === 'FAILED' ? 'Gagal' : ($item->status ?? 'Tanpa status')) }}</span></td>
                <td class="align-right nominal"><x-rupiah :value="$item->total_nominal" /></td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty-state"><span class="empty-symbol" aria-hidden="true">≡</span><h3>Belum ada transaksi yang cocok</h3><p>Tambah transaksi baru atau coba pencarian lainnya.</p><a href="{{ url('/admin/transaksi') }}#tambah">Tambah transaksi →</a></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
