<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Internal - Toko A (KANMIS)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans text-gray-800">

    <div class="max-w-6xl mx-auto p-6 mt-8">
        <!-- Header -->
        <div class="bg-indigo-700 text-white p-6 rounded-t-lg shadow-lg flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">Dashboard Internal - Aplikasi Toko A (KANMIS)</h1>
                <p class="text-indigo-200 mt-2">Ini adalah layar admin pusat. Semua data rahasia terlihat di sini.</p>
            </div>
            <div class="bg-indigo-800 px-4 py-2 rounded shadow-inner border border-indigo-600">
                <span class="text-xs text-indigo-300 block uppercase tracking-wider">Status Server</span>
                <span class="font-bold text-green-400">● ONLINE (SQL Server)</span>
            </div>
        </div>

        <div class="bg-white p-8 rounded-b-lg shadow-lg">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Kiri: Data Rahasia (Pegawai) -->
                <div>
                    <div class="flex items-center gap-2 mb-4 border-b pb-2 border-gray-200">
                        <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <h2 class="text-xl font-bold text-gray-700">Data Pegawai <span class="text-red-500 text-sm bg-red-100 px-2 py-1 rounded ml-2">RAHASIA (Internal)</span></h2>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">Data ini HANYA bisa dilihat oleh admin Toko A. Tidak akan pernah dikirim ke API Gateway Aplikasi B.</p>
                    
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full bg-white">
                            <thead class="bg-red-50">
                                <tr>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-red-800 uppercase">ID</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-red-800 uppercase">Nama</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-red-800 uppercase">Jabatan</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-red-800 uppercase">Gaji (Rahasia)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($pegawai as $p)
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm text-gray-600">{{ $p->id }}</td>
                                    <td class="py-3 px-4 text-sm font-medium text-gray-800">{{ $p->nama_pegawai }}</td>
                                    <td class="py-3 px-4 text-sm text-gray-600">{{ $p->jabatan }}</td>
                                    <td class="py-3 px-4 text-sm font-bold text-red-600">Rp {{ number_format($p->gaji, 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Kanan: Data Transaksi (Bisa dishare via API) -->
                <div>
                    <div class="flex items-center gap-2 mb-4 border-b pb-2 border-gray-200">
                        <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <h2 class="text-xl font-bold text-gray-700">Data Transaksi <span class="text-green-600 text-sm bg-green-100 px-2 py-1 rounded ml-2">DIBAGIKAN KE API</span></h2>
                    </div>
                    <p class="text-sm text-gray-500 mb-4">Data ini yang disediakan (diekspos) melalui API Gateway untuk diambil oleh Aplikasi B (SI-ISPO).</p>
                    
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full bg-white">
                            <thead class="bg-green-50">
                                <tr>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-green-800 uppercase">Kode TRX</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-green-800 uppercase">Nominal</th>
                                    <th class="py-3 px-4 text-left text-xs font-semibold text-green-800 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($transaksi as $t)
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm font-mono text-indigo-600">{{ $t->kode_transaksi }}</td>
                                    <td class="py-3 px-4 text-sm font-semibold text-gray-800">Rp {{ number_format($t->total_nominal, 0, ',', '.') }}</td>
                                    <td class="py-3 px-4 text-sm">
                                        @if($t->status == 'SUCCESS')
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold">{{ $t->status }}</span>
                                        @else
                                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">{{ $t->status }}</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Footer / Keterangan PoC -->
            <div class="mt-10 bg-blue-50 border-l-4 border-blue-500 p-4 rounded text-blue-800">
                <h3 class="font-bold mb-1">💡 Penjelasan Demo untuk Mentor:</h3>
                <p class="text-sm">Buka halaman ini (<a href="http://localhost:8001/admin" class="underline font-bold">localhost:8001/admin</a>) di tab pertama sebagai representasi KANMIS. Lalu buka <a href="http://localhost:8001/demo.html" class="underline font-bold" target="_blank">localhost:8001/demo.html</a> di tab kedua sebagai SI-ISPO. Tunjukkan ke mentor bahwa SI-ISPO hanya bisa menarik tabel sebelah kanan (Transaksi), dan tidak akan pernah bisa menyentuh tabel sebelah kiri (Gaji Pegawai) karena API Gateway kita membatasinya!</p>
            </div>
        </div>
    </div>
</body>
</html>
