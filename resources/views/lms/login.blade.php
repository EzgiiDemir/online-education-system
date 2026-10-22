@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Corporate sign in' : 'Kurumsal giriş').' · '.__('Arucad Eğitim Merkezi'))
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="container">
<div class="row align-items-center gy-5 auth-wrap-row">
<div class="col-lg-7 auth-intro">
<h1>{{ $en ? 'Continue your' : 'Öğrenmeye buradan' }}<br><em>{{ $en ? 'learning journey.' : 'devam edin.' }}</em></h1>
<p>{{ $en ? 'Discover your courses, join live classes, and access your certificates.' : 'Eğitimlerinizi keşfedin, canlı sınıflara katılın ve sertifikalarınıza ulaşın.' }}</p>
<div class="auth-points">
<span><x-lucide name="check" size="14" /> {{ $en ? 'Course catalog' : 'Eğitim kataloğu' }}</span>
<span><x-lucide name="check" size="14" /> {{ $en ? 'Live classes' : 'Canlı sınıflar' }}</span>
<span><x-lucide name="check" size="14" /> {{ $en ? 'Digital certificates' : 'Dijital sertifikalar' }}</span>
</div>
</div>
<div class="col-lg-5">
<div class="card surface auth-card p-4">
<h2>{{ $en ? 'Sign in' : 'Giriş yapın' }}</h2>
<form method="post" action="{{ $formAction ?? route('login') }}">
@csrf
<div class="mb-3"><label for="email" class="form-label">{{ $en ? 'Email' : 'E-posta' }}</label><input id="email" name="email" type="email" class="form-control" placeholder="ad.soyad@company.com" autocomplete="email" required></div>
<div class="mb-3"><label for="password" class="form-label">{{ $en ? 'Password' : 'Şifre' }}</label><input id="password" name="password" type="password" class="form-control" placeholder="••••••••" autocomplete="current-password" required></div>
<button type="submit" class="btn btn-primary w-100">{{ $en ? 'Sign in' : 'Giriş yap' }} <x-lucide name="arrow-right" size="14" /></button>
</form>
@if($settings->entra_enabled)<a class="btn btn-outline-secondary w-100 mt-2" href="{{ route('entra.redirect') }}">{{ $en ? 'Sign in with Microsoft' : 'Microsoft ile giriş yap' }}</a>@endif
@if($settings->self_registration_enabled)<p class="muted text-center mt-3 mb-0">{{ $en ? "Don't have an account?" : 'Hesabınız yok mu?' }} <a href="{{ route('register') }}">{{ $en ? 'Create one' : 'Hesap oluşturun' }}</a></p>@endif
@if($errors->any())<div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>@endif
</div>
</div>
</div>
</div>
@endsection
