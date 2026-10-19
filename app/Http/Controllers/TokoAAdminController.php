<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TokoAAdminController extends Controller
{
    public function dashboard(): View
    {
        $jumlahTransaksi = DB::table('db_transaksi')->count();

        $jumlahTransaksiBerhasil = DB::table('db_transaksi')->where('status', 'SUCCESS')->count();

        $totalNominalBerhasil = DB::table('db_transaksi')
            ->where('status', 'SUCCESS')
            ->sum('total_nominal');

        $transaksiTerbaru = DB::table('db_transaksi')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('admin.dashboard', compact(
            'jumlahTransaksi',
            'jumlahTransaksiBerhasil',
            'totalNominalBerhasil',
            'transaksiTerbaru'
        ));
    }

    public function pegawai(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $pegawai = DB::table('db_pegawai')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = '%'.mb_strtolower(trim($request->string('q')->toString())).'%';
                $query->where(function ($query) use ($search): void {
                    $query->whereRaw('LOWER(nama_pegawai) LIKE ?', [$search])->orWhereRaw('LOWER(jabatan) LIKE ?', [$search]);
                });
            })
            ->orderBy('id')
            ->paginate(25)->withQueryString();

        return view('admin.pegawai', compact('pegawai'));
    }

    public function simpanPegawai(Request $request)
    {
        $data = $request->validate([
            'nama_pegawai' => ['required', 'string', 'max:100'],
            'jabatan' => ['required', 'string', 'max:50'],
            'gaji' => ['required', 'numeric', 'min:0'],
        ]);

        DB::table('db_pegawai')->insert([
            'nama_pegawai' => $data['nama_pegawai'],
            'jabatan' => $data['jabatan'],
            'gaji' => $data['gaji'],
        ]);

        return redirect('/admin/pegawai')
            ->with('success', 'Data pegawai baru berhasil disimpan ke database SQL Server.');
    }

    public function transaksi(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['SUCCESS', 'FAILED'])]]);
        $transaksi = DB::table('db_transaksi')
            ->when($request->filled('q'), fn ($query) => $query->whereRaw('LOWER(kode_transaksi) LIKE ?', ['%'.mb_strtolower(trim($request->string('q')->toString())).'%']))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)->withQueryString();

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
