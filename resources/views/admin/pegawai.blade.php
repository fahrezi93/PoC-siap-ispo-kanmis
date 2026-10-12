@extends('layouts.admin')

@section('title', 'Data Pegawai - KANMIS')
@section('page_heading', 'Data Pegawai')
@section('breadcrumb', 'Data Pegawai')

@section('content')

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            Data Pegawai
        </h1>
        <p class="text-gray-500 mt-2">
            Informasi pegawai internal Toko ABC.
        </p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">

        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold">
                Daftar Pegawai
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-gray-600 text-sm">
                    <tr>
                        <th class="px-6 py-4">No.</th>
                        <th class="px-6 py-4">Nama Pegawai</th>
                        <th class="px-6 py-4">Jabatan</th>
                        <th class="px-6 py-4">Gaji</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100">
                    @forelse($pegawai as $index => $item)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                {{ $index + 1 }}
                            </td>
                            <td class="px-6 py-4 font-medium">
                                {{ $item->nama_pegawai }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $item->jabatan }}
                            </td>
                            <td class="px-6 py-4">
                                Rp {{ number_format($item->gaji, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4"
                                class="px-6 py-8 text-center text-gray-500">
                                Belum ada data pegawai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 text-sm text-gray-500 border-t border-gray-200">
            Data pegawai bersifat internal dan tidak ditampilkan melalui API Gateway Transaksi.
        </div>

    </div>

@endsection