<!doctype html>
<html lang="{{ session('locale', 'tr') }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Arucad Campus LMS')</title><link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.custom.min.css') }}"><link rel="stylesheet" href="{{ asset('css/lms.css') }}"><link rel="stylesheet" href="{{ asset('css/auth.css') }}"><link rel="stylesheet" href="{{ asset('css/learning.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-fullscreen.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-icons.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-light.css') }}">
    <script>(function(){try{var t=localStorage.getItem('theme');if(t==='dark'){document.documentElement.dataset.theme='dark';document.documentElement.dataset.bsTheme='dark';}var s=localStorage.getItem('sidebar');if(s==='collapsed'){document.documentElement.dataset.sidebar='collapsed';}}catch(e){}})();</script>
</head>
<body class="{{ trim($__env->yieldContent('body-class')) }}">
@php($en = session('locale', 'tr') === 'en')
@auth
@php($user = auth()->user())
@php($sidebarGroups = [])
@php($sidebarGroups[] = ['label' => $en ? 'MAIN MENU' : 'ANA MENÜ', 'items' => array_filter([
    ['url' => route('lms.home'), 'label' => $en ? 'Home' : 'Anasayfa', 'icon' => 'house', 'active' => request()->routeIs('lms.home')],
    $user->role !== 'admin' ? ['url' => route('learning.index'), 'label' => $en ? 'My Courses' : 'Eğitimlerim', 'icon' => 'book-open', 'active' => request()->routeIs('learning.index')] : null,
    $user->role !== 'admin' ? ['url' => route('learning-paths.index'), 'label' => $en ? 'Learning Paths' : 'Öğrenme Yolları', 'icon' => 'map', 'active' => request()->routeIs('learning-paths.*')] : null,
    ['url' => route('calendar.index'), 'label' => $en ? 'Calendar' : 'Takvim', 'icon' => 'calendar', 'active' => request()->routeIs('calendar.index')],
    $user->role !== 'admin' ? ['url' => route('skills.index'), 'label' => $en ? 'My Skills' : 'Yetkinliklerim', 'icon' => 'award', 'active' => request()->routeIs('skills.index')] : null,
])])
@php($sidebarGroups[] = ['label' => $en ? 'ACCOUNT' : 'HESAP', 'items' => [
    ['url' => route('account.show'), 'label' => $en ? 'Account' : 'Hesap bilgileri', 'icon' => 'circle-user', 'active' => request()->routeIs('account.show')],
]])
@php($otherWorkspaces = array_filter($user->workspaces(), fn ($w) => $w !== $user->role))
@if(!empty($otherWorkspaces))
@php($workspaceLabels = ['learner' => $en ? 'Learner' : 'Öğrenci', 'instructor' => $en ? 'Instructor' : 'Eğitmen', 'manager' => $en ? 'Manager' : 'Yönetici', 'admin' => $en ? 'Admin' : 'Yönetim'])
@php($workspaceUrls = ['learner' => route('lms.home'), 'instructor' => route('instructor.dashboard'), 'manager' => route('manager.dashboard'), 'admin' => url('/admin')])
@php($workspaceIcons = ['learner' => 'graduation-cap', 'instructor' => 'layout-grid', 'manager' => 'users', 'admin' => 'settings'])
@php($sidebarGroups[] = ['label' => $en ? 'MY OTHER WORKSPACES' : 'DİĞER ÇALIŞMA ALANLARIM', 'items' => array_map(fn ($w) => ['url' => $workspaceUrls[$w] ?? '#', 'label' => $workspaceLabels[$w] ?? $w, 'icon' => $workspaceIcons[$w] ?? 'layout-grid'], $otherWorkspaces)])
@endif
@if($user->role === 'admin')
@php($sidebarGroups[] = ['label' => $en ? 'ADMIN' : 'YÖNETİM', 'items' => [
    ['url' => '/admin', 'label' => $en ? 'Admin panel' : 'Yönetim paneli', 'icon' => 'settings'],
    ['url' => route('reports.index'), 'label' => $en ? 'Reports' : 'Raporlar', 'icon' => 'bar-chart', 'active' => request()->routeIs('reports.*')],
]])
@endif
<div class="app-shell">
<x-app-sidebar :brand-route="route('lms.home')" :groups="$sidebarGroups" />
<div class="app-main">
<header class="app-topbar">
<button type="button" class="sidebar-mobile-toggle" data-sidebar-mobile-toggle aria-label="{{ $en ? 'Toggle menu' : 'Menüyü aç/kapat' }}"><x-lucide name="menu" size="20" /></button>
<div class="nav-search" data-search-widget>
<form method="get" action="{{ route('search.index') }}" role="search" autocomplete="off">
<input type="search" name="q" placeholder="{{ $en ? 'Search…' : 'Ara…' }}" aria-label="{{ $en ? 'Search the portal' : 'Portalda ara' }}" data-search-input>
</form>
<div class="nav-search-results" data-search-results></div>
</div>
<div class="topbar-spacer"></div>
<div class="nav-actions">
@php($unreadNotifCount = $user->unreadNotifications->count())
@php($recentNotifications = $user->notifications()->latest()->limit(6)->get())
<div class="profile-menu dropdown">
<button class="profile-trigger" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="{{ $en ? 'Notifications' : 'Bildirimler' }}{{ $unreadNotifCount > 0 ? ' ('.$unreadNotifCount.' '.($en ? 'unread' : 'okunmamış').')' : '' }}">
<span class="profile-avatar-wrap"><x-lucide name="bell" size="18" />@if($unreadNotifCount > 0)<span class="notif-badge" aria-hidden="true">{{ $unreadNotifCount > 9 ? '9+' : $unreadNotifCount }}</span>@endif</span>
</button>
<div class="profile-dropdown dropdown-menu">
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
<button class="profile-trigger" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="{{ $user->name }}">
<span class="profile-avatar-wrap">
<span class="profile-avatar">
@if($user->avatar_path)
<img src="{{ asset('storage/'.$user->avatar_path) }}" alt="">
@else
{{ mb_strtoupper(mb_substr($user->name,0,1)) }}
@endif
</span>
</span>
<span class="profile-name">{{ $user->name }}</span>
</button>
<div class="profile-dropdown dropdown-menu">
<div class="dropdown-head"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div>
<div class="nav-lang"><a href="{{ route('language.switch', 'tr') }}" class="{{ !$en ? 'is-active' : '' }}">TR</a><a href="{{ route('language.switch', 'en') }}" class="{{ $en ? 'is-active' : '' }}">EN</a></div>
<div class="dropdown-divider"></div>
<a href="{{ route('account.show') }}"><x-lucide name="circle-user" size="16" /> {{ $en ? 'Account' : 'Hesap bilgileri' }}</a>
<button type="button" data-theme-toggle><x-lucide name="moon" size="16" class="theme-icon-dark" /><x-lucide name="sun" size="16" class="theme-icon-light" /> {{ $en ? 'Theme' : 'Tema' }}</button>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button-danger"><x-lucide name="log-out" size="16" /> {{ $en ? 'Log out' : 'Çıkış yap' }}</button></form>
</div>
</div>
</div>
</header>
<main class="page-shell container">@unless(request()->routeIs('lms.home'))<button type="button" class="back-link" onclick="history.length > 1 ? history.back() : (window.location = '{{ route('lms.home') }}')"><x-lucide name="arrow-left" size="16" /> {{ $en ? 'Back' : 'Geri' }}</button>@endunless @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif @yield('content')</main>
</div>
</div>
@else
<nav class="navbar navbar-expand-lg site-nav sticky-top">
<div class="container-fluid">
<a class="navbar-brand brand" href="{{ route('lms.home') }}">Arucad Campus <span>LMS</span></a>
<div class="nav-actions">
<div class="nav-search" data-search-widget>
<form method="get" action="{{ route('search.index') }}" role="search" autocomplete="off">
<input type="search" name="q" placeholder="{{ $en ? 'Search…' : 'Ara…' }}" aria-label="{{ $en ? 'Search the portal' : 'Portalda ara' }}" data-search-input>
</form>
<div class="nav-search-results" data-search-results></div>
</div>
<a class="nav-button" href="{{ route('login') }}">{{ $en ? 'Login' : 'Giriş' }}</a>
</div>
</div>
</nav>
<main class="page-shell container">@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif @yield('content')</main>
@endauth
<script>
document.addEventListener('click', function (e) {
    if (e.target.closest('[data-theme-toggle]')) {
        var isDark = document.documentElement.dataset.theme === 'dark';
        if (isDark) { delete document.documentElement.dataset.theme; delete document.documentElement.dataset.bsTheme; } else { document.documentElement.dataset.theme = 'dark'; document.documentElement.dataset.bsTheme = 'dark'; }
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
        ['courses', {{ \Illuminate\Support\Js::from($en ? 'Courses' : 'Eğitimler') }}],
        ['activities', {{ \Illuminate\Support\Js::from($en ? 'Activities' : 'Etkinlikler') }}],
        ['materials', {{ \Illuminate\Support\Js::from($en ? 'Materials' : 'Materyaller') }}],
        ['sessions', {{ \Illuminate\Support\Js::from($en ? 'Sessions' : 'Seanslar') }}],
        ['instructors', {{ \Illuminate\Support\Js::from($en ? 'Instructors' : 'Eğitmenler') }}]
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
<x-sidebar-script />
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body></html>
