@extends('lms.instructor-layout') @section('page-title', session('locale', 'tr') === 'en' ? 'My Profile' : 'Profilim') @section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="card" style="max-width:760px">
<h2>{{ $en ? 'Profile information' : 'Profil bilgileri' }}</h2>
<p class="muted">{{ $en ? 'Your email address cannot be changed per corporate policy. Contact your admin for a password change.' : 'E-posta adresiniz kurumsal politika gereği değiştirilemez. Şifre değişikliği için yöneticinizle iletişime geçin.' }}</p>
<form method="post" enctype="multipart/form-data" action="{{route('instructor.profile.update')}}">
@csrf
<div class="mb-3"><label class="form-label">{{ $en ? 'Full name' : 'Ad soyad' }}</label><input name="name" class="form-control" value="{{$user->name}}" required></div>
<div class="mb-3"><label class="form-label">{{ $en ? 'Department' : 'Birim' }}</label><input name="department" class="form-control" value="{{$user->department}}" placeholder="{{ $en ? 'e.g. Human Resources' : 'Örn. İnsan Kaynakları' }}"></div>
<div class="mb-3"><label class="form-label">{{ $en ? 'Job title' : 'Unvan' }}</label><input name="job_title" class="form-control" value="{{$user->job_title}}" placeholder="{{ $en ? 'e.g. Senior Instructor' : 'Örn. Kıdemli Eğitmen' }}"></div>
<div class="mb-3"><label class="form-label">{{ $en ? 'Corporate email' : 'Kurumsal e-posta' }}</label><input class="form-control" value="{{$user->email}}" disabled></div>
<div class="mb-3"><label class="form-label">{{ $en ? 'Photo' : 'Fotoğraf' }}</label><input type="file" name="avatar" class="form-control" accept="image/*"></div>
<div class="mb-3"><label class="form-label">{{ $en ? 'Short bio' : 'Kısa biyografi' }}</label><textarea name="bio" class="form-control" rows="5">{{$user->bio}}</textarea></div>
<button class="btn btn-primary">{{ $en ? 'Save profile' : 'Profili kaydet' }}</button>
</form>
</div>
<div class="card" style="max-width:760px;margin-top:20px">
<h2>{{ $en ? 'Account management' : 'Hesap yönetimi' }}</h2>
<p class="muted">{{ $en ? 'Go to your account page to view your training history or manage your account.' : 'Eğitim geçmişinizi görüntülemek veya hesabınızla ilgili işlemler için hesap sayfanıza gidin.' }}</p>
<a class="btn btn-outline-secondary" href="{{route('account.show')}}">{{ $en ? 'My account' : 'Hesap bilgilerim' }} <x-lucide name="arrow-right" size="14" /></a>
</div>
@endsection
