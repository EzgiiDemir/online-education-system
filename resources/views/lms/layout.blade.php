<!doctype html>
<html lang="{{ session('locale', 'tr') }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Arucad Campus LMS')</title><link rel="stylesheet" href="{{ asset('css/lms.css') }}"><link rel="stylesheet" href="{{ asset('css/auth.css') }}"><link rel="stylesheet" href="{{ asset('css/learning.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-fullscreen.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-icons.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-light.css') }}">
    <script>(function(){try{var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.dataset.theme='dark';}catch(e){}})();</script>
</head>
<body class="{{ trim($__env->yieldContent('body-class')) }}">
@php($en = session('locale', 'tr') === 'en')
<nav class="site-nav">
<a class="brand" href="{{ route('lms.home') }}">Arucad Campus <span>LMS</span></a>
<div class="nav-actions">
@auth @if(auth()->user()->role !== 'admin')<a class="nav-link" href="{{ route('learning.index') }}">{{ $en ? 'My Courses' : 'Eğitimlerim' }}</a>@endif @endauth
<a class="nav-link" href="{{ route('learning-paths.index') }}">{{ $en ? 'Learning Paths' : 'Öğrenim Yolları' }}</a>
<div class="nav-lang"><a href="{{ route('language.switch', 'tr') }}" class="{{ !$en ? 'is-active' : '' }}">TR</a><a href="{{ route('language.switch', 'en') }}" class="{{ $en ? 'is-active' : '' }}">EN</a></div>
@auth
@php($unreadNotifCount = auth()->user()->unreadNotifications->count())
@php($recentNotifications = auth()->user()->notifications()->latest()->limit(6)->get())
<div class="notif-menu">
<button class="notif-trigger" type="button" data-notif-toggle>
🔔
@if($unreadNotifCount > 0)<span class="notif-badge">{{ $unreadNotifCount > 9 ? '9+' : $unreadNotifCount }}</span>@endif
</button>
<div class="notif-dropdown" data-notif-dropdown>
<div class="dropdown-head">
<strong>{{ $en ? 'Notifications' : 'Bildirimler' }}</strong>
@if($unreadNotifCount > 0)<form method="post" action="{{ route('notifications.read-all') }}">@csrf<button type="submit" class="notif-mark-all">{{ $en ? 'Mark all read' : 'Tümünü okundu işaretle' }}</button></form>@endif
</div>
@forelse($recentNotifications as $notification)
<form method="post" action="{{ route('notifications.read', $notification->id) }}" class="notif-item{{ $notification->read_at ? '' : ' is-unread' }}">
@csrf
<button type="submit">{{ $notification->data['message'] ?? '—' }}</button>
</form>
@empty
<p class="muted" style="padding:16px;margin:0">{{ $en ? 'No notifications yet.' : 'Henüz bildirim yok.' }}</p>
@endforelse
<a href="{{ route('notifications.index') }}" class="notif-viewall">{{ $en ? 'View all' : 'Tümünü gör' }}</a>
</div>
</div>
<div class="profile-menu">
<button class="profile-trigger" type="button" data-profile-toggle>
<span class="profile-avatar">
@if(auth()->user()->avatar_path)
<img src="{{ asset('storage/'.auth()->user()->avatar_path) }}" alt="">
@else
{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}
@endif
</span>
<span class="profile-name">{{ auth()->user()->name }}</span>
</button>
<div class="profile-dropdown" data-profile-dropdown>
<div class="dropdown-head"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></div>
@if(auth()->user()->role==='admin')<a href="/admin">⚙ {{ $en ? 'Admin panel' : 'Yönetim paneli' }}</a>@endif
@if(auth()->user()->isInstructor() && auth()->user()->role!=='admin')<a href="{{ route('instructor.dashboard') }}">▦ {{ $en ? 'Instructor panel' : 'Eğitmen paneli' }}</a>@endif
@if(auth()->user()->role==='manager')<a href="{{ route('manager.dashboard') }}">▦ {{ $en ? 'Manager panel' : 'Yönetici paneli' }}</a>@endif
<a href="{{ route('account.show') }}">◎ {{ $en ? 'Account' : 'Hesap bilgileri' }}</a>
<a href="{{ route('skills.index') }}">🏅 {{ $en ? 'My Skills' : 'Yetkinliklerim' }}</a>
<button type="button" data-theme-toggle>◐ {{ $en ? 'Theme' : 'Tema' }}</button>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button-danger">↪ {{ $en ? 'Log out' : 'Çıkış yap' }}</button></form>
</div>
</div>
@else
<a class="nav-button" href="{{ route('login') }}">{{ $en ? 'Login' : 'Giriş' }}</a>
@endauth
</div>
</nav>
<main class="page-shell">@unless(request()->routeIs('lms.home'))<button type="button" class="back-link" onclick="history.length > 1 ? history.back() : (window.location = '{{ route('lms.home') }}')">← {{ $en ? 'Back' : 'Geri' }}</button>@endunless @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif @yield('content')</main>
<script>
document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-profile-toggle]');
    var dropdown = document.querySelector('[data-profile-dropdown]');
    if (dropdown) {
        if (toggle) { dropdown.classList.toggle('is-open'); return; }
        if (!e.target.closest('[data-profile-dropdown]')) dropdown.classList.remove('is-open');
    }
    var notifToggle = e.target.closest('[data-notif-toggle]');
    var notifDropdown = document.querySelector('[data-notif-dropdown]');
    if (notifDropdown) {
        if (notifToggle) { notifDropdown.classList.toggle('is-open'); return; }
        if (!e.target.closest('[data-notif-dropdown]')) notifDropdown.classList.remove('is-open');
    }
    if (e.target.closest('[data-theme-toggle]')) {
        var isDark = document.documentElement.dataset.theme === 'dark';
        if (isDark) { delete document.documentElement.dataset.theme; } else { document.documentElement.dataset.theme = 'dark'; }
        try { localStorage.setItem('theme', isDark ? 'light' : 'dark'); } catch (err) {}
    }
});
</script>
</body></html>
