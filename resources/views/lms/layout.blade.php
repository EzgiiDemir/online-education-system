<!doctype html>
<html lang="{{ session('locale', 'tr') }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Campus LMS')</title><link rel="stylesheet" href="{{ asset('css/lms.css') }}"><link rel="stylesheet" href="{{ asset('css/auth.css') }}"><link rel="stylesheet" href="{{ asset('css/learning.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-fullscreen.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-icons.css') }}"><link rel="stylesheet" href="{{ asset('css/classroom-light.css') }}">
</head>
<body class="{{ trim($__env->yieldContent('body-class')) }}">
<nav class="site-nav"><a class="brand" href="{{ route('lms.home') }}">Campus <span>LMS</span></a><div class="nav-actions">
<a class="nav-link" href="{{ route('language.switch', 'tr') }}">TR</a><a class="nav-link" href="{{ route('language.switch', 'en') }}">EN</a>
@auth @if(auth()->user()->role !== 'admin')<a class="nav-link" href="{{ route('learning.index') }}">Eğitimlerim</a>@endif <span class="nav-user">{{ auth()->user()->name }}</span><form method="post" action="{{ route('logout') }}" class="inline-form">@csrf<button class="nav-button" type="submit">{{ session('locale', 'tr') === 'en' ? 'Logout' : 'Çıkış' }}</button></form>@else <a class="nav-button" href="{{ route('login') }}">{{ session('locale', 'tr') === 'en' ? 'Login' : 'Giriş' }}</a>@endauth
</div></nav>
<main class="page-shell">@if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif @if(session('error'))<div class="alert error">{{ session('error') }}</div>@endif @yield('content')</main>
</body></html>
