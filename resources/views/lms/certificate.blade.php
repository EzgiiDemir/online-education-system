<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans;margin:0;padding:0;color:#172033}
.cert{border:12px solid #4763e6;padding:50px 70px 30px;text-align:center}
h1{font-size:38px;color:#4763e6;margin:8px 0}
h2{font-size:26px;margin:22px 0}
.cert-footer{width:100%;margin-top:40px}
.cert-code{text-align:left;font-size:12px;color:#667}
.cert-code b{font-size:14px;color:#172033}
.verify-qr{text-align:right}
.verify-qr img{display:block;margin:0 0 0 auto}
.verify-qr .qr-caption{font-size:8px;color:#667;white-space:nowrap;margin:2px 0 0}
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
<td class="cert-code" width="50%">{{ $en ? 'Certificate No:' : 'Sertifika No:' }} <b>{{$certificate->certificate_number}}</b><br>{{ $en ? 'Issued on:' : 'Veriliş tarihi:' }} {{$certificate->issued_at->format('d.m.Y')}}</td>
<td class="verify-qr" width="50%"><img src="{{ \App\Support\QrCodeGenerator::dataUri(route('certificates.verify', $certificate->certificate_number)) }}" width="100" height="100"><div class="qr-caption">{{ $en ? 'Scan the QR code to verify the certificate' : 'Sertifikayı doğrulamak için QR kodu okutunuz' }}</div></td>
</tr></table>
</div>
</body></html>
