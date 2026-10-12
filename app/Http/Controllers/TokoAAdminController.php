<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TokoAAdminController extends Controller
{
    public function dashboard()
    {
        $jumlahPegawai = DB::table('db_pegawai')->count();

        $jumlahTransaksi = DB::table('db_transaksi')->count();

        $totalNominalTransaksi = DB::table('db_transaksi')
            ->sum('total_nominal');

        $transaksiTerbesarBulanIni = DB::table('db_transaksi')
            ->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ])
            ->orderByDesc('total_nominal')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'jumlahPegawai',
            'jumlahTransaksi',
            'totalNominalTransaksi',
            'transaksiTerbesarBulanIni'
        ));
    }

    public function pegawai()
    {
        $pegawai = DB::table('db_pegawai')
            ->orderBy('id')
            ->get();

        return view('admin.pegawai', compact('pegawai'));
    }

    public function transaksi()
    {
        $transaksi = DB::table('db_transaksi')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.transaksi', compact('transaksi'));
    }

    public function simpanTransaksi(Request $request)
    {
        $data = $request->validate([
            'kode_transaksi' => [
                'required',
                'string',
                'max:50',
                Rule::unique('db_transaksi', 'kode_transaksi'),
            ],
            'total_nominal' => [
                'required',
                'numeric',
                'min:1',
            ],
            'status' => [
                'required',
                Rule::in(['SUCCESS', 'FAILED']),
            ],
        ]);

        DB::table('db_transaksi')->insert([
            'kode_transaksi' => $data['kode_transaksi'],
            'total_nominal' => $data['total_nominal'],
            'status' => $data['status'],
            'created_at' => now(),
        ]);

        return redirect('/admin/transaksi')
            ->with('success', 'Transaksi berhasil disimpan.');
    }
}