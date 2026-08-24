@extends('lms.instructor-layout') @section('page-title',$course->title) @section('content')
@php($en = session('locale', 'tr') === 'en')
@php($statusLabels = ['draft' => $en ? 'Draft' : 'Taslak', 'published' => $en ? 'Published' : 'Yayında', 'completed' => $en ? 'Completed' : 'Tamamlandı'])

<div class="section-heading instructor-section-heading">
<div><span class="eyebrow">{{ strtoupper($course->delivery_type) }} · {{ $statusLabels[$course->status] ?? $course->status }}</span><h1 style="margin:0">{{ $course->title }}</h1></div>
<a class="btn btn-outline-secondary" href="{{ route('courses.show', $course) }}" target="_blank" rel="noopener">{{ $en ? 'Preview as learner' : 'Öğrenci olarak önizle' }} <x-lucide name="external-link" size="14" /></a>
</div>

<ul class="nav nav-tabs" id="course-workspace-tabs">
<li class="nav-item"><a class="nav-link is-active" href="#overview" data-tab="overview">{{ $en ? 'Overview' : 'Genel bakış' }}</a></li>
<li class="nav-item"><a class="nav-link" href="#curriculum" data-tab="curriculum">{{ $en ? 'Curriculum' : 'Müfredat' }}</a></li>
<li class="nav-item"><a class="nav-link" href="#students" data-tab="students">{{ $en ? 'Students' : 'Öğrenciler' }} <span class="tab-count">{{ $course->currentEnrollments->count() }}</span></a></li>
<li class="nav-item"><a class="nav-link" href="#sessions" data-tab="sessions">{{ $en ? 'Sessions' : 'Seanslar' }}</a></li>
<li class="nav-item"><a class="nav-link" href="#assessments" data-tab="assessments">{{ $en ? 'Assessments' : 'Değerlendirmeler' }}</a></li>
<li class="nav-item"><a class="nav-link" href="#analytics" data-tab="analytics">{{ $en ? 'Analytics' : 'Analitik' }}</a></li>
<li class="nav-item"><a class="nav-link" href="#settings" data-tab="settings">{{ $en ? 'Settings' : 'Ayarlar' }}</a></li>
<li class="nav-item" style="margin-left:auto"><a class="nav-link" href="{{ route('instructor.courses.log', $course) }}"><x-lucide name="clipboard-list" size="14" /> {{ $en ? 'Activity log' : 'İşlem kaydı' }}</a></li>
</ul>

{{-- ============ OVERVIEW ============ --}}
<div data-tab-panel="overview">
<div class="row row-cols-1 row-cols-lg-2 g-4 mt-1">
<div class="card"><span class="tag">{{strtoupper($course->delivery_type)}} · {{$course->language==='en'?'ENGLISH':'TÜRKÇE'}}</span><h2>{{$course->title}}</h2><p>{{$course->description}}</p><p><b>{{ $en ? 'Instructor:' : 'Eğitmen:' }}</b> {{$course->instructor->name}}<br><b>{{ $en ? 'Guest instructor:' : 'Misafir eğitimci:' }}</b> {{$course->guest_instructor_name ?: ($en ? 'None' : 'Yok')}}<br><b>{{ $en ? 'Certificate:' : 'Sertifika:' }}</b> {{$course->certificate_enabled?($en?'Active':'Aktif'):($en?'Inactive':'Pasif')}}<br><b>SDG:</b> {{implode(', ',$course->sdg_goals ?: []) ?: ($en ? 'Not set' : 'Belirlenmedi')}}</p></div>
<div class="card">
<h2>{{ $en ? 'Course health' : 'Eğitim sağlığı' }}</h2>
<ul class="list-group list-group-flush">
<li class="list-group-item requirement-row"><span>{{ $en ? 'Content' : 'İçerik' }}</span>{!! $courseHealth['content'] ? '<span class="badge" style="background:#eaf0e4;color:#4d6b2f">'.($en?'Ready':'Hazır').'</span>' : '<span class="badge" style="background:#fdf3df;color:#8a6d1d">'.($en?'Missing':'Eksik').'</span>' !!}</li>
<li class="list-group-item requirement-row"><span>{{ $en ? 'Assessment' : 'Değerlendirme' }}</span>{!! $courseHealth['assessment'] ? '<span class="badge" style="background:#eaf0e4;color:#4d6b2f">'.($en?'Ready':'Hazır').'</span>' : '<span class="badge" style="background:#fdf3df;color:#8a6d1d">'.($en?'Missing':'Eksik').'</span>' !!}</li>
<li class="list-group-item requirement-row"><span>{{ $en ? 'Schedule' : 'Program' }}</span>{!! $courseHealth['schedule'] ? '<span class="badge" style="background:#eaf0e4;color:#4d6b2f">'.($en?'Ready':'Hazır').'</span>' : '<span class="badge" style="background:#fdf3df;color:#8a6d1d">'.($en?'Missing':'Eksik').'</span>' !!}</li>
</ul>
<div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
<button type="button" class="btn btn-outline-secondary" data-tab-jump="curriculum">{{ $en ? 'Add content' : 'İçerik ekle' }}</button>
<button type="button" class="btn btn-outline-secondary" data-tab-jump="sessions">{{ $en ? 'Schedule session' : 'Seans planla' }}</button>
<button type="button" class="btn btn-outline-secondary" data-tab-jump="students">{{ $en ? 'View students' : 'Öğrencileri gör' }}</button>
<button type="button" class="btn btn-outline-secondary" data-tab-jump="analytics">{{ $en ? 'View analytics' : 'Analitiği gör' }}</button>
</div>
</div>
</div>
<div class="row row-cols-2 row-cols-md-4 g-4 mt-1">
<div class="card metric-card"><span class="tag">{{ $en ? 'ENROLLMENTS' : 'KAYIT' }}</span><strong>{{ $stats['enrollments'] }}</strong></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'COMPLETED' : 'TAMAMLANAN' }}</span><strong>{{ $stats['completed'] }}</strong></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'SESSIONS' : 'SEANS' }}</span><strong>{{ $stats['sessions'] }}</strong></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'ATTENDANCE (MIN)' : 'KATILIM (DK)' }}</span><strong>{{ $stats['attendance_minutes'] }}</strong></div>
</div>
</div>

