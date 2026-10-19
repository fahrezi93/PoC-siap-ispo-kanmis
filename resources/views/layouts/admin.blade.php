<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Aplikasi A</title>
    <link rel="stylesheet" href="{{ asset('css/admin-a.css') }}">
    <script src="{{ asset('js/admin-a.js') }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Lewati ke konten</a>
<div class="workspace">
    <aside class="sidebar" aria-label="Navigasi aplikasi A">
        <a class="brand" href="{{ url('/admin') }}"><span class="brand-mark" aria-hidden="true">A</span><span>Toko ABC<span class="brand-sub">APLIKASI A</span></span></a>
        <div class="nav-label">Ruang kerja</div>
        <nav class="admin-nav">
            <a href="{{ url('/admin') }}" @class(['nav-active' => request()->is('admin')]) @if(request()->is('admin')) aria-current="page" @endif>Ringkasan toko</a>
            <a href="{{ url('/admin/transaksi') }}" @class(['nav-active' => request()->is('admin/transaksi')]) @if(request()->is('admin/transaksi')) aria-current="page" @endif>Data transaksi</a>
            <a href="{{ url('/admin/pegawai') }}" @class(['nav-active' => request()->is('admin/pegawai')]) @if(request()->is('admin/pegawai')) aria-current="page" @endif>Data pegawai</a>
        </nav>
        <div class="sidebar-note"><span class="small-label">SUMBER DATA UTAMA</span><strong>Aplikasi A → B → C</strong><p>Kelola aktivitas toko dan data internal dari satu tempat.</p><span class="readonly">Admin toko</span><a class="app-switch" href="{{ config('services.finance_c.url') }}">Buka Aplikasi C →</a></div>
    </aside>
    <div class="main-area">
        <header class="topbar"><span>Toko ABC <span aria-hidden="true">/</span> <strong>@yield('page_heading', 'Ringkasan toko')</strong></span><span class="app-label">APLIKASI A</span></header>
        <main id="main">
            @if(session('success'))<div class="notice notice-success" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="notice" role="alert"><strong>Periksa kembali data yang diisi.</strong><p>Lengkapi atau perbaiki kolom yang ditandai di bawah.</p></div>@endif
            @yield('content')
            <footer class="page-footer"><span>Aplikasi A · Admin toko</span><span>Sumber data utama · Toko ABC</span></footer>
        </main>
    </div>
</div>
</body>
</html>
