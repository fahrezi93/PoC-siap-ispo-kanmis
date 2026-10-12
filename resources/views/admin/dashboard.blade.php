 @extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            Dashboard
        </h1>
        <p class="text-gray-500 mt-2">
            Sistem Informasi Toko ABC
        </p>
    </div>

    {{-- Kartu Statistik --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        {{-- Jumlah Pegawai --}}
        <div class="bg-white rounded-xl shadow p-6">
            <p class="text-gray-500 text-sm">Data Pegawai</p>

            <h2 class="text-3xl font-bold text-indigo-700 mt-2">
                {{ number_format($jumlahPegawai, 0, ',', '.') }}
                <span class="text-base font-medium">Pegawai</span>
            </h2>

            <p class="text-gray-500 text-sm mt-3">
                Total pegawai yang terdaftar.
            </p>

            <a href="{{ url('/admin/pegawai') }}"
               class="inline-block mt-4 text-indigo-700 font-semibold">
                Lihat Data Pegawai →
            </a>
        </div>

        {{-- Jumlah Transaksi --}}
        <div class="bg-white rounded-xl shadow p-6">
            <p class="text-gray-500 text-sm">Data Transaksi</p>

            <h2 class="text-3xl font-bold text-indigo-700 mt-2">
                {{ number_format($jumlahTransaksi, 0, ',', '.') }}
                <span class="text-base font-medium">Transaksi</span>
            </h2>

            <p class="text-gray-500 text-sm mt-3">
                Total seluruh transaksi yang tercatat.
            </p>

            <a href="{{ url('/admin/transaksi') }}"
               class="inline-block mt-4 text-indigo-700 font-semibold">
                Lihat Transaksi →
            </a>
        </div>

        {{-- Total Nominal Transaksi --}}
        <div class="bg-white rounded-xl shadow p-6">
            <p class="text-gray-500 text-sm">Total Nominal Transaksi</p>

            <h2 class="text-3xl font-bold text-green-700 mt-2">
                Rp {{ number_format($totalNominalTransaksi, 0, ',', '.') }}
            </h2>

            <p class="text-gray-500 text-sm mt-3">
                Akumulasi nominal seluruh transaksi.
            </p>

            <a href="{{ url('/admin/transaksi') }}"
               class="inline-block mt-4 text-indigo-700 font-semibold">
                Lihat Detail →
            </a>
        </div>

    </div>

    {{-- Lima Transaksi Terbesar Bulan Ini --}}
    <div class="mt-8 bg-white rounded-xl shadow p-6">
        <div class="mb-5">
            <h2 class="text-xl font-bold text-gray-800">
                5 Transaksi Terbesar Bulan Ini
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                            No.
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                            Kode Transaksi
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                            Tanggal Transaksi
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">
                            Status
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">
                            Nominal
                        </th>
                    </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($transaksiTerbesarBulanIni as $transaksi)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ $loop->iteration }}
                            </td>

                            <td class="px-4 py-3 text-sm font-medium text-gray-800">
                                {{ $transaksi->kode_transaksi }}
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ \Carbon\Carbon::parse($transaksi->created_at)->format('d-m-Y H:i') }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                @if ($transaksi->status === 'SUCCESS')
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                        SUCCESS
                                    </span>
                                @else
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                        {{ $transaksi->status }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-right font-semibold text-gray-800">
                                Rp {{ number_format((float) $transaksi->total_nominal, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                Belum ada transaksi pada bulan ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection