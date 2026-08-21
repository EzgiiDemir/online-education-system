@extends('lms.instructor-layout')
@section('page-title','Genel bakış')
@section('content')
<div class="grid metrics-grid"><div class="card metric-card"><span class="tag">AKTİF EĞİTİMLER</span><strong>{{ $courses->where('status','published')->count() }}</strong><small>Yayınlanan eğitimleriniz</small></div><div class="card metric-card"><span class="tag">TOPLAM KATILIMCI</span><strong>{{ $courses->sum('enrollments_count') }}</strong><small>Tüm eğitimlerinizde</small></div><div class="card metric-card"><span class="tag">PLANLANAN SEANS</span><strong>{{ $courses->sum(fn($course)=>$course->sessions->count()) }}</strong><small>Online ve offline seans</small></div></div>
<div class="section-heading instructor-section-heading"><div><span class="eyebrow">BUGÜN</span><h2>Bugünün eylemleri</h2></div></div>
<div class="today-grid">
@if($upcomingSession)
<div class="today-card surface"><span class="status">SIRADAKİ SEANS</span><h3>{{ $upcomingSession->title }}</h3><p class="muted">{{ $upcomingSession->course->title }} · {{ $upcomingSession->starts_at?->format('d.m.Y H:i') }}</p></div>
@endif
@if($sessionsNeedingAttendanceReview->isNotEmpty())
<div class="today-card surface"><span class="status">YOKLAMA KONTROLÜ</span><h3>{{ $sessionsNeedingAttendanceReview->count() }} seans incelenmeli</h3><p class="muted">Son 7 günde katılımı işaretlenmemiş seanslar var.</p><ul class="curriculum-list">@foreach($sessionsNeedingAttendanceReview->take(3) as $needsReview)<li><span>{{ $needsReview->title }} — {{ $needsReview->course->title }}</span><a class="text-link" href="{{ route('instructor.courses.show', $needsReview->course) }}">İncele →</a></li>@endforeach</ul></div>
@endif
@if($incompleteLearnersCount > 0)
<div class="today-card surface"><span class="status">DEVAM EDEN ÖĞRENCİ</span><h3>{{ $incompleteLearnersCount }}</h3><p class="muted">Henüz tamamlamamış katılımcı sayısı.</p></div>
@endif
@if($newEnrollmentsCount > 0)
<div class="today-card surface"><span class="status">YENİ KAYIT</span><h3>{{ $newEnrollmentsCount }}</h3><p class="muted">Son 7 günde yapılan yeni kayıtlar.</p></div>
@endif
@if(!$upcomingSession && $sessionsNeedingAttendanceReview->isEmpty() && $incompleteLearnersCount === 0 && $newEnrollmentsCount === 0)
<div class="today-card surface"><p class="muted" style="margin:0">Şu an dikkat gerektiren bir şey yok.</p></div>
@endif
</div>
<div class="section-heading instructor-section-heading" id="courses"><div><span class="eyebrow">İÇERİK YÖNETİMİ</span><h2>Eğitimlerim</h2></div><a class="button" href="#new-course">+ Yeni eğitim planla</a></div>
<div class="grid course-management-grid">@forelse($courses as $course)<article class="card management-card"><div class="course-card-top"><span class="status">{{ $course->delivery_type === 'online' ? 'CANLI SINIF' : 'İÇERİK TABANLI' }}</span><span class="muted">{{ strtoupper($course->language ?? 'tr') }}</span></div><h3>{{ $course->title }}</h3><p class="muted">{{ $course->description }}</p><div class="course-meta"><span>{{ $course->sessions->count() }} seans</span><span>•</span><span>{{ $course->enrollments_count }} katılımcı</span>@if($course->enrollments_count > 0)<span>•</span><span>%{{ (int) round($course->completed_enrollments_count / $course->enrollments_count * 100) }} tamamlama</span>@endif</div><a class="text-link" href="{{ route('instructor.courses.show',$course) }}">Yönet ve ayrıntıları gör <span>→</span></a></article>@empty<div class="card empty-state">Henüz eğitim planlamadınız.<br><a class="text-link" href="#new-course">İlk eğitiminizi planlayın →</a></div>@endforelse</div>
<section id="new-course" class="card course-form-card">
<div class="form-heading"><div><span class="eyebrow">YENİ İÇERİK</span><h2>Yeni eğitim planla</h2><p class="muted">Eğitimin temel bilgilerini ve yayın ayarlarını tanımlayın.</p></div></div>
<div class="wizard-steps" id="wizard-steps">
<span class="wizard-dot" data-goto="0">1. Temel bilgiler</span>
<span class="wizard-dot" data-goto="1">2. Misafir eğitimci</span>
<span class="wizard-dot" data-goto="2">3. Yayın ve görseller</span>
</div>
<form class="course-form" method="post" action="{{ route('instructor.courses.store') }}" enctype="multipart/form-data" id="course-wizard-form">
@csrf
<fieldset class="wizard-step" data-step="0"><div class="form-section-title">Temel bilgiler</div><div class="form-grid"><div class="field full"><label for="title">Eğitim adı <i>*</i></label><input id="title" name="title" placeholder="Örn. Etkili iletişim atölyesi" required></div><div class="field full"><label for="description">Eğitim açıklaması</label><textarea id="description" name="description" rows="3" placeholder="Eğitimin kapsamını ve hedeflerini kısaca açıklayın"></textarea></div><div class="field"><label for="delivery_type">Eğitim türü</label><select id="delivery_type" name="delivery_type"><option value="offline">Offline / içerik tabanlı</option><option value="online">Online / portal içi canlı sınıf</option></select></div><div class="field"><label for="language">Eğitim dili</label><select id="language" name="language"><option value="tr">Türkçe</option><option value="en">English</option></select></div><div class="field"><label for="starts_at">Başlangıç tarihi</label><input id="starts_at" name="starts_at" type="datetime-local"></div><div class="field"><label for="ends_at">Bitiş tarihi</label><input id="ends_at" name="ends_at" type="datetime-local"></div></div></fieldset>
<fieldset class="wizard-step" data-step="1" hidden><div class="form-section-title">Misafir eğitimci <span>İsteğe bağlı</span></div><div class="form-grid"><div class="field"><label for="guest_instructor_name">Ad soyad</label><input id="guest_instructor_name" name="guest_instructor_name" placeholder="Misafir eğitimci adı"></div><div class="field"><label for="guest_instructor_email">E-posta</label><input id="guest_instructor_email" name="guest_instructor_email" type="email" placeholder="egitmen@kurum.com"></div><div class="field full"><label for="guest_instructor_bio">Kısa biyografi</label><textarea id="guest_instructor_bio" name="guest_instructor_bio" rows="2" placeholder="Uzmanlık alanı ve kısa tanıtım"></textarea></div></div></fieldset>
<fieldset class="wizard-step" data-step="2" hidden><div class="form-section-title">Yayın ve görseller</div><div class="form-grid"><label class="check-field full"><input name="certificate_enabled" type="checkbox" value="1" checked><span><b>Sertifika üret</b><small>Katılımcı tüm zorunlu içerikleri tamamladığında otomatik sertifika oluştur.</small></span></label><div class="field"><label for="poster">Afiş</label><input id="poster" name="poster" type="file" accept="image/*"></div><div class="field"><label for="image">Kapak görseli</label><input id="image" name="image" type="file" accept="image/*"></div></div>
<div class="wizard-checklist"><span id="wc-title">○ Eğitim adı girildi</span><span>✓ Yayın ayarları hazır</span></div>
</fieldset>
<div class="form-actions"><button type="button" class="button-secondary" id="wizard-back" style="display:none">← Geri</button><span class="muted">Daha sonra eğitim ayrıntılarından seans ve materyal ekleyebilirsiniz.</span><button type="button" class="button" id="wizard-next">İleri →</button><button type="submit" id="wizard-submit" style="display:none">Eğitimi yayınla <span>→</span></button></div>
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
        if (titleCheck) titleCheck.textContent = (titleInput.value.trim() ? '✓' : '○') + ' Eğitim adı girildi';
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