{{-- ============ CURRICULUM ============ --}}
<div data-tab-panel="curriculum" hidden>
<div class="card" style="margin-top:20px" id="curriculum-builder">
<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px"><div><h2>{{ $en ? 'Curriculum' : 'Müfredat' }}</h2>
<p class="muted" style="margin-top:-8px">{{ $en ? 'Drag sections and activities to reorder them. Quiz questions are added from the admin panel.' : 'Bölüm ve etkinlikleri sürükleyerek sıralayabilirsiniz. Sınav (quiz) soruları admin panelinden eklenir.' }}</p></div>
</div>
<div id="sections-list">
@foreach($course->sections as $section)
<div class="builder-section" draggable="true" data-section-id="{{$section->id}}">
<div class="builder-section-head"><span class="drag-handle" aria-hidden="true"><x-lucide name="grip-vertical" size="16" /></span><span class="reorder-buttons"><button type="button" class="reorder-btn" data-move="up" aria-label="{{ $en ? 'Move section up' : 'Bölümü yukarı taşı' }}"><x-lucide name="chevron-up" size="14" /></button><button type="button" class="reorder-btn" data-move="down" aria-label="{{ $en ? 'Move section down' : 'Bölümü aşağı taşı' }}"><x-lucide name="chevron-down" size="14" /></button></span><h3 style="margin:0;flex:1">{{$section->title}}</h3>
<button type="button" class="text-link" onclick="document.getElementById('edit-section-{{$section->id}}').style.display='block'">{{ $en ? 'Edit' : 'Düzenle' }}</button>
<form method="post" action="{{route('builder.sections.destroy',$section)}}" onsubmit="return confirm('{{ $en ? 'This section and all its activities will be deleted. Are you sure?' : 'Bu bölüm ve içindeki tüm etkinlikler silinecek. Emin misiniz?' }}')" class="inline-form">@csrf<input type="hidden" name="_method" value="DELETE"><button type="submit" class="text-link" style="color:#b3402e">{{ $en ? 'Delete' : 'Sil' }}</button></form>
</div>
<form method="post" action="{{route('builder.sections.update',$section)}}" id="edit-section-{{$section->id}}" style="display:none;margin:10px 0">@csrf<input name="title" value="{{$section->title}}" aria-label="{{ $en ? 'Section title' : 'Bölüm başlığı' }}" required><textarea name="description" rows="2" placeholder="{{ $en ? 'Description (optional)' : 'Açıklama (opsiyonel)' }}" aria-label="{{ $en ? 'Section description' : 'Bölüm açıklaması' }}">{{$section->description}}</textarea><button type="submit" class="btn btn-primary">{{ $en ? 'Save' : 'Kaydet' }}</button></form>
@if($section->description)<p class="muted">{{$section->description}}</p>@endif
<ul class="builder-activity-list" data-activities data-section-id="{{$section->id}}">
@forelse($section->activities as $activity)
<li draggable="true" data-activity-id="{{$activity->id}}"><span class="drag-handle" aria-hidden="true"><x-lucide name="grip-vertical" size="16" /></span><span class="reorder-buttons"><button type="button" class="reorder-btn" data-move="up" aria-label="{{ $en ? 'Move activity up' : 'Etkinliği yukarı taşı' }}"><x-lucide name="chevron-up" size="14" /></button><button type="button" class="reorder-btn" data-move="down" aria-label="{{ $en ? 'Move activity down' : 'Etkinliği aşağı taşı' }}"><x-lucide name="chevron-down" size="14" /></button></span><span>{{$activity->title}}</span> <small class="muted">({{$activity->type}}@if(!$activity->is_required) · {{ $en ? 'optional' : 'opsiyonel' }} @endif)</small>
@if($activity->type==='assignment')<a class="text-link" href="{{route('instructor.activities.submissions',$activity)}}">{{ $en ? 'Submissions →' : 'Teslimler →' }}</a>@endif
@if($activity->path)<a class="text-link" href="{{asset('storage/'.$activity->path)}}" target="_blank" rel="noopener">{{ $en ? 'Preview' : 'Önizle' }}</a>@endif
<button type="button" class="text-link" onclick="document.getElementById('edit-activity-{{$activity->id}}').style.display='block'">{{ $en ? 'Edit' : 'Düzenle' }}</button>
@if($activity->type!=='quiz')<form method="post" action="{{route('builder.activities.destroy',$activity)}}" onsubmit="return confirm('{{ $en ? 'Delete this activity?' : 'Bu etkinlik silinsin mi?' }}')" class="inline-form">@csrf<input type="hidden" name="_method" value="DELETE"><button type="submit" class="text-link" style="color:#b3402e">{{ $en ? 'Delete' : 'Sil' }}</button></form>@endif
<form method="post" action="{{route('builder.activities.update',$activity)}}" id="edit-activity-{{$activity->id}}" style="display:none;margin:10px 0;flex-basis:100%">@csrf<input name="title" value="{{$activity->title}}" aria-label="{{ $en ? 'Activity title' : 'Etkinlik başlığı' }}" required><textarea name="description" rows="2" placeholder="{{ $en ? 'Description (optional)' : 'Açıklama (opsiyonel)' }}" aria-label="{{ $en ? 'Activity description' : 'Etkinlik açıklaması' }}">{{$activity->description}}</textarea><label class="check-field"><input type="checkbox" name="is_required" value="1" {{$activity->is_required?'checked':''}}> {{ $en ? 'Required' : 'Zorunlu' }}</label><button type="submit" class="btn btn-primary">{{ $en ? 'Save' : 'Kaydet' }}</button></form>
</li>
@empty
<li class="muted">{{ $en ? 'No activities in this section yet.' : 'Bu bölümde henüz etkinlik yok.' }}</li>
@endforelse
</ul>
<details class="builder-add-activity"><summary>+ {{ $en ? 'Add activity' : 'Etkinlik ekle' }}</summary>
<form method="post" action="{{route('builder.activities.store',$section)}}">@csrf
<input name="title" placeholder="{{ $en ? 'Activity name' : 'Etkinlik adı' }}" required>
<select name="type"><option value="video">{{ $en ? 'Video' : 'Video' }}</option><option value="document">{{ $en ? 'Document' : 'Doküman' }}</option><option value="presentation">{{ $en ? 'Presentation' : 'Sunum' }}</option><option value="resource">{{ $en ? 'Resource' : 'Kaynak' }}</option><option value="assignment">{{ $en ? 'Assignment' : 'Ödev' }}</option></select>
<label class="check-field"><input type="checkbox" name="is_required" value="1" checked> {{ $en ? 'Required' : 'Zorunlu' }}</label>
<button type="submit" class="btn btn-primary">{{ $en ? 'Add' : 'Ekle' }}</button>
</form>
</details>
</div>
@endforeach
</div>
<details class="builder-add-section"><summary>+ {{ $en ? 'Add section' : 'Bölüm ekle' }}</summary>
<form method="post" action="{{route('builder.sections.store',$course)}}">@csrf<input name="title" placeholder="{{ $en ? 'Section name' : 'Bölüm adı' }}" required><textarea name="description" rows="2" placeholder="{{ $en ? 'Description (optional)' : 'Açıklama (opsiyonel)' }}"></textarea><button type="submit" class="btn btn-primary">{{ $en ? 'Add section' : 'Bölüm ekle' }}</button></form>
</details>
</div>
</div>

