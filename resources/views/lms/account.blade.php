@php($accountLayout = match(auth()->user()->role) { 'instructor' => 'lms.instructor-layout', 'manager' => 'lms.manager-layout', default => 'lms.layout' })
@extends($accountLayout)
@section('title', (session('locale', 'tr') === 'en' ? 'Account' : 'Hesap bilgileri').' · '.(session('locale', 'tr') === 'en' ? 'Arucad Continuous Learning Center' : 'Arucad Sürekli Öğrenme Merkezi'))
@section('page-title', session('locale', 'tr') === 'en' ? 'Account' : 'Hesap bilgileri')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'MY ACCOUNT' : 'HESABIM' }}</span><h1>{{ $en ? 'Account' : 'Hesap bilgileri' }}</h1><p class="muted">{{ $en ? 'Your profile information and training history.' : 'Profil bilgileriniz ve eğitim geçmişiniz.' }}</p></div></div>
<div class="row g-4">
<div class="col-md-4 col-lg-3">
<div class="card surface p-4">
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
<h2 style="margin:0 0 12px;font-size:16px">{{ $en ? 'Email notifications' : 'E-posta bildirimleri' }}</h2>
<form method="post" action="{{ route('account.notification-preferences.update') }}">
@csrf
@foreach(\App\Models\User::OPTIONAL_NOTIFICATION_TYPES as $type => $label)
<div class="form-check" style="margin-bottom:8px">
<input class="form-check-input" type="checkbox" name="preferences[]" value="{{ $type }}" id="pref-{{ $type }}" {{ $user->wantsNotification($type) ? 'checked' : '' }}>
<label class="form-check-label" for="pref-{{ $type }}" style="font-size:14px">{{ $label }}</label>
</div>
@endforeach
<button type="submit" class="btn btn-outline-secondary" style="width:100%;margin-top:10px">{{ $en ? 'Save preferences' : 'Tercihleri kaydet' }}</button>
</form>
<hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
<button type="button" class="button-danger" style="width:100%" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">{{ $en ? 'Delete my account' : 'Hesabımı sil' }}</button>
</div>
</div>
<div class="col-md-8 col-lg-9">
@if($courses->isNotEmpty())
<div class="card surface p-4 mb-3">
<h2 style="margin-top:0">{{ $en ? 'Courses I teach' : 'Eğitmen olduğum kurslar' }}</h2>
<ul style="list-style:none;padding:0;margin:0">
@foreach($courses as $course)<li style="padding:10px 0;border-top:1px solid var(--line)"><b>{{ $course->title }}</b> <span class="muted">· {{ $course->enrollments_count }} {{ $en ? 'participants' : 'katılımcı' }}</span></li>@endforeach
</ul>
</div>
@endif
<div class="card surface p-4">
<h2 style="margin-top:0">{{ $en ? 'My Courses' : 'Eğitimlerim' }}</h2>
@forelse($enrollments as $row)
<div style="padding:14px 0;border-top:1px solid var(--line)">
<div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:10px"><b>{{ $row['enrollment']->course->title }}</b>
@if($row['enrollment']->isExempt())
<span class="badge" style="background:#eef1e9;color:var(--success-dark)">{{ $en ? 'Exempt' : 'Muaf' }}</span>
@else
<span class="badge">{{ $row['stats']['percent'] }}%</span>
@endif
</div>
@unless($row['enrollment']->isExempt())
<div class="meter"><span style="width:{{ $row['stats']['percent'] }}%"></span></div>
@endunless
<small class="muted">{{ $row['stats']['completed'] }} / {{ $row['stats']['total'] }} {{ $en ? 'completed' : 'tamamlandı' }} · {{ $en ? 'Status:' : 'Durum:' }} {{ $row['enrollment']->status }}</small>
@if($row['enrollment']->certificate?->isRevoked())<div style="margin-top:8px"><span class="badge" style="background:#fbe9e4;color:#b3402e">{{ $en ? 'Certificate revoked' : 'Sertifika iptal edildi' }}</span></div>@elseif($row['enrollment']->certificate)<div style="margin-top:8px"><a class="btn btn-outline-secondary" href="{{ route('certificates.download', $row['enrollment']->certificate) }}">{{ $en ? 'Download certificate' : 'Sertifikayı indir' }}</a></div>@endif
</div>
@empty
<p class="muted">{{ $en ? "You aren't enrolled in any training yet." : 'Henüz kayıt olduğunuz bir eğitim yok.' }}</p>
@endforelse
{{ $enrollments->links('lms.partials.pagination') }}
</div>
</div>
</div>

<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header">
<h2 class="modal-title h5" id="deleteAccountModalLabel">{{ $en ? 'Delete my account' : 'Hesabımı sil' }}</h2>
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ $en ? 'Close' : 'Kapat' }}"></button>
</div>
<div class="modal-body">
<div class="danger-zone">
<p>{{ $en ? 'This action cannot be undone. All your records and certificates will be deleted. Enter your password to confirm.' : 'Bu işlem geri alınamaz. Tüm kayıtlarınız ve sertifikalarınız silinecektir. Onaylamak için şifrenizi girin.' }}</p>
</div>
<form method="post" action="{{ route('account.destroy') }}" onsubmit="return confirm('{{ $en ? 'Are you sure you want to permanently delete your account?' : 'Hesabınızı kalıcı olarak silmek istediğinize emin misiniz?' }}')" class="mt-3">
@csrf
<label for="deleteAccountPassword" class="form-label">{{ $en ? 'Your password' : 'Şifreniz' }}</label>
<input type="password" id="deleteAccountPassword" name="password" class="form-control" placeholder="{{ $en ? 'Your password' : 'Şifreniz' }}" required>
<button type="submit" class="button-danger" style="width:100%;margin-top:14px">{{ $en ? 'Delete permanently' : 'Kalıcı olarak sil' }}</button>
</form>
</div>
</div>
</div>
</div>
@endsection
