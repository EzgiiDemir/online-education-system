<!doctype html>
<html lang="{{ session('locale','tr') }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('page-title', session('locale', 'tr') === 'en' ? 'Instructor workspace' : 'Eğitmen çalışma alanı') · Arucad Campus LMS</title><link rel="stylesheet" href="{{ asset('css/lms.css') }}"><link rel="stylesheet" href="{{ asset('css/instructor.css') }}"><link rel="stylesheet" href="{{ asset('css/instructor-forms.css') }}">
<script>(function(){try{var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.dataset.theme='dark';}catch(e){}})();</script>
</head>
<body class="instructor-page">
@php($en = session('locale', 'tr') === 'en')
<div class="instructor-shell">
<aside class="instructor-sidebar"><a class="brand sidebar-brand" href="{{ route('instructor.dashboard') }}">Arucad Campus <span>LMS</span></a><div class="sidebar-profile"><div class="sidebar-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</div><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'admin' ? ($en ? 'Admin' : 'Yönetici') : ($en ? 'Instructor' : 'Eğitmen') }}</small></div></div><div class="sidebar-label">{{ $en ? 'WORKSPACE' : 'ÇALIŞMA ALANI' }}</div><nav class="sidebar-nav"><a href="{{ route('instructor.dashboard') }}" class="{{ request()->routeIs('instructor.dashboard') ? 'is-active' : '' }}"><span>⌂</span> {{ $en ? 'Overview' : 'Genel bakış' }}</a><a href="{{ route('instructor.dashboard') }}#courses"><span>▦</span> {{ $en ? 'My Courses' : 'Eğitimlerim' }}</a><a href="{{ route('instructor.profile') }}" class="{{ request()->routeIs('instructor.profile') ? 'is-active' : '' }}"><span>◎</span> {{ $en ? 'My Profile' : 'Profilim' }}</a><a href="{{ route('account.show') }}" class="{{ request()->routeIs('account.show') ? 'is-active' : '' }}"><span>⚿</span> {{ $en ? 'Account' : 'Hesap bilgileri' }}</a></nav><div class="sidebar-label">{{ $en ? 'SHORTCUTS' : 'KISAYOLLAR' }}</div><nav class="sidebar-nav"><a href="{{ route('lms.home') }}"><span>↗</span> {{ $en ? 'View portal' : 'Portalı görüntüle' }}</a></nav><form class="sidebar-logout" method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button-danger"><span>⏻</span> {{ $en ? 'Log out' : 'Çıkış yap' }}</button></form></aside>
<main class="instructor-main"><header class="instructor-header"><div>@unless(request()->routeIs('instructor.dashboard'))<button type="button" class="back-link" onclick="history.length > 1 ? history.back() : (window.location = '{{ route('instructor.dashboard') }}')">← {{ $en ? 'Back' : 'Geri' }}</button>@endunless<span class="eyebrow">INSTRUCTOR WORKSPACE</span><h1>@yield('page-title', $en ? 'Instructor workspace' : 'Eğitmen çalışma alanı')</h1></div>
<div class="profile-menu">
<button class="profile-trigger" type="button" data-profile-toggle>
<span class="profile-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span>
<span class="profile-name">{{ auth()->user()->name }}</span>
</button>
<div class="profile-dropdown" data-profile-dropdown>
<div class="dropdown-head"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></div>
<a href="{{ route('instructor.profile') }}">◎ {{ $en ? 'Profile' : 'Profilim' }}</a>
<button type="button" data-theme-toggle>◐ {{ $en ? 'Theme' : 'Tema' }}</button>
<a href="{{ route('language.switch', $en ? 'tr' : 'en') }}">文 {{ $en ? 'Türkçe' : 'English' }}</a>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button-danger">⏻ {{ $en ? 'Log out' : 'Çıkış yap' }}</button></form>
</div>
</div>
</header>@if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif @yield('content')</main>
</div>
<script>
document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-profile-toggle]');
    var dropdown = document.querySelector('[data-profile-dropdown]');
    if (dropdown) {
        if (toggle) { dropdown.classList.toggle('is-open'); return; }
        if (!e.target.closest('[data-profile-dropdown]')) dropdown.classList.remove('is-open');
    }
    if (e.target.closest('[data-theme-toggle]')) {
        var isDark = document.documentElement.dataset.theme === 'dark';
        if (isDark) { delete document.documentElement.dataset.theme; } else { document.documentElement.dataset.theme = 'dark'; }
        try { localStorage.setItem('theme', isDark ? 'light' : 'dark'); } catch (err) {}
    }
});
</script>
</body></html>
