<!doctype html>
<html lang="{{ session('locale','tr') }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('page-title', session('locale', 'tr') === 'en' ? 'Instructor workspace' : 'Eğitmen çalışma alanı') · Arucad Campus LMS</title><link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.custom.min.css') }}"><link rel="stylesheet" href="{{ asset('css/lms.css') }}"><link rel="stylesheet" href="{{ asset('css/instructor.css') }}"><link rel="stylesheet" href="{{ asset('css/instructor-forms.css') }}">
<script>(function(){try{var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.dataset.theme='dark';}catch(e){}})();</script>
</head>
<body class="instructor-page">
@php($en = session('locale', 'tr') === 'en')
<div class="instructor-shell">
<aside class="instructor-sidebar"><a class="brand sidebar-brand" href="{{ route('instructor.dashboard') }}">Arucad Campus <span>LMS</span></a><div class="sidebar-profile"><div class="sidebar-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</div><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role === 'admin' ? ($en ? 'Admin' : 'Yönetici') : ($en ? 'Instructor' : 'Eğitmen') }}</small></div></div><div class="sidebar-label">{{ $en ? 'WORKSPACE' : 'ÇALIŞMA ALANI' }}</div><nav class="sidebar-nav"><a href="{{ route('instructor.dashboard') }}" class="{{ request()->routeIs('instructor.dashboard') ? 'is-active' : '' }}"><span><x-lucide name="house" size="16" /></span> {{ $en ? 'Overview' : 'Genel bakış' }}</a><a href="{{ route('instructor.courses.index') }}" class="{{ request()->routeIs('instructor.courses.index') ? 'is-active' : '' }}"><span><x-lucide name="layout-grid" size="16" /></span> {{ $en ? 'My Courses' : 'Eğitimlerim' }}</a><a href="{{ route('instructor.profile') }}" class="{{ request()->routeIs('instructor.profile') ? 'is-active' : '' }}"><span><x-lucide name="circle-user" size="16" /></span> {{ $en ? 'My Profile' : 'Profilim' }}</a><a href="{{ route('account.show') }}" class="{{ request()->routeIs('account.show') ? 'is-active' : '' }}"><span><x-lucide name="shield-check" size="16" /></span> {{ $en ? 'Account' : 'Hesap bilgileri' }}</a></nav><div class="sidebar-label">{{ $en ? 'SHORTCUTS' : 'KISAYOLLAR' }}</div><nav class="sidebar-nav"><a href="{{ route('lms.home') }}"><span><x-lucide name="globe" size="16" /></span> {{ $en ? 'View portal' : 'Portalı görüntüle' }}</a></nav></aside>
<main class="instructor-main"><header class="instructor-header"><div>@unless(request()->routeIs('instructor.dashboard'))<button type="button" class="back-link" onclick="history.length > 1 ? history.back() : (window.location = '{{ route('instructor.dashboard') }}')"><x-lucide name="arrow-left" size="16" /> {{ $en ? 'Back' : 'Geri' }}</button>@endunless<span class="eyebrow">INSTRUCTOR WORKSPACE</span><h1>@yield('page-title', $en ? 'Instructor workspace' : 'Eğitmen çalışma alanı')</h1></div>
<div class="instructor-header-actions">
<div class="nav-lang"><a href="{{ route('language.switch', 'tr') }}" class="{{ !$en ? 'is-active' : '' }}">TR</a><a href="{{ route('language.switch', 'en') }}" class="{{ $en ? 'is-active' : '' }}">EN</a></div>
<div class="profile-menu dropdown">
<button class="profile-trigger" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
<span class="profile-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span>
<span class="profile-name">{{ auth()->user()->name }}</span>
</button>
<div class="profile-dropdown dropdown-menu">
<div class="dropdown-head"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></div>
<a href="{{ route('instructor.profile') }}"><x-lucide name="circle-user" size="16" /> {{ $en ? 'Profile' : 'Profilim' }}</a>
<button type="button" data-theme-toggle><x-lucide name="moon" size="16" class="theme-icon-dark" /><x-lucide name="sun" size="16" class="theme-icon-light" /> {{ $en ? 'Theme' : 'Tema' }}</button>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button-danger"><x-lucide name="log-out" size="16" /> {{ $en ? 'Log out' : 'Çıkış yap' }}</button></form>
</div>
</div>
</div>
</header>@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif @yield('content')</main>
</div>
<script>
document.addEventListener('click', function (e) {
    if (e.target.closest('[data-theme-toggle]')) {
        var isDark = document.documentElement.dataset.theme === 'dark';
        if (isDark) { delete document.documentElement.dataset.theme; } else { document.documentElement.dataset.theme = 'dark'; }
        try { localStorage.setItem('theme', isDark ? 'light' : 'dark'); } catch (err) {}
    }
});
</script>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body></html>
