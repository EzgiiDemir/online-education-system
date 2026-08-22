<!doctype html>
<html lang="{{ session('locale', 'tr') }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Arucad Campus LMS')</title><link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.custom.min.css') }}"><link rel="stylesheet" href="{{ asset('css/lms.css') }}"><link rel="stylesheet" href="{{ asset('css/auth.css') }}"><link rel="stylesheet" href="{{ asset('css/learning.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-fullscreen.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-icons.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-light.css') }}">
    <script>(function(){try{var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.dataset.theme='dark';}catch(e){}})();</script>
</head>
<body class="{{ trim($__env->yieldContent('body-class')) }}">
@php($en = session('locale', 'tr') === 'en')
<nav class="site-nav">
<a class="brand" href="{{ route('lms.home') }}">Arucad Campus <span>LMS</span></a>
<div class="nav-actions">
<div class="nav-search" data-search-widget>
<form method="get" action="{{ route('search.index') }}" role="search" autocomplete="off">
<input type="search" name="q" placeholder="{{ $en ? 'Search…' : 'Ara…' }}" aria-label="{{ $en ? 'Search the portal' : 'Portalda ara' }}" data-search-input>
</form>
<div class="nav-search-results" data-search-results></div>
</div>
@auth @if(auth()->user()->role !== 'admin')<a class="nav-link" href="{{ route('learning.index') }}">{{ $en ? 'My Courses' : 'Eğitimlerim' }}</a>@endif @endauth
<a class="nav-link" href="{{ route('learning-paths.index') }}">{{ $en ? 'Learning Paths' : 'Öğrenim Yolları' }}</a>
<div class="nav-lang"><a href="{{ route('language.switch', 'tr') }}" class="{{ !$en ? 'is-active' : '' }}">TR</a><a href="{{ route('language.switch', 'en') }}" class="{{ $en ? 'is-active' : '' }}">EN</a></div>
@auth
@php($unreadNotifCount = auth()->user()->unreadNotifications->count())
@php($recentNotifications = auth()->user()->notifications()->latest()->limit(6)->get())
<div class="notif-menu dropdown">
<button class="notif-trigger" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="{{ $en ? 'Notifications' : 'Bildirimler' }}{{ $unreadNotifCount > 0 ? ' ('.$unreadNotifCount.' '.($en ? 'unread' : 'okunmamış').')' : '' }}">
<x-lucide name="bell" size="20" />
@if($unreadNotifCount > 0)<span class="notif-badge" aria-hidden="true">{{ $unreadNotifCount > 9 ? '9+' : $unreadNotifCount }}</span>@endif
</button>
<div class="notif-dropdown dropdown-menu">
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
<div class="profile-menu dropdown">
<button class="profile-trigger" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
<span class="profile-avatar">
@if(auth()->user()->avatar_path)
<img src="{{ asset('storage/'.auth()->user()->avatar_path) }}" alt="">
@else
{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}
@endif
</span>
<span class="profile-name">{{ auth()->user()->name }}</span>
</button>
<div class="profile-dropdown dropdown-menu">
<div class="dropdown-head"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->email }}</small></div>
@if(auth()->user()->role==='admin')<a href="/admin"><x-lucide name="settings" size="16" /> {{ $en ? 'Admin panel' : 'Yönetim paneli' }}</a><a href="{{ route('reports.index') }}"><x-lucide name="bar-chart" size="16" /> {{ $en ? 'Reports' : 'Raporlar' }}</a>@endif
@if(auth()->user()->isInstructor() && auth()->user()->role!=='admin')<a href="{{ route('instructor.dashboard') }}"><x-lucide name="layout-grid" size="16" /> {{ $en ? 'Instructor panel' : 'Eğitmen paneli' }}</a>@endif
@if(auth()->user()->role==='manager')<a href="{{ route('manager.dashboard') }}"><x-lucide name="layout-grid" size="16" /> {{ $en ? 'Manager panel' : 'Yönetici paneli' }}</a>@endif
<a href="{{ route('account.show') }}"><x-lucide name="circle-user" size="16" /> {{ $en ? 'Account' : 'Hesap bilgileri' }}</a>
<a href="{{ route('skills.index') }}"><x-lucide name="award" size="16" /> {{ $en ? 'My Skills' : 'Yetkinliklerim' }}</a>
<a href="{{ route('calendar.index') }}"><x-lucide name="calendar" size="16" /> {{ $en ? 'Calendar' : 'Takvim' }}</a>
<button type="button" data-theme-toggle><x-lucide name="moon" size="16" class="theme-icon-dark" /><x-lucide name="sun" size="16" class="theme-icon-light" /> {{ $en ? 'Theme' : 'Tema' }}</button>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button-danger"><x-lucide name="log-out" size="16" /> {{ $en ? 'Log out' : 'Çıkış yap' }}</button></form>
</div>
</div>
@else
<a class="nav-button" href="{{ route('login') }}">{{ $en ? 'Login' : 'Giriş' }}</a>
@endauth
</div>
</nav>
<main class="page-shell">@unless(request()->routeIs('lms.home'))<button type="button" class="back-link" onclick="history.length > 1 ? history.back() : (window.location = '{{ route('lms.home') }}')"><x-lucide name="arrow-left" size="16" /> {{ $en ? 'Back' : 'Geri' }}</button>@endunless @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif @yield('content')</main>
<script>
document.addEventListener('click', function (e) {
    if (e.target.closest('[data-theme-toggle]')) {
        var isDark = document.documentElement.dataset.theme === 'dark';
        if (isDark) { delete document.documentElement.dataset.theme; } else { document.documentElement.dataset.theme = 'dark'; }
        try { localStorage.setItem('theme', isDark ? 'light' : 'dark'); } catch (err) {}
    }
});
</script>
<script>
(function(){
    var widget = document.querySelector('[data-search-widget]');
    if (!widget) return;
    var input = widget.querySelector('[data-search-input]');
    var results = widget.querySelector('[data-search-results]');
    var groups = [
        ['courses', {{ $en ? "'Courses'" : "'Eğitimler'" }}],
        ['activities', {{ $en ? "'Activities'" : "'Etkinlikler'" }}],
        ['materials', {{ $en ? "'Materials'" : "'Materyaller'" }}],
        ['sessions', {{ $en ? "'Sessions'" : "'Seanslar'" }}],
        ['instructors', {{ $en ? "'Instructors'" : "'Eğitmenler'" }}]
    ];
    var timer = null;
    var lastQuery = '';

    function render(data, query) {
        var total = groups.reduce(function(sum, g){ return sum + data[g[0]].length; }, 0);
        if (total === 0) {
            results.innerHTML = '<p class="nav-search-empty">{{ $en ? "No results" : "Sonuç bulunamadı" }}</p>';
            results.classList.add('is-open');
            return;
        }
        var html = '';
        groups.forEach(function(g){
            var items = data[g[0]];
            if (!items.length) return;
            html += '<div class="nav-search-group"><small>' + g[1] + '</small>';
            items.forEach(function(item){
                if (item.url) {
                    html += '<a href="' + item.url + '">' + item.title + '</a>';
                } else {
                    html += '<span class="nav-search-plain">' + item.title + '</span>';
                }
            });
            html += '</div>';
        });
        html += '<a class="notif-viewall" href="/search?q=' + encodeURIComponent(query) + '">{{ $en ? "View all results" : "Tüm sonuçları gör" }} →</a>';
        results.innerHTML = html;
        results.classList.add('is-open');
    }

    input.addEventListener('input', function () {
        var query = input.value.trim();
        clearTimeout(timer);
        if (query.length < 2) { results.classList.remove('is-open'); results.innerHTML = ''; return; }
        timer = setTimeout(function () {
            lastQuery = query;
            fetch('{{ route('search.quick') }}?q=' + encodeURIComponent(query), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) { if (query === input.value.trim()) render(data, query); });
        }, 250);
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { results.classList.remove('is-open'); input.blur(); }
    });

    document.addEventListener('click', function (e) {
        if (!widget.contains(e.target)) results.classList.remove('is-open');
    });
})();
</script>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body></html>
