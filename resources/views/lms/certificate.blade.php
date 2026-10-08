<!doctype html>
<html>
<head>
<meta charset="utf-8">
{{--
    Official ARUCAD "Katılım Belgesi". Geometry (mm, A4 landscape 297x210)
    and colours are measured from public/images/certificate-reference.png,
    which is the approved artwork this template reproduces:

        navy   #393E56      body grey  #6F727A
        top accent bar      0-6.3mm tall, 190.2mm wide, flush left
        bottom accent bar   6.3mm tall, from x=107.3mm, flush right
        frame               outer 10.4mm inset, inner hairline at 13mm
        logo                centred, top 28.3mm, 81.4mm wide
        title               72mm     name 104mm     body 117mm
        date                139mm    signature 176mm

    DomPDF notes: it has no flexbox and mis-handles content after a block
    <img>, so the layout is absolutely positioned and the QR sits in its own
    fixed box rather than in normal flow (see Faz 111/116).
--}}
<style>
@page { size: A4 landscape; margin: 0; }
html, body { margin: 0; padding: 0; width: 297mm; height: 210mm; }
body { font-family: DejaVu Sans, sans-serif; color: #6F727A; }
.page { position: relative; width: 297mm; height: 210mm; overflow: hidden; }

.bar-top    { position: absolute; top: 0; left: 0; width: 190.2mm; height: 6.3mm; background: #393E56; }
.bar-bottom { position: absolute; top: 203.7mm; left: 107.3mm; width: 189.7mm; height: 6.3mm; background: #393E56; }

.frame-outer { position: absolute; top: 10.4mm; left: 6.2mm; width: 284.0mm; height: 189.1mm; border: 1.7pt solid #393E56; }
.frame-inner { position: absolute; top: 13.3mm; left: 9.3mm; width: 278.0mm; height: 183.2mm; border: 0.85pt solid #393E56; }

.row { position: absolute; left: 20mm; width: 257mm; text-align: center; }

.logo-row { top: 28.3mm; }
.logo-row img { width: 81.4mm; height: 21.8mm; }

.title { top: 69mm; font-size: 30pt; font-weight: 400; letter-spacing: 4px; color: #393E56; }

.name      { top: 102.5mm; font-size: 15pt; font-weight: 700; color: #393E56; }
.name-rule { position: absolute; top: 111mm; left: 58.5mm; width: 180mm; border-top: 0.6pt solid #BFC1C7; }

.body { top: 116mm; font-size: 12.5pt; line-height: 1.6; }
.body strong { color: #393E56; }

.date { top: 137.5mm; font-size: 13pt; }
.date strong { color: #393E56; }

.sign-name { top: 174.5mm; font-size: 12pt; font-weight: 700; color: #393E56; }
.sign-rule { position: absolute; top: 182mm; left: 121mm; width: 55mm; border-top: 0.8pt solid #9FA1A6; }
.sign-title { top: 183mm; font-size: 10.5pt; }

/* Verification block: kept from the previous template so a certificate
   stays independently checkable, placed in the empty lower-left panel so
   it never collides with the official artwork above. */
/* Bottom-right corner, stacked. The inner frame ends at 196.5mm (13.3 + 183.2)
   and its right edge is at 287.3mm (9.3 + 278); this block occupies
   231–281mm x 160–190mm, so it clears the frame by ~6mm on both sides and
   cannot collide with the centred signature (its rule spans 121–176mm).
   break-all on the URL keeps a long production host inside the column
   instead of running over the border. */
.verify { position: absolute; top: 160mm; left: 231mm; width: 50mm; text-align: center; }
.verify img { display: block; margin: 0 auto 1.4mm; width: 17mm; height: 17mm; }
.verify p { margin: 0; font-size: 6pt; line-height: 1.3; color: #9FA1A6; word-break: break-all; }
.verify .no { font-size: 7.5pt; font-weight: 700; letter-spacing: 0.2px; color: #393E56; margin-bottom: 0.9mm; }
.verify .url { font-size: 5.5pt; margin-top: 0.7mm; }
</style>
</head>
<body>
@php($en = session('locale', 'tr') === 'en')
@php($settings = \App\Models\PortalSetting::current())
@php($verifyPage = request()->getSchemeAndHttpHost().'/verify')
@php($issuedAt = $certificate->issued_at)
@php($months = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'])
@php($issuedLong = $en
    ? $issuedAt->format('j F Y')
    : $issuedAt->format('j').' '.$months[$issuedAt->month - 1].' '.$issuedAt->format('Y'))
<div class="page">
    <div class="bar-top"></div>
    <div class="bar-bottom"></div>
    <div class="frame-outer"></div>
    <div class="frame-inner"></div>

    <div class="row logo-row"><img src="{{ public_path('images/arucad-certificate-logo.png') }}" alt="ARUCAD"></div>

    <div class="row title">{{ $en ? 'CERTIFICATE OF PARTICIPATION' : 'KATILIM BELGESİ' }}</div>

    <div class="row name">{{ $enrollment->user->name }}</div>
    <div class="name-rule"></div>

    <div class="row body">
        @if($en)
            Has participated in <strong>{{ $enrollment->course->title }}</strong>,<br>
            hosted by {{ $settings->issuerName() }}.
        @else
            {{ $settings->issuerName() }} ev sahipliğinde gerçekleşen<br>
            <strong>{{ $enrollment->course->title }}</strong>’{{ \App\Support\TurkishSuffix::dative($enrollment->course->title) }} katılmıştır.
        @endif
    </div>

    <div class="row date"><strong>{{ $issuedLong }}</strong></div>

    <div class="row sign-name">{{ $settings->signatoryName() }}</div>
    <div class="sign-rule"></div>
    <div class="row sign-title">{{ $settings->signatoryTitle() }}</div>

    <div class="verify">
        <img src="{{ \App\Support\QrCodeGenerator::dataUri(route('certificates.verify', $certificate->certificate_number), 80) }}" alt="">
        <p class="no">{{ $certificate->certificate_number }}</p>
        <p>{{ $en ? 'Scan to verify this certificate' : 'Doğrulamak için karekodu okutunuz' }}</p>
        <p class="url">{{ $verifyPage }}</p>
    </div>
</div>
</body>
</html>
