@extends('layouts.admin')

@section('title', 'Data Transaksi - KANMIS')
@section('page_heading', 'Data Transaksi')
@section('breadcrumb', 'Data Transaksi')

@section('content')

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            Data Transaksi
        </h1>
        <p class="text-gray-500 mt-2">
            Kelola transaksi untuk pengujian API Gateway.
        </p>
    </div>

    <!-- FORM TAMBAH TRANSAKSI -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">

        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold">
                Tambah Transaksi
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                Data akan disimpan ke database SQL Server.
            </p>
        </div>

        <form action="{{ url('/admin/transaksi') }}" method="POST"
              class="p-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

                <div>
                    <label class="block text-sm font-medium mb-2">
                        Kode Transaksi
                    </label>
                    <input type="text" name="kode_transaksi"
                           value="{{ old('kode_transaksi') }}"
                           placeholder="Contoh: TRX-004"
                           required
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">
                        Total Nominal (Rp)
                    </label>
                    <input type="number" name="total_nominal"
                           value="{{ old('total_nominal') }}"
                           min="1" step="1" required
                           placeholder="Contoh: 150000"
                           class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-2">
                        Status Transaksi
                    </label>
                    <select name="status" required
                            class="w-full border border-gray-300 rounded-lg px-4 py-3">
                        <option value="SUCCESS">SUCCESS</option>
                        <option value="FAILED">FAILED</option>
                    </select>
                </div>

            </div>

            <button type="submit"
                    class="mt-5 bg-indigo-700 text-white px-5 py-3 rounded-lg hover:bg-indigo-800">
                + Simpan Transaksi
            </button>
        </form>

    </div>

    <!-- TABEL TRANSAKSI -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">

        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold">
                Riwayat Transaksi
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-gray-600 text-sm">
                    <tr>
                        <th class="px-6 py-4">No.</th>
                        <th class="px-6 py-4">Kode Transaksi</th>
                        <th class="px-6 py-4">Total Nominal</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Waktu Transaksi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse($transaksi as $index => $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                {{ $index + 1 }}
                            </td>
                            <td class="px-6 py-4 font-medium">
                                {{ $item->kode_transaksi }}
                            </td>
                            <td class="px-6 py-4">
                                Rp {{ number_format($item->total_nominal, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4">
                                @if($item->status === 'SUCCESS')
                                    <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm">
                                        SUCCESS
                                    </span>
                                @else
                                    <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm">
                                        FAILED
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                {{ $item->created_at }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"
                                class="px-6 py-8 text-center text-gray-500">
                                Belum ada transaksi.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

@endsection