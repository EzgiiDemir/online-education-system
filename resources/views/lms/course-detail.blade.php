@extends('lms.instructor-layout') @section('page-title',$course->title) @section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="row row-cols-1 row-cols-lg-2 g-4 mt-1"><div class="card"><span class="tag">{{strtoupper($course->delivery_type)}} · {{$course->language==='en'?'ENGLISH':'TÜRKÇE'}}</span><h2>{{$course->title}}</h2><p>{{$course->description}}</p><p><b>{{ $en ? 'Instructor:' : 'Eğitmen:' }}</b> {{$course->instructor->name}}<br><b>{{ $en ? 'Guest instructor:' : 'Misafir eğitimci:' }}</b> {{$course->guest_instructor_name ?: ($en ? 'None' : 'Yok')}}<br><b>{{ $en ? 'Certificate:' : 'Sertifika:' }}</b> {{$course->certificate_enabled?($en?'Active':'Aktif'):($en?'Inactive':'Pasif')}}<br><b>SDG:</b> {{implode(', ',$course->sdg_goals ?: []) ?: ($en ? 'Not set' : 'Belirlenmedi')}}</p></div><div class="card"><h2>{{ $en ? 'Participants' : 'Katılımcılar' }} ({{$course->enrollments->count()}})</h2>@forelse($participants as $enrollment)<p><b>{{$enrollment->user->name}}</b> · {{$enrollment->status}}@if($enrollment->run)<br><small>{{ $en ? 'Run:' : 'Dönem:' }} {{$enrollment->run->name}}</small>@endif<br><small>{{$enrollment->user->email}}</small>@foreach($enrollment->sessionProgress as $progress)<br><small>{{$progress->session->title}}: {{ $en ? 'start' : 'giriş' }} {{$progress->started_at?->format('d.m.Y H:i') ?: '-'}} / {{ $en ? 'end' : 'bitiş' }} {{$progress->completed_at?->format('d.m.Y H:i') ?: '-'}}</small>@endforeach</p>@empty<p>{{ $en ? 'No participants yet.' : 'Henüz katılımcı yok.' }}</p>@endforelse{{ $participants->links('lms.partials.pagination') }}</div></div><div class="card" style="margin-top:20px" id="curriculum-builder">
<h2>{{ $en ? 'Curriculum' : 'Müfredat' }}</h2>
<p class="muted" style="margin-top:-8px">{{ $en ? 'Drag sections and activities to reorder them. Quiz questions are added from the admin panel.' : 'Bölüm ve etkinlikleri sürükleyerek sıralayabilirsiniz. Sınav (quiz) soruları admin panelinden eklenir.' }}</p>
<div id="sections-list">
@foreach($course->sections as $section)
<div class="builder-section" draggable="true" data-section-id="{{$section->id}}">
<div class="builder-section-head"><span class="drag-handle" aria-hidden="true"><x-lucide name="grip-vertical" size="16" /></span><span class="reorder-buttons"><button type="button" class="reorder-btn" data-move="up" aria-label="{{ $en ? 'Move section up' : 'Bölümü yukarı taşı' }}"><x-lucide name="chevron-up" size="14" /></button><button type="button" class="reorder-btn" data-move="down" aria-label="{{ $en ? 'Move section down' : 'Bölümü aşağı taşı' }}"><x-lucide name="chevron-down" size="14" /></button></span><h3 style="margin:0;flex:1">{{$section->title}}</h3>
<button type="button" class="text-link" onclick="document.getElementById('edit-section-{{$section->id}}').style.display='block'">{{ $en ? 'Edit' : 'Düzenle' }}</button>
<form method="post" action="{{route('builder.sections.destroy',$section)}}" onsubmit="return confirm('{{ $en ? 'This section and all its activities will be deleted. Are you sure?' : 'Bu bölüm ve içindeki tüm etkinlikler silinecek. Emin misiniz?' }}')" class="inline-form">@csrf<input type="hidden" name="_method" value="DELETE"><button type="submit" class="text-link" style="color:#b3402e">{{ $en ? 'Delete' : 'Sil' }}</button></form>
</div>
<form method="post" action="{{route('builder.sections.update',$section)}}" id="edit-section-{{$section->id}}" style="display:none;margin:10px 0">@csrf<input name="title" value="{{$section->title}}" aria-label="{{ $en ? 'Section title' : 'Bölüm başlığı' }}" required><textarea name="description" rows="2" placeholder="{{ $en ? 'Description (optional)' : 'Açıklama (opsiyonel)' }}" aria-label="{{ $en ? 'Section description' : 'Bölüm açıklaması' }}">{{$section->description}}</textarea><button type="submit">{{ $en ? 'Save' : 'Kaydet' }}</button></form>
@if($section->description)<p class="muted">{{$section->description}}</p>@endif
<ul class="builder-activity-list" data-activities data-section-id="{{$section->id}}">
@forelse($section->activities as $activity)
<li draggable="true" data-activity-id="{{$activity->id}}"><span class="drag-handle" aria-hidden="true"><x-lucide name="grip-vertical" size="16" /></span><span class="reorder-buttons"><button type="button" class="reorder-btn" data-move="up" aria-label="{{ $en ? 'Move activity up' : 'Etkinliği yukarı taşı' }}"><x-lucide name="chevron-up" size="14" /></button><button type="button" class="reorder-btn" data-move="down" aria-label="{{ $en ? 'Move activity down' : 'Etkinliği aşağı taşı' }}"><x-lucide name="chevron-down" size="14" /></button></span><span>{{$activity->title}}</span> <small class="muted">({{$activity->type}}@if(!$activity->is_required) · {{ $en ? 'optional' : 'opsiyonel' }} @endif)</small>
@if($activity->type==='assignment')<a class="text-link" href="{{route('instructor.activities.submissions',$activity)}}">{{ $en ? 'Submissions →' : 'Teslimler →' }}</a>@endif
@if($activity->type!=='quiz')<form method="post" action="{{route('builder.activities.destroy',$activity)}}" onsubmit="return confirm('{{ $en ? 'Delete this activity?' : 'Bu etkinlik silinsin mi?' }}')" class="inline-form">@csrf<input type="hidden" name="_method" value="DELETE"><button type="submit" class="text-link" style="color:#b3402e">{{ $en ? 'Delete' : 'Sil' }}</button></form>@endif
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
<button type="submit">{{ $en ? 'Add' : 'Ekle' }}</button>
</form>
</details>
</div>
@endforeach
</div>
<details class="builder-add-section"><summary>+ {{ $en ? 'Add section' : 'Bölüm ekle' }}</summary>
<form method="post" action="{{route('builder.sections.store',$course)}}">@csrf<input name="title" placeholder="{{ $en ? 'Section name' : 'Bölüm adı' }}" required><textarea name="description" rows="2" placeholder="{{ $en ? 'Description (optional)' : 'Açıklama (opsiyonel)' }}"></textarea><button type="submit">{{ $en ? 'Add section' : 'Bölüm ekle' }}</button></form>
</details>
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
</script>
@if($course->runs->isNotEmpty())<div class="card" style="margin-top:20px"><h2>{{ $en ? 'Runs' : 'Dönemler' }}</h2><ul>@foreach($course->runs as $run)<li><b>{{$run->name}}</b> ({{$run->code}}) · {{$run->status}} · {{$run->starts_at?->format('d.m.Y') ?: '-'}} - {{$run->ends_at?->format('d.m.Y') ?: '-'}} · {{$course->enrollments->where('course_run_id',$run->id)->count()}} {{ $en ? 'participants' : 'katılımcı' }}</li>@endforeach</ul></div>@endif<div class="card" style="margin-top:20px"><h2>{{ $en ? 'Sessions' : 'Seanslar' }}</h2>@foreach($course->sessions as $session)<div style="border-top:1px solid #eaecf0;padding:16px 0"><h3>{{$session->title}}</h3><p class="muted">{{$session->starts_at?->format('d.m.Y H:i')}} - {{$session->ends_at?->format('H:i')}}</p>@if($session->meeting_url)<a class="button" href="{{$session->meeting_url}}">{{ $en ? 'Join class' : 'Derse katıl' }}</a>@endif @if($session->recording_path)<a class="button secondary" href="{{asset('storage/'.$session->recording_path)}}">{{ $en ? 'Open recording' : 'Kaydı aç' }}</a>@endif<ul>@foreach($session->materials as $material)<li>{{$material->title}}</li>@endforeach</ul><div style="margin-top:12px"><b>{{ $en ? 'Attendance' : 'Yoklama' }}</b>@if($session->meeting_status==='not_applicable')<p class="muted" style="margin:4px 0">{{ $en ? 'In-person session — attendance only counts if you mark it.' : 'Yüz yüze seans — katılım yalnızca sizin işaretlemenizle sayılır.' }}</p>@else<p class="muted" style="margin:4px 0">{{ $en ? 'Attendance for an online session is counted automatically once a participant stays in the room long enough; you can also mark it here if there is a connection issue.' : 'Online seansta katılım, katılımcı odada yeterli süre kalınca otomatik sayılır; bağlantı sorunu olursa buradan da işaretleyebilirsiniz.' }}</p>@endif<ul>@forelse($course->enrollments as $enrollment)@php($attendance = $enrollment->sessionProgress->firstWhere('course_session_id', $session->id))<li>{{$enrollment->user->name}} — @if($attendance?->completed_at)<span class="muted"><x-lucide name="check" size="14" /> {{ $en ? 'Attended' : 'Katıldı' }} ({{ $attendance->completed_at->format('d.m.Y H:i') }})</span>@else<form method="post" action="{{route('instructor.sessions.attendance',$session)}}" class="inline-form">@csrf<input type="hidden" name="user_id" value="{{$enrollment->user_id}}"><button class="button secondary" type="submit">{{ $en ? 'Mark as attended' : 'Katıldı olarak işaretle' }}</button></form>@endif</li>@empty<li class="muted">{{ $en ? 'No participants.' : 'Katılımcı yok.' }}</li>@endforelse</ul></div></div>@endforeach<h2>{{ $en ? 'Plan a new session' : 'Yeni seans planla' }}</h2><p class="muted">{{ $en ? 'For online courses, the meeting room and join URL are created automatically.' : 'Online eğitimlerde toplantı odası ve katılım URL’si otomatik oluşturulur.' }}</p><form method="post" action="{{route('instructor.sessions.store',$course)}}" enctype="multipart/form-data">@csrf<input name="title" placeholder="{{ $en ? 'Session name' : 'Seans adı' }}" required><input name="starts_at" type="datetime-local"><input name="ends_at" type="datetime-local"><input name="material" type="file"><button>{{ $en ? 'Add session & material' : 'Seans ve materyal ekle' }}</button></form></div>
@endsection
