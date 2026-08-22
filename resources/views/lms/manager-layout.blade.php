<!doctype html>
<html lang="{{ session('locale','tr') }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('page-title', session('locale', 'tr') === 'en' ? 'Manager workspace' : 'Yönetici çalışma alanı') · Arucad Campus LMS</title><link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.custom.min.css') }}"><link rel="stylesheet" href="{{ asset('css/lms.css') }}"><link rel="stylesheet" href="{{ asset('css/instructor.css') }}"><link rel="stylesheet" href="{{ asset('css/instructor-forms.css') }}">
<script>(function(){try{var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.dataset.theme='dark';}catch(e){}})();</script>
</head>
<body class="instructor-page">
@php($en = session('locale', 'tr') === 'en')
<div class="instructor-shell">
<aside class="instructor-sidebar"><a class="brand sidebar-brand" href="{{ route('manager.dashboard') }}">Arucad Campus <span>LMS</span></a><div class="sidebar-profile"><div class="sidebar-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</div><div><strong>{{ auth()->user()->name }}</strong><small>{{ $en ? 'Manager' : 'Yönetici' }}</small></div></div><div class="sidebar-label">{{ $en ? 'TEAM' : 'EKİP' }}</div><nav class="sidebar-nav"><a href="{{ route('manager.dashboard') }}" class="{{ request()->routeIs('manager.dashboard') ? 'is-active' : '' }}"><span><x-lucide name="house" size="16" /></span> {{ $en ? 'Team overview' : 'Ekip genel bakış' }}</a><a href="{{ route('account.show') }}" class="{{ request()->routeIs('account.show') ? 'is-active' : '' }}"><span><x-lucide name="shield-check" size="16" /></span> {{ $en ? 'Account' : 'Hesap bilgileri' }}</a></nav><div class="sidebar-label">{{ $en ? 'SHORTCUTS' : 'KISAYOLLAR' }}</div><nav class="sidebar-nav"><a href="{{ route('lms.home') }}"><span><x-lucide name="globe" size="16" /></span> {{ $en ? 'View portal' : 'Portalı görüntüle' }}</a></nav><form class="sidebar-logout" method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button-danger"><span><x-lucide name="log-out" size="16" /></span> {{ $en ? 'Log out' : 'Çıkış yap' }}</button></form></aside>
<main class="instructor-main"><header class="instructor-header"><div><span class="eyebrow">MANAGER WORKSPACE</span><h1>@yield('page-title', $en ? 'Manager workspace' : 'Yönetici çalışma alanı')</h1></div>
<div class="profile-menu">
<button class="profile-trigger" type="button" data-profile-toggle>
<span class="profile-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span>
<span class="profile-name">{{ auth()->user()->name }}</span>
</button>
<div class="profile-dropdown" data-profile-dropdown>
<div class="dropdown-head"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></div>
<a href="{{ route('account.show') }}"><x-lucide name="circle-user" size="16" /> {{ $en ? 'Account' : 'Hesap bilgileri' }}</a>
<button type="button" data-theme-toggle><x-lucide name="moon" size="16" class="theme-icon-dark" /><x-lucide name="sun" size="16" class="theme-icon-light" /> {{ $en ? 'Theme' : 'Tema' }}</button>
<a href="{{ route('language.switch', $en ? 'tr' : 'en') }}"><x-lucide name="languages" size="16" /> {{ $en ? 'Türkçe' : 'English' }}</a>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button-danger"><x-lucide name="log-out" size="16" /> {{ $en ? 'Log out' : 'Çıkış yap' }}</button></form>
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
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body></html>
