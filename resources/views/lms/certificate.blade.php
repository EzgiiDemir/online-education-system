<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans;margin:0;padding:0;color:#172033}
.cert{border:12px solid #4763e6;padding:50px 70px 30px;text-align:center}
h1{font-size:38px;color:#4763e6;margin:8px 0}
h2{font-size:26px;margin:22px 0}
.cert-footer{margin-top:40px;text-align:left}
.cert-date{font-size:12px;color:#667}
.verify-qr{text-align:right;font-size:12px;color:#667;margin-top:16px}
.verify-qr b{font-size:14px;color:#172033}
.verify-qr img{margin:4px 0}
/* DomPDF pushes content after an <img> down by a fixed amount whenever any
   block-level content precedes the image's own container earlier in the
   document -- reproduced in total isolation, unrelated to tables/floats/
   div-vs-br. Confirmed empirically: -90px brings the caption flush under a
   130px QR code again; this is a measured compensation for that DomPDF
   quirk, not a meaningful design value on its own. */
.verify-qr .qr-caption{font-size:8px;display:inline-block;margin-top:-90px}
</style></head><body>
@php($en = session('locale', 'tr') === 'en')
<div class="cert">
<p>{{ $en ? 'Arucad Continuous Learning Center' : 'Arucad Sürekli Öğrenme Merkezi' }}</p>
<h1>{{ $en ? 'Certificate of Achievement' : 'Başarı Sertifikası' }}</h1>
<p>{{ $en ? 'This certificate confirms that' : 'Bu sertifika' }}</p>
<h2>{{$enrollment->user->name}}</h2>
<p>{{ $en ? 'has successfully completed the following course.' : 'kişisinin aşağıdaki eğitimi başarıyla tamamladığını onaylar.' }}</p>
<h2>{{$enrollment->course->title}}</h2>
<div class="cert-footer">
<div class="cert-date">{{ $en ? 'Issued on:' : 'Veriliş tarihi:' }} {{$certificate->issued_at->format('d.m.Y')}}</div>
<div class="verify-qr">
{{ $en ? 'Certificate No:' : 'Sertifika No:' }} <b>{{$certificate->certificate_number}}</b><br>
<img src="{{ \App\Support\QrCodeGenerator::dataUri(route('certificates.verify', $certificate->certificate_number), 130) }}" width="130" height="130"><br>
<span class="qr-caption">{{ $en ? 'Scan the QR code to verify the certificate' : 'Sertifikayı doğrulamak için QR kodu okutunuz' }}</span>
</div>
</div>
</div>
</body></html>
