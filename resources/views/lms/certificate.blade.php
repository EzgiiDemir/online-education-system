<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans;margin:0;padding:0;color:#172033}
.cert{border:12px solid #4763e6;padding:70px 70px 34px;text-align:center}
h1{font-size:40px;color:#4763e6;margin:10px 0}
h2{font-size:28px;margin:30px 0}
.cert-footer{width:100%;margin-top:56px}
.cert-footer td{vertical-align:bottom}
.cert-code{text-align:left;font-size:12px;color:#667}
.cert-code b{font-size:14px;color:#172033}
.verify-qr{text-align:right;width:130px}
.verify-qr .qr-box{width:100px;height:100px;margin-left:auto}
.verify-qr small{display:block;font-size:10px;color:#667;margin-top:4px}
</style></head><body>
@php($en = session('locale', 'tr') === 'en')
<div class="cert">
<p>{{ $en ? 'Arucad Continuous Learning Center' : 'Arucad Sürekli Öğrenme Merkezi' }}</p>
<h1>{{ $en ? 'Certificate of Achievement' : 'Başarı Sertifikası' }}</h1>
<p>{{ $en ? 'This certificate confirms that' : 'Bu sertifika' }}</p>
<h2>{{$enrollment->user->name}}</h2>
<p>{{ $en ? 'has successfully completed the following course.' : 'kişisinin aşağıdaki eğitimi başarıyla tamamladığını onaylar.' }}</p>
<h2>{{$enrollment->course->title}}</h2>
<table class="cert-footer"><tr>
<td class="cert-code">{{ $en ? 'Certificate No:' : 'Sertifika No:' }} <b>{{$certificate->certificate_number}}</b><br>{{ $en ? 'Issued on:' : 'Veriliş tarihi:' }} {{$certificate->issued_at->format('d.m.Y')}}</td>
<td class="verify-qr"><div class="qr-box">{!! \App\Support\QrCodeGenerator::svg(route('certificates.verify', $certificate->certificate_number)) !!}</div><small>{{ $en ? 'Verification QR code' : 'Doğrulama QR kodu' }}</small></td>
</tr></table>
</div>
</body></html>
