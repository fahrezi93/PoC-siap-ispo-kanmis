<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiGatewayController extends Controller
{
    public function index(Request $request)
    {
        // Menerima query parameter tanggal (YYYY-MM-DD), default: hari ini
        $tanggal = $request->query('tanggal', date('Y-m-d'));

        // Lakukan query ke database db_toko_a, HANYA membaca tabel db_transaksi
        // Filter status 'SUCCESS' pada tanggal yang dipilih
        $transaksis = DB::table('db_transaksi')
            ->where('status', 'SUCCESS')
            ->whereDate('created_at', $tanggal)
            ->select('kode_transaksi', 'total_nominal', 'status', 'created_at as waktu_transaksi')
            ->get();

        // Hitung total_records dan total_omset
        $totalRecords = $transaksis->count();
        $totalOmset = (float) $transaksis->sum('total_nominal');

        // Kembalikan respons JSON berstruktur rapi
        return response()->json([
            'status' => 'success',
            'meta' => [
                'tanggal_filter' => $tanggal,
                'total_records' => $totalRecords,
                'total_omset' => $totalOmset
            ],
            'data' => $transaksis
        ]);
    }

    public function pegawai()
    {
        // Mengambil data pegawai dari SQL Server db_toko_a
        $pegawai = DB::table('db_pegawai')
            ->select('id', 'nama_pegawai', 'jabatan', 'gaji')
            ->get();

        return response()->json([
            'status' => 'success',
            'meta' => [
                'total_records' => $pegawai->count()
            ],
            'data' => $pegawai
        ]);
    }
}