{{-- ============ STUDENTS ============ --}}
<div data-tab-panel="students" hidden>
<div class="card" style="margin-top:20px">
<h2>{{ $en ? 'Students' : 'Öğrenciler' }} ({{$course->currentEnrollments->count()}})</h2>
@if($participants->isEmpty())
<p class="muted">{{ $en ? 'No participants yet.' : 'Henüz katılımcı yok.' }}</p>
@else
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:13px">
<thead><tr style="text-align:left">
<th style="padding:8px;border-bottom:1px solid var(--line)">{{ $en ? 'Student' : 'Öğrenci' }}</th>
<th style="padding:8px;border-bottom:1px solid var(--line)">{{ $en ? 'Progress' : 'İlerleme' }}</th>
<th style="padding:8px;border-bottom:1px solid var(--line)">{{ $en ? 'Quiz' : 'Quiz' }}</th>
<th style="padding:8px;border-bottom:1px solid var(--line)">{{ $en ? 'Assignments' : 'Ödevler' }}</th>
<th style="padding:8px;border-bottom:1px solid var(--line)">{{ $en ? 'Last activity' : 'Son işlem' }}</th>
</tr></thead>
<tbody>
@foreach($participants as $enrollment)
<tr>
<td style="padding:8px;border-bottom:1px solid var(--line)"><b>{{$enrollment->user->name}}</b><br><small class="muted">{{$enrollment->user->email}}</small></td>
<td style="padding:8px;border-bottom:1px solid var(--line)">%{{ $enrollment->completion_percent }}</td>
<td style="padding:8px;border-bottom:1px solid var(--line)">{{ $enrollment->quiz_attempts_avg_score !== null ? '%'.round($enrollment->quiz_attempts_avg_score) : '—' }}</td>
<td style="padding:8px;border-bottom:1px solid var(--line)">{{ $enrollment->assignment_submissions_count }}</td>
<td style="padding:8px;border-bottom:1px solid var(--line)">{{ $enrollment->last_activity_at?->diffForHumans() ?? ($en ? 'No activity' : 'Henüz yok') }}</td>
</tr>
@endforeach
</tbody>
</table>
</div>
{{ $participants->links('lms.partials.pagination') }}
@endif
</div>
</div>

