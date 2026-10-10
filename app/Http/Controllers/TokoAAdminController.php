<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TokoAAdminController extends Controller
{
    public function index()
    {
        // Sebagai pemilik Toko A (KANMIS), kita bisa melihat semua data, termasuk yang rahasia
        $pegawai = DB::table('db_pegawai')->get();
        $transaksi = DB::table('db_transaksi')->orderBy('created_at', 'desc')->get();

        return view('admin', compact('pegawai', 'transaksi'));
    }
}
