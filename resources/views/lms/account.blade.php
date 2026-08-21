@extends('lms.layout')
@section('title','Hesap bilgileri · Arucad Campus LMS')
@section('content')
<div class="section-heading"><div><span class="eyebrow">HESABIM</span><h1>Hesap bilgileri</h1><p class="muted">Profil bilgileriniz ve eğitim geçmişiniz.</p></div></div>
<div class="grid" style="display:grid;gap:20px;grid-template-columns:280px 1fr;align-items:start">
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
<p class="muted" style="margin:0 0 18px">{{ ['admin'=>'Yönetici','instructor'=>'Eğitmen','learner'=>'Öğrenci'][$user->role] ?? $user->role }}</p>
@if($user->department)<p style="margin:4px 0"><b>Departman:</b> {{ $user->department }}</p>@endif
@if($user->job_title)<p style="margin:4px 0"><b>Unvan:</b> {{ $user->job_title }}</p>@endif
<hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
<button type="button" class="button-secondary button-danger" style="width:100%" onclick="document.getElementById('deleteAccountDialog').style.display='block'">Hesabımı sil</button>
<div id="deleteAccountDialog" class="danger-zone" style="display:none">
<p>Bu işlem geri alınamaz. Tüm kayıtlarınız ve sertifikalarınız silinecektir. Onaylamak için şifrenizi girin.</p>
<form method="post" action="{{ route('account.destroy') }}" onsubmit="return confirm('Hesabınızı kalıcı olarak silmek istediğinize emin misiniz?')">@csrf
<input type="password" name="password" placeholder="Şifreniz" required>
<button type="submit" class="button-danger" style="width:100%;margin-top:10px">Kalıcı olarak sil</button>
</form>
</div>
</div>
<div>
@if($courses->isNotEmpty())
<div class="card surface" style="padding:24px;margin-bottom:18px">
<h2 style="margin-top:0">Eğitmen olduğum kurslar</h2>
<ul style="list-style:none;padding:0;margin:0">
@foreach($courses as $course)<li style="padding:10px 0;border-top:1px solid var(--line)"><b>{{ $course->title }}</b> <span class="muted">· {{ $course->enrollments_count }} katılımcı</span></li>@endforeach
</ul>
</div>
@endif
<div class="card surface" style="padding:24px">
<h2 style="margin-top:0">Eğitimlerim</h2>
@forelse($enrollments as $row)
<div style="padding:14px 0;border-top:1px solid var(--line)">
<div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><b>{{ $row['enrollment']->course->title }}</b><span class="status">{{ $row['stats']['percent'] }}%</span></div>
<div class="meter"><span style="width:{{ $row['stats']['percent'] }}%"></span></div>
<small class="muted">{{ $row['stats']['completed'] }} / {{ $row['stats']['total'] }} tamamlandı · Durum: {{ $row['enrollment']->status }}</small>
@if($row['enrollment']->certificate)<div style="margin-top:8px"><a class="button secondary" href="{{ route('certificates.download', $row['enrollment']->certificate) }}">Sertifikayı indir</a></div>@endif
</div>
@empty
<p class="muted">Henüz kayıt olduğunuz bir eğitim yok.</p>
@endforelse
</div>
</div>
</div>
@endsection
