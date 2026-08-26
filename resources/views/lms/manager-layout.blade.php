<!doctype html>
<html lang="{{ session('locale','tr') }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('page-title', session('locale', 'tr') === 'en' ? 'Manager workspace' : 'Yönetici çalışma alanı') · {{ session('locale', 'tr') === 'en' ? 'Arucad Continuous Learning Center' : 'Arucad Sürekli Öğrenme Merkezi' }}</title><link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.custom.min.css') }}"><link rel="stylesheet" href="{{ asset('css/lms.css') }}"><link rel="stylesheet" href="{{ asset('css/instructor.css') }}"><link rel="stylesheet" href="{{ asset('css/instructor-forms.css') }}">
<script>(function(){try{var t=localStorage.getItem('theme');if(t==='dark'){document.documentElement.dataset.theme='dark';document.documentElement.dataset.bsTheme='dark';}var s=localStorage.getItem('sidebar');if(s==='collapsed'){document.documentElement.dataset.sidebar='collapsed';}}catch(e){}})();</script>
</head>
<body class="instructor-page">
@php($en = session('locale', 'tr') === 'en')
@php($user = auth()->user())
@php($sidebarGroups = [['label' => $en ? 'MAIN MENU' : 'ANA MENÜ', 'items' => [
    ['url' => route('manager.dashboard').'#overview', 'label' => $en ? 'Overview' : 'Genel bakış', 'icon' => 'layout-grid', 'active' => request()->routeIs('manager.dashboard')],
    ['url' => route('manager.dashboard').'#team', 'label' => $en ? 'Team' : 'Ekip', 'icon' => 'users', 'active' => false],
    ['url' => route('manager.dashboard').'#training', 'label' => $en ? 'Training' : 'Eğitim Takibi', 'icon' => 'graduation-cap', 'active' => false],
    ['url' => route('manager.dashboard').'#compliance', 'label' => $en ? 'Compliance' : 'Uyumluluk', 'icon' => 'shield-check', 'active' => false],
    ['url' => route('manager.dashboard').'#reports', 'label' => $en ? 'Reports' : 'Raporlar', 'icon' => 'bar-chart', 'active' => false],
]]])
@php($sidebarGroups[] = ['label' => $en ? 'ACCOUNT' : 'HESAP', 'items' => [
    ['url' => route('account.show'), 'label' => $en ? 'Account' : 'Hesap bilgileri', 'icon' => 'circle-user', 'active' => request()->routeIs('account.show')],
    ['url' => route('certificates.verify.lookup'), 'label' => $en ? 'Verification' : 'Doğrulama', 'icon' => 'trophy', 'active' => request()->routeIs('certificates.verify*')],
]])
@php($otherWorkspaces = array_filter($user->workspaces(), fn ($w) => $w !== 'manager'))
@if(!empty($otherWorkspaces))
@php($workspaceLabels = ['learner' => $en ? 'Learner' : 'Öğrenci', 'instructor' => $en ? 'Instructor' : 'Eğitmen', 'manager' => $en ? 'Manager' : 'Yönetici', 'admin' => $en ? 'Admin' : 'Yönetim'])
@php($workspaceUrls = ['learner' => route('lms.home'), 'instructor' => route('instructor.dashboard'), 'manager' => route('manager.dashboard'), 'admin' => url('/admin')])
@php($workspaceIcons = ['learner' => 'graduation-cap', 'instructor' => 'layout-grid', 'manager' => 'users', 'admin' => 'settings'])
@php($sidebarGroups[] = ['label' => $en ? 'MY OTHER WORKSPACES' : 'DİĞER ÇALIŞMA ALANLARIM', 'items' => array_map(fn ($w) => ['url' => $workspaceUrls[$w] ?? '#', 'label' => $workspaceLabels[$w] ?? $w, 'icon' => $workspaceIcons[$w] ?? 'layout-grid'], $otherWorkspaces)])
@endif
<div class="app-shell">
<x-app-sidebar :brand-route="route('manager.dashboard')" :groups="$sidebarGroups" />
<div class="app-main">
<header class="app-topbar">
<button type="button" class="sidebar-mobile-toggle" data-sidebar-mobile-toggle aria-label="{{ $en ? 'Toggle menu' : 'Menüyü aç/kapat' }}"><x-lucide name="menu" size="20" /></button>
<div class="topbar-spacer"></div>
<div class="nav-actions">
@php($unreadNotifCount = $user->unreadNotifications->count())
@php($recentNotifications = $user->notifications()->latest()->limit(6)->get())
<div class="profile-menu dropdown">
<button class="profile-trigger" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="{{ $en ? 'Notifications' : 'Bildirimler' }}">
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
<button class="profile-trigger" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
<span class="profile-avatar">{{ mb_strtoupper(mb_substr($user->name,0,1)) }}</span>
<span class="profile-name">{{ $user->name }}</span>
</button>
<div class="profile-dropdown dropdown-menu">
<div class="dropdown-head"><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></div>
<div class="nav-lang"><a href="{{ route('language.switch', 'tr') }}" class="{{ !$en ? 'is-active' : '' }}">TR</a><a href="{{ route('language.switch', 'en') }}" class="{{ $en ? 'is-active' : '' }}">EN</a></div>
<div class="dropdown-divider"></div>
<a href="{{ route('lms.home') }}"><x-lucide name="globe" size="16" /> {{ $en ? 'View portal' : 'Portalı görüntüle' }}</a>
<button type="button" data-theme-toggle><x-lucide name="moon" size="16" class="theme-icon-dark" /><x-lucide name="sun" size="16" class="theme-icon-light" /> {{ $en ? 'Theme' : 'Tema' }}</button>
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button-danger"><x-lucide name="log-out" size="16" /> {{ $en ? 'Log out' : 'Çıkış yap' }}</button></form>
</div>
</div>
</div>
</header>
<main class="page-shell container instructor-main">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@yield('content')
</main>
</div>
</div>
<script>
document.addEventListener('click', function (e) {
    if (e.target.closest('[data-theme-toggle]')) {
        var isDark = document.documentElement.dataset.theme === 'dark';
        if (isDark) { delete document.documentElement.dataset.theme; delete document.documentElement.dataset.bsTheme; } else { document.documentElement.dataset.theme = 'dark'; document.documentElement.dataset.bsTheme = 'dark'; }
        try { localStorage.setItem('theme', isDark ? 'light' : 'dark'); } catch (err) {}
    }
});
</script>
<x-sidebar-script />
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body></html>
