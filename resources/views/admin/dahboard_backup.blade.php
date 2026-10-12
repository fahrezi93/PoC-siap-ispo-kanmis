@extends('layouts.admin')

@section('title', 'Dashboard - KANMIS')

@section('content')

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-800">
            Dashboard
        </h1>
        <p class="text-gray-500 mt-2">
            Sistem Informasi Toko A — KANMIS
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <div class="bg-white rounded-xl shadow p-6">
            <p class="text-gray-500 text-sm">Data Pegawai</p>
            <h2 class="text-3xl font-bold text-indigo-700 mt-2">
                Data Internal
            </h2>
            <p class="text-gray-500 text-sm mt-3">
                Informasi pegawai dan gaji.
            </p>
            <a href="{{ url('/admin/pegawai') }}"
               class="inline-block mt-4 text-indigo-700 font-semibold">
                Lihat Data Pegawai →
            </a>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <p class="text-gray-500 text-sm">Data Transaksi</p>
            <h2 class="text-3xl font-bold text-indigo-700 mt-2">
                Data Toko
            </h2>
            <p class="text-gray-500 text-sm mt-3">
                Informasi transaksi Toko A.
            </p>
            <a href="{{ url('/admin/transaksi') }}"
               class="inline-block mt-4 text-indigo-700 font-semibold">
                Lihat Transaksi →
            </a>
        </div>

        <div class="bg-white rounded-xl shadow p-6">
            <p class="text-gray-500 text-sm">API Gateway</p>
            <h2 class="text-3xl font-bold text-green-700 mt-2">
                PoC
            </h2>
            <p class="text-gray-500 text-sm mt-3">
                Pengujian akses data untuk SI-ISPO.
            </p>
            <a href="{{ url('/demo.html') }}"
               class="inline-block mt-4 text-indigo-700 font-semibold">
                Buka Demo API →
            </a>
        </div>

    </div>

    <div class="mt-8 bg-white rounded-xl shadow p-6">
        <h2 class="text-xl font-bold mb-3">
            Tentang Sistem
        </h2>
        <p class="text-gray-600 leading-7">
            KANMIS merupakan aplikasi internal Toko A. Data pegawai
            dan gaji hanya ditampilkan pada halaman internal, sedangkan
            SI-ISPO mengakses data transaksi melalui API Gateway
            menggunakan API Key.
        </p>
    </div>

@endsection