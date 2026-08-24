<!doctype html>
<html lang="{{ session('locale','tr') }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('page-title', session('locale', 'tr') === 'en' ? 'Instructor workspace' : 'Eğitmen çalışma alanı') · Arucad Campus LMS</title><link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.custom.min.css') }}"><link rel="stylesheet" href="{{ asset('css/lms.css') }}"><link rel="stylesheet" href="{{ asset('css/instructor.css') }}"><link rel="stylesheet" href="{{ asset('css/instructor-forms.css') }}">
<script>(function(){try{var t=localStorage.getItem('theme');if(t==='dark'){document.documentElement.dataset.theme='dark';document.documentElement.dataset.bsTheme='dark';}}catch(e){}})();</script>
</head>
<body class="instructor-page">
@php($en = session('locale', 'tr') === 'en')
<nav class="navbar navbar-expand-lg site-nav sticky-top">
<div class="container-fluid">
<a class="navbar-brand brand" href="{{ route('instructor.dashboard') }}">Arucad Campus <span>LMS</span></a>
<x-workspace-switcher />
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#instructorNavCollapse" aria-controls="instructorNavCollapse" aria-expanded="false" aria-label="{{ $en ? 'Toggle navigation' : 'Menüyü aç/kapat' }}"><x-lucide name="menu" size="20" /></button>
<div class="collapse navbar-collapse ms-auto justify-content-end" id="instructorNavCollapse">
<ul class="navbar-nav me-lg-3 mb-2 mb-lg-0">
<li class="nav-item"><a class="nav-link {{ request()->routeIs('instructor.dashboard') ? 'is-active' : '' }}" href="{{ route('instructor.dashboard') }}">{{ $en ? 'Overview' : 'Genel bakış' }}</a></li>
<li class="nav-item"><a class="nav-link {{ request()->routeIs('instructor.courses.*') ? 'is-active' : '' }}" href="{{ route('instructor.courses.index') }}">{{ $en ? 'My Courses' : 'Eğitimlerim' }}</a></li>
<li class="nav-item"><a class="nav-link {{ request()->routeIs('instructor.calendar') ? 'is-active' : '' }}" href="{{ route('instructor.calendar') }}">{{ $en ? 'Calendar' : 'Takvim' }}</a></li>
<li class="nav-item"><a class="nav-link {{ request()->routeIs('instructor.profile') ? 'is-active' : '' }}" href="{{ route('instructor.profile') }}">{{ $en ? 'My Profile' : 'Profilim' }}</a></li>
</ul>
<div class="nav-actions">
<div class="profile-menu dropdown">
<button class="profile-trigger" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
<span class="profile-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span>
<span class="profile-name">{{ auth()->user()->name }}</span>
</button>
<div class="profile-dropdown dropdown-menu">
<div class="dropdown-head"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></div>
<div class="nav-lang"><a href="{{ route('language.switch', 'tr') }}" class="{{ !$en ? 'is-active' : '' }}">TR</a><a href="{{ route('language.switch', 'en') }}" class="{{ $en ? 'is-active' : '' }}">EN</a></div>
<div class="dropdown-divider"></div>
<a href="{{ route('instructor.profile') }}"><x-lucide name="circle-user" size="16" /> {{ $en ? 'Profile' : 'Profilim' }}</a>
<a href="{{ route('account.show') }}"><x-lucide name="shield-check" size="16" /> {{ $en ? 'Account' : 'Hesap bilgileri' }}</a>
<a href="{{ route('lms.home') }}"><x-lucide name="globe" size="16" /> {{ $en ? 'View portal' : 'Portalı görüntüle' }}</a>
<button type="button" data-theme-toggle><x-lucide name="moon" size="16" class="theme-icon-dark" /><x-lucide name="sun" size="16" class="theme-icon-light" /> {{ $en ? 'Theme' : 'Tema' }}</button>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button-danger"><x-lucide name="log-out" size="16" /> {{ $en ? 'Log out' : 'Çıkış yap' }}</button></form>
</div>
</div>
</div>
</div>
</div>
</nav>
<main class="page-shell container instructor-main">
<header class="instructor-header"><div><span class="eyebrow">INSTRUCTOR WORKSPACE</span><h1>@yield('page-title', $en ? 'Instructor workspace' : 'Eğitmen çalışma alanı')</h1></div></header>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@yield('content')
</main>
<script>
document.addEventListener('click', function (e) {
    if (e.target.closest('[data-theme-toggle]')) {
        var isDark = document.documentElement.dataset.theme === 'dark';
        if (isDark) { delete document.documentElement.dataset.theme; delete document.documentElement.dataset.bsTheme; } else { document.documentElement.dataset.theme = 'dark'; document.documentElement.dataset.bsTheme = 'dark'; }
        try { localStorage.setItem('theme', isDark ? 'light' : 'dark'); } catch (err) {}
    }
});
</script>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body></html>
