<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Toko ABC')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 text-gray-800 font-sans">

    <div class="min-h-screen flex">

        <!-- SIDEBAR -->
        <aside class="w-64 bg-indigo-950 text-white flex flex-col min-h-screen">

            <div class="px-6 py-6 border-b border-indigo-800">
                <h1 class="text-2xl font-bold">TOKO ABC</h1>
                <p class="text-indigo-300 text-sm mt-1">
                    Sistem Informasi Toko ABC
                </p>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2">

                <p class="px-3 mb-3 text-xs uppercase tracking-widest text-indigo-300">
                    Menu Utama
                </p>

                <a href="{{ url('/admin') }}"
                   class="block px-3 py-3 rounded-lg hover:bg-indigo-800">
                    ▦ &nbsp; Dashboard
                </a>

                <a href="{{ url('/admin/pegawai') }}"
                   class="block px-3 py-3 rounded-lg hover:bg-indigo-800">
                    ♙ &nbsp; Data Pegawai
                </a>

                <a href="{{ url('/admin/transaksi') }}"
                   class="block px-3 py-3 rounded-lg hover:bg-indigo-800">
                    ▤ &nbsp; Data Transaksi
                </a>

               {{-- <p class="px-3 pt-6 mb-3 text-xs uppercase tracking-widest text-indigo-300">
                    Integrasi
                </p>

                <a href="{{ url('/demo.html') }}"
                   target="_blank"
                   class="block px-3 py-3 rounded-lg bg-indigo-800 hover:bg-indigo-700">
                    ↗ &nbsp; Demo SI-ISPO
                </a> --}}

            </nav>

            <div class="border-t border-indigo-800 px-6 py-4">
                <p class="text-sm font-semibold">Toko A</p>
                <p class="text-xs text-indigo-300 mt-1">
                    Admin Panel
                </p>
            </div>

        </aside>

        <!-- KONTEN UTAMA -->
        <div class="flex-1 min-w-0">

            <header class="bg-white border-b border-gray-200 px-8 py-5">
                <h2 class="text-xl font-semibold">
                    @yield('page_heading', 'Dashboard')
                </h2>
            </header>

            <main class="p-8">
                @if(session('success'))
                    <div class="mb-4 rounded-lg bg-green-100 text-green-800 p-3">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 rounded-lg bg-red-100 text-red-800 p-3">
                        <ul class="list-disc pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>

    </div>

</body>
</html>