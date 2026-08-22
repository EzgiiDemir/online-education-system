@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Account' : 'Hesap bilgileri').' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'MY ACCOUNT' : 'HESABIM' }}</span><h1>{{ $en ? 'Account' : 'Hesap bilgileri' }}</h1><p class="muted">{{ $en ? 'Your profile information and training history.' : 'Profil bilgileriniz ve eğitim geçmişiniz.' }}</p></div></div>
<div class="account-grid">
<div class="card surface" style="padding:24px">
<div class="profile-avatar" style="width:64px;height:64px;font-size:22px;margin-bottom:14px">
@if($user->avatar_path)
<img src="{{ asset('storage/'.$user->avatar_path) }}" alt="">
@else
{{ mb_strtoupper(mb_substr($user->name,0,1)) }}
@endif
</div>
<h2 style="margin:0 0 4px">{{ $user->name }}</h2>
<p class="muted" style="margin:0 0 4px">{{ $user->email }}</p>
<p class="muted" style="margin:0 0 18px">{{ $en ? ['admin'=>'Admin','instructor'=>'Instructor','learner'=>'Learner'][$user->role] ?? $user->role : (['admin'=>'Yönetici','instructor'=>'Eğitmen','learner'=>'Öğrenci'][$user->role] ?? $user->role) }}</p>
@if($user->department)<p style="margin:4px 0"><b>{{ $en ? 'Department:' : 'Departman:' }}</b> {{ $user->department }}</p>@endif
@if($user->job_title)<p style="margin:4px 0"><b>{{ $en ? 'Job title:' : 'Unvan:' }}</b> {{ $user->job_title }}</p>@endif
<hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
<button type="button" class="button-danger" style="width:100%" onclick="document.getElementById('deleteAccountDialog').style.display='block'">{{ $en ? 'Delete my account' : 'Hesabımı sil' }}</button>
<div id="deleteAccountDialog" class="danger-zone" style="display:none">
<p>{{ $en ? 'This action cannot be undone. All your records and certificates will be deleted. Enter your password to confirm.' : 'Bu işlem geri alınamaz. Tüm kayıtlarınız ve sertifikalarınız silinecektir. Onaylamak için şifrenizi girin.' }}</p>
<form method="post" action="{{ route('account.destroy') }}" onsubmit="return confirm('{{ $en ? 'Are you sure you want to permanently delete your account?' : 'Hesabınızı kalıcı olarak silmek istediğinize emin misiniz?' }}')">@csrf
<input type="password" name="password" placeholder="{{ $en ? 'Your password' : 'Şifreniz' }}" required>
<button type="submit" class="button-danger" style="width:100%;margin-top:10px">{{ $en ? 'Delete permanently' : 'Kalıcı olarak sil' }}</button>
</form>
</div>
</div>
<div>
@if($courses->isNotEmpty())
<div class="card surface" style="padding:24px;margin-bottom:18px">
<h2 style="margin-top:0">{{ $en ? 'Courses I teach' : 'Eğitmen olduğum kurslar' }}</h2>
<ul style="list-style:none;padding:0;margin:0">
@foreach($courses as $course)<li style="padding:10px 0;border-top:1px solid var(--line)"><b>{{ $course->title }}</b> <span class="muted">· {{ $course->enrollments_count }} {{ $en ? 'participants' : 'katılımcı' }}</span></li>@endforeach
</ul>
</div>
@endif
<div class="card surface" style="padding:24px">
<h2 style="margin-top:0">{{ $en ? 'My Courses' : 'Eğitimlerim' }}</h2>
@forelse($enrollments as $row)
<div style="padding:14px 0;border-top:1px solid var(--line)">
<div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><b>{{ $row['enrollment']->course->title }}</b><span class="status">{{ $row['stats']['percent'] }}%</span></div>
<div class="meter"><span style="width:{{ $row['stats']['percent'] }}%"></span></div>
<small class="muted">{{ $row['stats']['completed'] }} / {{ $row['stats']['total'] }} {{ $en ? 'completed' : 'tamamlandı' }} · {{ $en ? 'Status:' : 'Durum:' }} {{ $row['enrollment']->status }}</small>
@if($row['enrollment']->certificate)<div style="margin-top:8px"><a class="button secondary" href="{{ route('certificates.download', $row['enrollment']->certificate) }}">{{ $en ? 'Download certificate' : 'Sertifikayı indir' }}</a></div>@endif
</div>
@empty
<p class="muted">{{ $en ? "You aren't enrolled in any training yet." : 'Henüz kayıt olduğunuz bir eğitim yok.' }}</p>
@endforelse
{{ $enrollments->links('lms.partials.pagination') }}
</div>
</div>
</div>
@endsection