{{-- ============ SESSIONS ============ --}}
<div data-tab-panel="sessions" hidden>
@if($course->runs->isNotEmpty())<div class="card" style="margin-top:20px"><h2>{{ $en ? 'Runs' : 'Dönemler' }}</h2><ul>@foreach($course->runs as $run)<li><b>{{$run->name}}</b> ({{$run->code}}) · {{$run->status}} · {{$run->starts_at?->format('d.m.Y') ?: '-'}} - {{$run->ends_at?->format('d.m.Y') ?: '-'}} · {{$course->currentEnrollments->where('course_run_id',$run->id)->count()}} {{ $en ? 'participants' : 'katılımcı' }}</li>@endforeach</ul></div>@endif<div class="card" style="margin-top:20px"><h2>{{ $en ? 'Sessions' : 'Seanslar' }}</h2>@foreach($course->sessions as $session)<div style="border-top:1px solid #eaecf0;padding:16px 0"><h3>{{$session->title}}</h3><p class="muted">{{$session->starts_at?->format('d.m.Y H:i')}} - {{$session->ends_at?->format('H:i')}}</p>@if($session->meeting_url)<a class="btn btn-primary" href="{{$session->meeting_url}}">{{ $en ? 'Join class' : 'Derse katıl' }}</a>@endif @if($session->recording_path)<a class="btn btn-outline-secondary" href="{{asset('storage/'.$session->recording_path)}}">{{ $en ? 'Open recording' : 'Kaydı aç' }}</a> <small class="muted">{{ $en ? 'Available until' : 'Şu tarihe kadar erişilebilir:' }} {{ $session->recording_expires_at?->format('d.m.Y') }}</small>@endif<ul>@foreach($session->materials as $material)<li><span>{{$material->title}}</span> <a class="text-link" href="{{asset('storage/'.$material->path)}}" target="_blank" rel="noopener">{{ $en ? 'Preview' : 'Önizle' }}</a> <button type="button" class="text-link" onclick="document.getElementById('edit-material-{{$material->id}}').style.display='block'">{{ $en ? 'Edit' : 'Düzenle' }}</button> @if($material->versions->isNotEmpty())<button type="button" class="text-link" onclick="document.getElementById('history-material-{{$material->id}}').style.display='block'">{{ $en ? 'History' : 'Geçmiş' }} ({{$material->versions->count()}})</button>@endif <form method="post" action="{{route('builder.materials.destroy',$material)}}" onsubmit="return confirm('{{ $en ? 'Delete this material?' : 'Bu materyal silinsin mi?' }}')" class="inline-form">@csrf<input type="hidden" name="_method" value="DELETE"><button type="submit" class="text-link" style="color:#b3402e">{{ $en ? 'Delete' : 'Sil' }}</button></form>
<form method="post" action="{{route('builder.materials.update',$material)}}" id="edit-material-{{$material->id}}" style="display:none;margin:8px 0" enctype="multipart/form-data">@csrf<input name="title" value="{{$material->title}}" aria-label="{{ $en ? 'Material title' : 'Materyal başlığı' }}" required><label class="muted" style="display:block;margin:6px 0 2px;font-size:12px">{{ $en ? 'Replace file (optional)' : 'Dosyayı değiştir (opsiyonel)' }}</label><input type="file" name="file"><button type="submit" class="btn btn-primary" style="margin-top:6px">{{ $en ? 'Save' : 'Kaydet' }}</button></form>
@if($material->versions->isNotEmpty())<div id="history-material-{{$material->id}}" style="display:none;margin:8px 0"><ul class="muted" style="margin:0;padding-left:18px">@foreach($material->versions as $version)<li>{{$version->title}} — <a class="text-link" href="{{asset('storage/'.$version->path)}}" target="_blank" rel="noopener">{{ $en ? 'Open' : 'Aç' }}</a> <small>({{$version->created_at->format('d.m.Y H:i')}}@if($version->replacedBy) · {{$version->replacedBy->name}}@endif)</small></li>@endforeach</ul></div>@endif
</li>@endforeach</ul><div style="margin-top:12px"><b>{{ $en ? 'Attendance' : 'Yoklama' }}</b>@if($session->meeting_status==='not_applicable')<p class="muted" style="margin:4px 0">{{ $en ? 'In-person session — attendance only counts if you mark it.' : 'Yüz yüze seans — katılım yalnızca sizin işaretlemenizle sayılır.' }}</p>@else<p class="muted" style="margin:4px 0">{{ $en ? 'Attendance for an online session is counted automatically once a participant stays in the room long enough; you can also mark it here if there is a connection issue.' : 'Online seansta katılım, katılımcı odada yeterli süre kalınca otomatik sayılır; bağlantı sorunu olursa buradan da işaretleyebilirsiniz.' }}</p>@endif<ul>@forelse($course->currentEnrollments as $enrollment)@php($attendance = $enrollment->sessionProgress->firstWhere('course_session_id', $session->id))<li>{{$enrollment->user->name}} — @if($attendance?->completed_at)<span class="muted"><x-lucide name="check" size="14" /> {{ $en ? 'Attended' : 'Katıldı' }} ({{ $attendance->completed_at->format('d.m.Y H:i') }})</span>@else<form method="post" action="{{route('instructor.sessions.attendance',$session)}}" class="inline-form">@csrf<input type="hidden" name="user_id" value="{{$enrollment->user_id}}"><button class="btn btn-outline-secondary" type="submit">{{ $en ? 'Mark as attended' : 'Katıldı olarak işaretle' }}</button></form>@endif</li>@empty<li class="muted">{{ $en ? 'No participants.' : 'Katılımcı yok.' }}</li>@endforelse</ul></div></div>@endforeach<h2>{{ $en ? 'Plan a new session' : 'Yeni seans planla' }}</h2><p class="muted">{{ $en ? 'For online courses, the meeting room and join URL are created automatically.' : 'Online eğitimlerde toplantı odası ve katılım URL’si otomatik oluşturulur.' }}</p><form method="post" action="{{route('instructor.sessions.store',$course)}}" enctype="multipart/form-data">@csrf<input name="title" placeholder="{{ $en ? 'Session name' : 'Seans adı' }}" required><input name="starts_at" type="datetime-local"><input name="ends_at" type="datetime-local"><input name="material" type="file"><button>{{ $en ? 'Add session & material' : 'Seans ve materyal ekle' }}</button></form></div>
</div>

