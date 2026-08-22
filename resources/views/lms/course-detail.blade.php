@extends('lms.instructor-layout') @section('page-title',$course->title) @section('content')<div class="grid" style="margin-top:20px"><div class="card"><span class="tag">{{strtoupper($course->delivery_type)}} · {{$course->language==='en'?'ENGLISH':'TÜRKÇE'}}</span><h2>{{$course->title}}</h2><p>{{$course->description}}</p><p><b>Eğitmen:</b> {{$course->instructor->name}}<br><b>Misafir eğitimci:</b> {{$course->guest_instructor_name ?: 'Yok'}}<br><b>Sertifika:</b> {{$course->certificate_enabled?'Aktif':'Pasif'}}<br><b>SDG:</b> {{implode(', ',$course->sdg_goals ?: []) ?: 'Belirlenmedi'}}</p></div><div class="card"><h2>Katılımcılar ({{$course->enrollments->count()}})</h2>@forelse($participants as $enrollment)<p><b>{{$enrollment->user->name}}</b> · {{$enrollment->status}}@if($enrollment->run)<br><small>Dönem: {{$enrollment->run->name}}</small>@endif<br><small>{{$enrollment->user->email}}</small>@foreach($enrollment->sessionProgress as $progress)<br><small>{{$progress->session->title}}: giriş {{$progress->started_at?->format('d.m.Y H:i') ?: '-'}} / bitiş {{$progress->completed_at?->format('d.m.Y H:i') ?: '-'}}</small>@endforeach</p>@empty<p>Henüz katılımcı yok.</p>@endforelse{{ $participants->links('lms.partials.pagination') }}</div></div><div class="card" style="margin-top:20px" id="curriculum-builder">
<h2>Müfredat</h2>
<p class="muted" style="margin-top:-8px">Bölüm ve etkinlikleri sürükleyerek sıralayabilirsiniz. Sınav (quiz) soruları admin panelinden eklenir.</p>
<div id="sections-list">
@foreach($course->sections as $section)
<div class="builder-section" draggable="true" data-section-id="{{$section->id}}">
<div class="builder-section-head"><span class="drag-handle">⠿</span><h3 style="margin:0;flex:1">{{$section->title}}</h3>
<button type="button" class="text-link" onclick="document.getElementById('edit-section-{{$section->id}}').style.display='block'">Düzenle</button>
<form method="post" action="{{route('builder.sections.destroy',$section)}}" onsubmit="return confirm('Bu bölüm ve içindeki tüm etkinlikler silinecek. Emin misiniz?')" class="inline-form">@csrf<input type="hidden" name="_method" value="DELETE"><button type="submit" class="text-link" style="color:#b3402e">Sil</button></form>
</div>
<form method="post" action="{{route('builder.sections.update',$section)}}" id="edit-section-{{$section->id}}" style="display:none;margin:10px 0">@csrf<input name="title" value="{{$section->title}}" required><textarea name="description" rows="2" placeholder="Açıklama (opsiyonel)">{{$section->description}}</textarea><button type="submit">Kaydet</button></form>
@if($section->description)<p class="muted">{{$section->description}}</p>@endif
<ul class="builder-activity-list" data-activities data-section-id="{{$section->id}}">
@forelse($section->activities as $activity)
<li draggable="true" data-activity-id="{{$activity->id}}"><span class="drag-handle">⠿</span><span>{{$activity->title}}</span> <small class="muted">({{$activity->type}}@if(!$activity->is_required) · opsiyonel @endif)</small>
@if($activity->type==='assignment')<a class="text-link" href="{{route('instructor.activities.submissions',$activity)}}">Teslimler →</a>@endif
@if($activity->type!=='quiz')<form method="post" action="{{route('builder.activities.destroy',$activity)}}" onsubmit="return confirm('Bu etkinlik silinsin mi?')" class="inline-form">@csrf<input type="hidden" name="_method" value="DELETE"><button type="submit" class="text-link" style="color:#b3402e">Sil</button></form>@endif
</li>
@empty
<li class="muted">Bu bölümde henüz etkinlik yok.</li>
@endforelse
</ul>
<details class="builder-add-activity"><summary>+ Etkinlik ekle</summary>
<form method="post" action="{{route('builder.activities.store',$section)}}">@csrf
<input name="title" placeholder="Etkinlik adı" required>
<select name="type"><option value="video">Video</option><option value="document">Doküman</option><option value="presentation">Sunum</option><option value="resource">Kaynak</option><option value="assignment">Ödev</option></select>
<label class="check-field"><input type="checkbox" name="is_required" value="1" checked> Zorunlu</label>
<button type="submit">Ekle</button>
</form>
</details>
</div>
@endforeach
</div>
<details class="builder-add-section"><summary>+ Bölüm ekle</summary>
<form method="post" action="{{route('builder.sections.store',$course)}}">@csrf<input name="title" placeholder="Bölüm adı" required><textarea name="description" rows="2" placeholder="Açıklama (opsiyonel)"></textarea><button type="submit">Bölüm ekle</button></form>
</details>
</div>
<script>
(function(){
    function csrf(){ return document.querySelector('meta[name="csrf-token"]').content; }
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
            var ids = Array.prototype.map.call(container.children, function(el){ return el.dataset[idAttr]; });
            fetch(endpointFn(), {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json'},
                body: JSON.stringify({ids: ids})
            });
            dragEl = null;
        });
    }
    var sectionsList = document.getElementById('sections-list');
    if (sectionsList) setupSortable(sectionsList, function(){ return '{{ route('builder.sections.reorder', $course) }}'; }, 'sectionId');
    document.querySelectorAll('[data-activities]').forEach(function(list){
        setupSortable(list, function(){ return '/instructor/sections/' + list.dataset.sectionId + '/activities/reorder'; }, 'activityId');
    });
})();
</script>@if($course->runs->isNotEmpty())<div class="card" style="margin-top:20px"><h2>Dönemler</h2><ul>@foreach($course->runs as $run)<li><b>{{$run->name}}</b> ({{$run->code}}) · {{$run->status}} · {{$run->starts_at?->format('d.m.Y') ?: '-'}} - {{$run->ends_at?->format('d.m.Y') ?: '-'}} · {{$course->enrollments->where('course_run_id',$run->id)->count()}} katılımcı</li>@endforeach</ul></div>@endif<div class="card" style="margin-top:20px"><h2>Seanslar</h2>@foreach($course->sessions as $session)<div style="border-top:1px solid #eaecf0;padding:16px 0"><h3>{{$session->title}}</h3><p class="muted">{{$session->starts_at?->format('d.m.Y H:i')}} - {{$session->ends_at?->format('H:i')}}</p>@if($session->meeting_url)<a class="button" href="{{$session->meeting_url}}">Derse katıl</a>@endif @if($session->recording_path)<a class="button secondary" href="{{asset('storage/'.$session->recording_path)}}">Kaydı aç</a>@endif<ul>@foreach($session->materials as $material)<li>{{$material->title}}</li>@endforeach</ul><div style="margin-top:12px"><b>Yoklama</b>@if($session->meeting_status==='not_applicable')<p class="muted" style="margin:4px 0">Yüz yüze seans — katılım yalnızca sizin işaretlemenizle sayılır.</p>@else<p class="muted" style="margin:4px 0">Online seansta katılım, katılımcı odada yeterli süre kalınca otomatik sayılır; bağlantı sorunu olursa buradan da işaretleyebilirsiniz.</p>@endif<ul>@forelse($course->enrollments as $enrollment)@php($attendance = $enrollment->sessionProgress->firstWhere('course_session_id', $session->id))<li>{{$enrollment->user->name}} — @if($attendance?->completed_at)<span class="muted">✓ Katıldı ({{ $attendance->completed_at->format('d.m.Y H:i') }})</span>@else<form method="post" action="{{route('instructor.sessions.attendance',$session)}}" class="inline-form">@csrf<input type="hidden" name="user_id" value="{{$enrollment->user_id}}"><button class="button secondary" type="submit">Katıldı olarak işaretle</button></form>@endif</li>@empty<li class="muted">Katılımcı yok.</li>@endforelse</ul></div></div>@endforeach<h2>Yeni seans planla</h2><p class="muted">Online eğitimlerde toplantı odası ve katılım URL’si otomatik oluşturulur.</p><form method="post" action="{{route('instructor.sessions.store',$course)}}" enctype="multipart/form-data">@csrf<input name="title" placeholder="Seans adı" required><input name="starts_at" type="datetime-local"><input name="ends_at" type="datetime-local"><input name="material" type="file"><button>Seans ve materyal ekle</button></form></div>@endsection
