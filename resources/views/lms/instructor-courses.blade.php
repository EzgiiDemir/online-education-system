@extends('lms.instructor-layout')
@section('page-title', session('locale', 'tr') === 'en' ? 'My Courses' : 'Eğitimlerim')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading instructor-section-heading"><div><span class="eyebrow">{{ $en ? 'CONTENT MANAGEMENT' : 'İÇERİK YÖNETİMİ' }}</span><h2>{{ $en ? 'My Courses' : 'Eğitimlerim' }}</h2></div><a class="button" href="#new-course">+ {{ $en ? 'Plan a new course' : 'Yeni eğitim planla' }}</a></div>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">@forelse($courses as $course)<article class="card management-card" style="--course-accent:var(--course-accent-{{ $course->id % 6 }});border-left:4px solid var(--course-accent)"><div class="course-card-top"><span class="status">{{ $course->delivery_type === 'online' ? ($en ? 'LIVE CLASS' : 'CANLI SINIF') : ($en ? 'SELF-PACED' : 'İÇERİK TABANLI') }}</span><span class="muted">{{ strtoupper($course->language ?? 'tr') }}</span></div><h3>{{ $course->title }}</h3><p class="muted">{{ $course->description }}</p><div class="course-meta"><span>{{ $course->sessions->count() }} {{ $en ? 'sessions' : 'seans' }}</span><span>•</span><span>{{ $course->enrollments_count }} {{ $en ? 'participants' : 'katılımcı' }}</span>@if($course->enrollments_count > 0)<span>•</span><span>%{{ (int) round($course->completed_enrollments_count / $course->enrollments_count * 100) }} {{ $en ? 'complete' : 'tamamlama' }}</span>@endif</div><a class="text-link" href="{{ route('instructor.courses.show',$course) }}">{{ $en ? 'Manage and view details' : 'Yönet ve ayrıntıları gör' }} <x-lucide name="arrow-right" size="14" /></a></article>@empty<div class="card empty-state">{{ $en ? "You haven't planned a course yet." : 'Henüz eğitim planlamadınız.' }}<br><a class="text-link" href="#new-course">{{ $en ? 'Plan your first course →' : 'İlk eğitiminizi planlayın →' }}</a></div>@endforelse</div>
<section id="new-course" class="card course-form-card">
<div class="form-heading"><div><span class="eyebrow">{{ $en ? 'NEW CONTENT' : 'YENİ İÇERİK' }}</span><h2>{{ $en ? 'Plan a new course' : 'Yeni eğitim planla' }}</h2><p class="muted">{{ $en ? "Define the course's basic information and publishing settings." : 'Eğitimin temel bilgilerini ve yayın ayarlarını tanımlayın.' }}</p></div></div>
<div class="wizard-steps" id="wizard-steps">
<span class="wizard-dot" data-goto="0">1. {{ $en ? 'Basics' : 'Temel bilgiler' }}</span>
<span class="wizard-dot" data-goto="1">2. {{ $en ? 'Guest instructor' : 'Misafir eğitimci' }}</span>
<span class="wizard-dot" data-goto="2">3. {{ $en ? 'Publishing & images' : 'Yayın ve görseller' }}</span>
</div>
<form class="course-form" method="post" action="{{ route('instructor.courses.store') }}" enctype="multipart/form-data" id="course-wizard-form">
@csrf
<fieldset class="wizard-step" data-step="0"><div class="form-section-title">{{ $en ? 'Basics' : 'Temel bilgiler' }}</div><div class="form-grid"><div class="field full"><label for="title" class="form-label">{{ $en ? 'Course name' : 'Eğitim adı' }} <i>*</i></label><input id="title" name="title" class="form-control" placeholder="{{ $en ? 'e.g. Effective communication workshop' : 'Örn. Etkili iletişim atölyesi' }}" required></div><div class="field full"><label for="description" class="form-label">{{ $en ? 'Course description' : 'Eğitim açıklaması' }}</label><textarea id="description" name="description" class="form-control" rows="3" placeholder="{{ $en ? 'Briefly describe the scope and goals of the course' : 'Eğitimin kapsamını ve hedeflerini kısaca açıklayın' }}"></textarea></div><div class="field"><label for="delivery_type" class="form-label">{{ $en ? 'Course type' : 'Eğitim türü' }}</label><select id="delivery_type" name="delivery_type" class="form-select"><option value="offline">{{ $en ? 'Offline / self-paced' : 'Offline / içerik tabanlı' }}</option><option value="online">{{ $en ? 'Online / in-portal live class' : 'Online / portal içi canlı sınıf' }}</option></select></div><div class="field"><label for="language" class="form-label">{{ $en ? 'Course language' : 'Eğitim dili' }}</label><select id="language" name="language" class="form-select"><option value="tr">Türkçe</option><option value="en">English</option></select></div><div class="field"><label for="starts_at" class="form-label">{{ $en ? 'Start date' : 'Başlangıç tarihi' }}</label><input id="starts_at" name="starts_at" type="datetime-local" class="form-control"></div><div class="field"><label for="ends_at" class="form-label">{{ $en ? 'End date' : 'Bitiş tarihi' }}</label><input id="ends_at" name="ends_at" type="datetime-local" class="form-control"></div></div></fieldset>
<fieldset class="wizard-step" data-step="1" hidden><div class="form-section-title">{{ $en ? 'Guest instructor' : 'Misafir eğitimci' }} <span>{{ $en ? 'Optional' : 'İsteğe bağlı' }}</span></div><div class="form-grid"><div class="field"><label for="guest_instructor_name" class="form-label">{{ $en ? 'Full name' : 'Ad soyad' }}</label><input id="guest_instructor_name" name="guest_instructor_name" class="form-control" placeholder="{{ $en ? 'Guest instructor name' : 'Misafir eğitimci adı' }}"></div><div class="field"><label for="guest_instructor_email" class="form-label">{{ $en ? 'Email' : 'E-posta' }}</label><input id="guest_instructor_email" name="guest_instructor_email" type="email" class="form-control" placeholder="egitmen@kurum.com"></div><div class="field full"><label for="guest_instructor_bio" class="form-label">{{ $en ? 'Short bio' : 'Kısa biyografi' }}</label><textarea id="guest_instructor_bio" name="guest_instructor_bio" class="form-control" rows="2" placeholder="{{ $en ? 'Area of expertise and a brief introduction' : 'Uzmanlık alanı ve kısa tanıtım' }}"></textarea></div></div></fieldset>
<fieldset class="wizard-step" data-step="2" hidden><div class="form-section-title">{{ $en ? 'Publishing & images' : 'Yayın ve görseller' }}</div><div class="form-grid"><label class="check-field full"><input name="certificate_enabled" type="checkbox" value="1" checked><span><b>{{ $en ? 'Generate certificate' : 'Sertifika üret' }}</b><small>{{ $en ? 'Automatically issue a certificate once a participant completes all required content.' : 'Katılımcı tüm zorunlu içerikleri tamamladığında otomatik sertifika oluştur.' }}</small></span></label><div class="field"><label for="poster" class="form-label">{{ $en ? 'Poster' : 'Afiş' }}</label><input id="poster" name="poster" type="file" class="form-control" accept="image/*"></div><div class="field"><label for="image" class="form-label">{{ $en ? 'Cover image' : 'Kapak görseli' }}</label><input id="image" name="image" type="file" class="form-control" accept="image/*"></div></div>
<div class="wizard-checklist"><span id="wc-title"><x-lucide name="circle" size="14" /> {{ $en ? 'Course name entered' : 'Eğitim adı girildi' }}</span><span><x-lucide name="check" size="14" /> {{ $en ? 'Publishing settings ready' : 'Yayın ayarları hazır' }}</span></div>
</fieldset>
<div class="form-actions"><button type="button" class="button-secondary" id="wizard-back" style="display:none"><x-lucide name="arrow-left" size="14" /> {{ $en ? 'Back' : 'Geri' }}</button><span class="muted">{{ $en ? 'You can add sessions and materials from the course details later.' : 'Daha sonra eğitim ayrıntılarından seans ve materyal ekleyebilirsiniz.' }}</span><button type="button" class="button" id="wizard-next">{{ $en ? 'Next' : 'İleri' }} <x-lucide name="arrow-right" size="14" /></button><button type="submit" id="wizard-submit" style="display:none">{{ $en ? 'Publish course' : 'Eğitimi yayınla' }} <x-lucide name="arrow-right" size="14" /></button></div>
</form>
</section>
<script>
(function(){
    var form = document.getElementById('course-wizard-form');
    if (!form) return;
    var steps = Array.prototype.slice.call(form.querySelectorAll('.wizard-step'));
    var dots = Array.prototype.slice.call(document.querySelectorAll('#wizard-steps .wizard-dot'));
    var backBtn = document.getElementById('wizard-back');
    var nextBtn = document.getElementById('wizard-next');
    var submitBtn = document.getElementById('wizard-submit');
    var titleInput = document.getElementById('title');
    var titleCheck = document.getElementById('wc-title');
    var titleCheckLabel = {{ \Illuminate\Support\Js::from($en ? 'Course name entered' : 'Eğitim adı girildi') }};
    var checkIconHtml = {!! json_encode(\App\Support\Icon::svg('check', 14)) !!};
    var circleIconHtml = {!! json_encode(\App\Support\Icon::svg('circle', 14)) !!};
    var current = 0;
    function render(){
        steps.forEach(function(s){ s.hidden = Number(s.dataset.step) !== current; });
        dots.forEach(function(d,i){
            d.classList.toggle('is-active', i === current);
            d.classList.toggle('is-done', i < current);
        });
        backBtn.style.display = current === 0 ? 'none' : '';
        nextBtn.style.display = current === steps.length - 1 ? 'none' : '';
        submitBtn.style.display = current === steps.length - 1 ? '' : 'none';
        if (titleCheck) titleCheck.innerHTML = (titleInput.value.trim() ? checkIconHtml : circleIconHtml) + ' ' + titleCheckLabel;
    }
    nextBtn.addEventListener('click', function(){
        if (current === 0 && titleInput && !titleInput.value.trim()) { titleInput.reportValidity(); return; }
        if (current < steps.length - 1) { current++; render(); }
    });
    backBtn.addEventListener('click', function(){ if (current > 0) { current--; render(); } });
    dots.forEach(function(d,i){ d.addEventListener('click', function(){ current = i; render(); }); });
    render();
})();
</script>
@endsection
