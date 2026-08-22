@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Certificate verification' : 'Sertifika doğrulama').' · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="viewer-wrap">
<div class="card surface viewer-card">
<span class="eyebrow">{{ $en ? 'CERTIFICATE VERIFICATION' : 'SERTİFİKA DOĞRULAMA' }}</span>
<h1>{{ $certificateNumber }}</h1>
@if($certificate)
<div class="verify-result is-valid">
<span class="status" style="background:#eef1e9;color:var(--success-dark)">✓ {{ $en ? 'Valid certificate' : 'Geçerli sertifika' }}</span>
<div class="requirement-row"><span>{{ $en ? 'Holder' : 'Sahibi' }}</span><span><b>{{ $certificate->enrollment->user->name }}</b></span></div>
<div class="requirement-row"><span>{{ $en ? 'Course' : 'Eğitim' }}</span><span><b>{{ $certificate->enrollment->course->title }}</b></span></div>
<div class="requirement-row"><span>{{ $en ? 'Issued on' : 'Veriliş tarihi' }}</span><span>{{ $certificate->issued_at->format('d.m.Y') }}</span></div>
@if($certificate->expiresAt())
<div class="requirement-row"><span>{{ $en ? 'Valid until' : 'Geçerlilik tarihi' }}</span><span>{{ $certificate->expiresAt()->format('d.m.Y') }}</span></div>
@if($certificate->isExpired())
<p class="due-warning is-overdue">⚠ {{ $en ? 'This certificate has expired and needs recertification.' : 'Bu sertifikanın süresi dolmuştur, yenilenmesi gerekir.' }}</p>
@endif
@endif
</div>
@else
<div class="verify-result is-invalid">
<span class="status" style="background:#fbe9e4;color:#b3402e">✕ {{ $en ? 'Not found' : 'Bulunamadı' }}</span>
<p class="muted">{{ $en ? "We couldn't find a certificate with this number. Please check it and try again." : 'Bu numarayla eşleşen bir sertifika bulunamadı. Lütfen numarayı kontrol edip tekrar deneyin.' }}</p>
</div>
@endif
</div>
</div>
@endsection
