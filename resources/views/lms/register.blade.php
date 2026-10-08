@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Create account' : 'Hesap oluştur').' · '.(session('locale', 'tr') === 'en' ? 'Arucad Training Center' : 'Arucad Eğitim Merkezi'))
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="container">
<div class="row align-items-center gy-5 auth-wrap-row">
<div class="col-lg-7 auth-intro">
<h1>{{ $en ? 'One account' : 'Gelişiminiz için' }}<br><em>{{ $en ? 'for your growth.' : 'tek bir hesap.' }}</em></h1>
<p>{{ $en ? 'Create your account in seconds with your corporate email.' : 'Kurumsal e-posta adresinizle birkaç saniyede hesabınızı oluşturun.' }}</p>
<div class="auth-points">
<span><x-lucide name="check" size="14" /> {{ $en ? 'Personal learning space' : 'Kişisel öğrenme alanı' }}</span>
<span><x-lucide name="check" size="14" /> {{ $en ? 'Live class access' : 'Canlı sınıf erişimi' }}</span>
<span><x-lucide name="check" size="14" /> {{ $en ? 'Digital certificates' : 'Dijital sertifikalar' }}</span>
</div>
</div>
<div class="col-lg-5">
<div class="card surface auth-card p-4">
<div class="auth-card-head"><span class="auth-icon"><x-lucide name="sparkles" size="16" /></span><span>{{ $en ? 'Welcome' : 'Hoş geldiniz' }}</span></div>
<h2>{{ $en ? 'Create a new account' : 'Yeni hesap oluşturun' }}</h2>
<p class="muted">{{ $en ? 'Continue with your corporate email address.' : 'Kurumsal e-posta adresiniz ile devam edin.' }}</p>
<form method="post" action="{{ route('register') }}">
@csrf
<div class="mb-3"><label for="name" class="form-label">{{ $en ? 'Full name' : 'Ad soyad' }}</label><input id="name" name="name" type="text" class="form-control" value="{{ old('name') }}" placeholder="Ad Soyad" autocomplete="name" required></div>
<div class="mb-3"><label for="email" class="form-label">{{ $en ? 'Email' : 'E-posta' }}</label><input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" placeholder="ad.soyad@company.com" autocomplete="email" required></div>
<div class="mb-3"><label for="password" class="form-label">{{ $en ? 'Password' : 'Şifre' }}</label><input id="password" name="password" type="password" class="form-control" placeholder="••••••••" autocomplete="new-password" required></div>
<div class="mb-3"><label for="password_confirmation" class="form-label">{{ $en ? 'Confirm password' : 'Şifre (tekrar)' }}</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control" placeholder="••••••••" autocomplete="new-password" required></div>
<button type="submit" class="btn btn-primary w-100">{{ $en ? 'Create account' : 'Hesap oluştur' }} <x-lucide name="arrow-right" size="14" /></button>
</form>
<p class="muted text-center mt-3 mb-0">{{ $en ? 'Already have an account?' : 'Zaten hesabınız var mı?' }} <a href="{{ route('login') }}">{{ $en ? 'Sign in' : 'Giriş yapın' }}</a></p>
@if($errors->any())<div class="alert alert-danger mt-3 mb-0">{{ $errors->first() }}</div>@endif
</div>
</div>
</div>
</div>
@endsection
