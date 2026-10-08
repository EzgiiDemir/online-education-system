@extends('lms.layout')
@section('title', (session('locale', 'tr') === 'en' ? 'Certificate verification' : 'Sertifika doğrulama').' · '.(session('locale', 'tr') === 'en' ? 'Arucad Training Center' : 'Arucad Eğitim Merkezi'))
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="viewer-wrap">
<div class="card surface viewer-card">
<span class="eyebrow">{{ $en ? 'CERTIFICATE VERIFICATION' : 'SERTİFİKA DOĞRULAMA' }}</span>
<h1>{{ $en ? 'Verify a certificate' : 'Sertifika doğrula' }}</h1>
<p class="muted">{{ $en ? 'Enter the certificate number printed on the certificate to check its validity.' : 'Geçerliliğini kontrol etmek için sertifikanın üzerindeki sertifika numarasını girin.' }}</p>
<form method="get" action="{{ route('certificates.verify.lookup') }}" class="report-filters">
<div class="field" style="flex:2"><label for="verify-number">{{ $en ? 'Certificate number' : 'Sertifika numarası' }}</label><input id="verify-number" type="text" name="number" placeholder="CERT-XXXXXXXXXX" required autofocus></div>
<div class="field" style="flex:0;align-self:flex-end"><button type="submit" class="btn btn-primary">{{ $en ? 'Verify' : 'Doğrula' }}</button></div>
</form>
</div>
</div>
@endsection