{{-- ============ ASSESSMENTS ============ --}}
<div data-tab-panel="assessments" hidden>
<div class="card" style="margin-top:20px">
<h2>{{ $en ? 'Assessments' : 'Değerlendirmeler' }}</h2>
@if($assessableActivities->isEmpty())
<p class="muted">{{ $en ? 'No quizzes or assignments in this course yet -- add one from the Curriculum tab.' : 'Bu eğitimde henüz quiz veya ödev yok -- Müfredat sekmesinden ekleyebilirsiniz.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($assessableActivities as $activity)
<li class="list-group-item requirement-row">
<span><b>{{ $activity->title }}</b>
@if($activity->type === 'quiz')
<small class="muted">— {{ $activity->quizQuestions->count() }} {{ $en ? 'questions' : 'soru' }} · {{ $activity->quizAttempts->count() }} {{ $en ? 'attempts' : 'deneme' }}@if($activity->quizAttempts->isNotEmpty()) · {{ $en ? 'avg.' : 'ort.' }} %{{ round($activity->quizAttempts->avg('score')) }}@endif</small>
@else
<small class="muted">— {{ $activity->assignmentSubmissions->count() }} {{ $en ? 'submissions' : 'teslim' }} · {{ $activity->assignmentSubmissions->whereNull('graded_at')->count() }} {{ $en ? 'pending' : 'bekliyor' }}</small>
@endif
</span>
@if($activity->type === 'assignment')<a class="text-link" href="{{ route('instructor.activities.submissions', $activity) }}">{{ $en ? 'Submissions →' : 'Teslimler →' }}</a>@endif
</li>
@endforeach
</ul>
@endif
</div>
</div>

{{-- ============ ANALYTICS ============ --}}
<div data-tab-panel="analytics" hidden>
<div class="row row-cols-1 row-cols-md-3 g-4 mt-1">
<div class="card metric-card"><span class="tag">{{ $en ? 'STARTED' : 'BAŞLADI' }}</span><strong>{{ $analytics['funnel']['started'] }}</strong><small class="muted">/ {{ $analytics['total'] }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? '50%+' : '%50+' }}</span><strong>{{ $analytics['funnel']['halfway'] }}</strong><small class="muted">/ {{ $analytics['total'] }}</small></div>
<div class="card metric-card"><span class="tag">{{ $en ? 'COMPLETED' : 'TAMAMLADI' }}</span><strong>{{ $analytics['funnel']['completed'] }}</strong><small class="muted">/ {{ $analytics['total'] }}</small></div>
</div>

<div class="row row-cols-1 row-cols-md-3 g-4 mt-1">
<div class="card">
<h3 style="margin-top:0">{{ $en ? 'Quiz' : 'Quiz' }}</h3>
<ul class="list-group list-group-flush">
<li class="list-group-item requirement-row"><span>{{ $en ? 'Average score' : 'Ortalama puan' }}</span><b>{{ $analytics['quizStats']['avg_score'] !== null ? '%'.$analytics['quizStats']['avg_score'] : '—' }}</b></li>
<li class="list-group-item requirement-row"><span>{{ $en ? 'Pass rate' : 'Geçme oranı' }}</span><b>{{ $analytics['quizStats']['pass_rate'] !== null ? '%'.$analytics['quizStats']['pass_rate'] : '—' }}</b></li>
<li class="list-group-item requirement-row"><span>{{ $en ? 'Average attempts' : 'Ortalama deneme' }}</span><b>{{ $analytics['quizStats']['avg_attempts'] ?? '—' }}</b></li>
</ul>
</div>
<div class="card">
<h3 style="margin-top:0">{{ $en ? 'Assignments' : 'Ödevler' }}</h3>
<ul class="list-group list-group-flush">
<li class="list-group-item requirement-row"><span>{{ $en ? 'Submitted' : 'Teslim edilen' }}</span><b>{{ $analytics['assignmentStats']['submitted'] }}</b></li>
<li class="list-group-item requirement-row"><span>{{ $en ? 'Pending' : 'Bekleyen' }}</span><b>{{ $analytics['assignmentStats']['pending'] }}</b></li>
<li class="list-group-item requirement-row"><span>{{ $en ? 'Average score' : 'Ortalama puan' }}</span><b>{{ $analytics['assignmentStats']['avg_score'] !== null ? '%'.$analytics['assignmentStats']['avg_score'] : '—' }}</b></li>
</ul>
</div>
<div class="card">
<h3 style="margin-top:0">{{ $en ? 'Attendance' : 'Yoklama' }}</h3>
<ul class="list-group list-group-flush">
<li class="list-group-item requirement-row"><span>{{ $en ? 'Average attendance' : 'Ortalama katılım' }}</span><b>{{ $analytics['avgAttendance'] !== null ? '%'.$analytics['avgAttendance'] : '—' }}</b></li>
</ul>
</div>
</div>

<div class="card" style="margin-top:20px">
<h2>{{ $en ? 'Content performance' : 'İçerik performansı' }}</h2>
<p class="muted" style="margin-top:-8px">{{ $en ? 'Not just how students are doing -- which specific piece of content is where they get stuck.' : 'Sadece öğrencilerin durumu değil -- hangi içerikte takıldıkları.' }}</p>
@if($analytics['contentPerformance']->isEmpty())
<p class="muted">{{ $en ? 'No content completion data yet.' : 'Henüz içerik tamamlama verisi yok.' }}</p>
@else
<ul class="list-group list-group-flush">
@foreach($analytics['contentPerformance'] as $item)
<li class="list-group-item requirement-row">
<span>{{ $item['title'] }}</span>
<span>{{ $item['rate'] < 60 ? '⚠ ' : '' }}%{{ $item['rate'] }}</span>
</li>
@endforeach
</ul>
@endif
</div>
</div>

{{-- ============ SETTINGS ============ --}}
<div data-tab-panel="settings" hidden>
<div class="card" style="margin-top:20px">
<h2>{{ $en ? 'Course settings' : 'Eğitim ayarları' }}</h2>
<form method="post" action="{{ route('instructor.courses.update', $course) }}" enctype="multipart/form-data">
@csrf
<label>{{ $en ? 'Title' : 'Başlık' }}<input name="title" value="{{ $course->title }}" required></label>
<label>{{ $en ? 'Description' : 'Açıklama' }}<textarea name="description" rows="3">{{ $course->description }}</textarea></label>
<div class="row row-cols-1 row-cols-md-3 g-2">
<label>{{ $en ? 'Delivery' : 'Sunum şekli' }}<select name="delivery_type"><option value="online" @selected($course->delivery_type==='online')>{{ $en ? 'Online' : 'Online' }}</option><option value="offline" @selected($course->delivery_type==='offline')>{{ $en ? 'In person' : 'Yüz yüze' }}</option></select></label>
<label>{{ $en ? 'Language' : 'Dil' }}<select name="language"><option value="tr" @selected($course->language==='tr')>Türkçe</option><option value="en" @selected($course->language==='en')>English</option></select></label>
<label>{{ $en ? 'Status' : 'Durum' }}<select name="status"><option value="draft" @selected($course->status==='draft')>{{ $statusLabels['draft'] }}</option><option value="published" @selected($course->status==='published')>{{ $statusLabels['published'] }}</option><option value="completed" @selected($course->status==='completed')>{{ $statusLabels['completed'] }}</option></select></label>
</div>
<div class="row row-cols-1 row-cols-md-2 g-2">
<label>{{ $en ? 'Starts at' : 'Başlangıç' }}<input type="date" name="starts_at" value="{{ $course->starts_at?->format('Y-m-d') }}"></label>
<label>{{ $en ? 'Ends at' : 'Bitiş' }}<input type="date" name="ends_at" value="{{ $course->ends_at?->format('Y-m-d') }}"></label>
</div>
<label>{{ $en ? 'Guest instructor name' : 'Misafir eğitimci adı' }}<input name="guest_instructor_name" value="{{ $course->guest_instructor_name }}"></label>
<label>{{ $en ? 'Guest instructor email' : 'Misafir eğitimci e-postası' }}<input type="email" name="guest_instructor_email" value="{{ $course->guest_instructor_email }}"></label>
<label>{{ $en ? 'Guest instructor bio' : 'Misafir eğitimci biyografisi' }}<textarea name="guest_instructor_bio" rows="2">{{ $course->guest_instructor_bio }}</textarea></label>
<label class="check-field"><input type="checkbox" name="certificate_enabled" value="1" {{ $course->certificate_enabled ? 'checked' : '' }}> {{ $en ? 'Certificate enabled' : 'Sertifika aktif' }}</label>
<label>{{ $en ? 'Poster image (optional)' : 'Poster görseli (opsiyonel)' }}<input type="file" name="poster"></label>
<label>{{ $en ? 'Cover image (optional)' : 'Kapak görseli (opsiyonel)' }}<input type="file" name="image"></label>
<button type="submit" class="btn btn-primary" style="margin-top:12px">{{ $en ? 'Save settings' : 'Ayarları kaydet' }}</button>
</form>
</div>
</div>

<script>
(function(){
    function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
    function persist(container, endpointFn, idAttr){
        var ids = Array.prototype.map.call(container.children, function(el){ return el.dataset[idAttr]; });
        fetch(endpointFn(), {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json'},
            body: JSON.stringify({ids: ids})
        });
    }
    // Drag-and-drop is a mouse-only interaction; the ▲▼ buttons give keyboard
    // and screen-reader users an equivalent way to reorder (WCAG 2.5.7).
    function setupSortable(container, endpointFn, idAttr){
        var dragEl = null;
        container.addEventListener('dragstart', function(e){
            var item = e.target.closest('[draggable="true"]');
            if (!item || item.parentElement !== container) return;
            dragEl = item;
            e.dataTransfer.effectAllowed = 'move';
        });
        container.addEventListener('dragover', function(e){
            e.preventDefault();
            var target = e.target.closest('[draggable="true"]');
            if (!target || target === dragEl || target.parentElement !== container) return;
            var rect = target.getBoundingClientRect();
            var placeAfter = (e.clientY - rect.top) / rect.height > 0.5;
            container.insertBefore(dragEl, placeAfter ? target.nextSibling : target);
        });
        container.addEventListener('drop', function(e){
            e.preventDefault();
            if (!dragEl) return;
            persist(container, endpointFn, idAttr);
            dragEl = null;
        });
        container.addEventListener('click', function(e){
            var btn = e.target.closest('[data-move]');
            if (!btn) return;
            var item = btn.closest('[draggable="true"]');
            if (!item || item.parentElement !== container) return;
            if (btn.dataset.move === 'up' && item.previousElementSibling) {
                container.insertBefore(item, item.previousElementSibling);
            } else if (btn.dataset.move === 'down' && item.nextElementSibling) {
                container.insertBefore(item.nextElementSibling, item);
            } else {
                return;
            }
            btn.focus();
            persist(container, endpointFn, idAttr);
        });
    }
    var sectionsList = document.getElementById('sections-list');
    if (sectionsList) setupSortable(sectionsList, function(){ return '{{ route('builder.sections.reorder', $course) }}'; }, 'sectionId');
    document.querySelectorAll('[data-activities]').forEach(function(list){
        setupSortable(list, function(){ return '/instructor/sections/' + list.dataset.sectionId + '/activities/reorder'; }, 'activityId');
    });
})();
(function(){
    var tabs = document.getElementById('course-workspace-tabs');
    if (!tabs) return;
    var links = tabs.querySelectorAll('[data-tab]');
    var panels = document.querySelectorAll('[data-tab-panel]');
    function activate(name){
        if (!name) return;
        links.forEach(function(l){ l.classList.toggle('is-active', l.dataset.tab === name); });
        panels.forEach(function(p){ p.hidden = p.dataset.tabPanel !== name; });
    }
    tabs.addEventListener('click', function(e){
        var link = e.target.closest('[data-tab]');
        if (!link) return;
        e.preventDefault();
        activate(link.dataset.tab);
        history.replaceState(null, '', '#' + link.dataset.tab);
    });
    document.querySelectorAll('[data-tab-jump]').forEach(function(btn){
        btn.addEventListener('click', function(){
            activate(btn.dataset.tabJump);
            history.replaceState(null, '', '#' + btn.dataset.tabJump);
        });
    });
    var initial = window.location.hash ? window.location.hash.slice(1) : 'overview';
    if (!document.querySelector('[data-tab-panel="' + initial + '"]')) initial = 'overview';
    activate(initial);
})();
</script>
@endsection
