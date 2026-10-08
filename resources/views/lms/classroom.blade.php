@extends('lms.layout')
@section('body-class','classroom-page')
@section('title',$session->title.' · '.(session('locale', 'tr') === 'en' ? 'Live class' : 'Canlı sınıf'))
@section('content')
@php($en = session('locale', 'tr') === 'en')
@php($isInstructor = auth()->id() === $session->course->instructor_id)
<div class="live-room" id="room" data-user="{{ auth()->id() }}" data-instructor="{{ $session->course->instructor_id }}" data-is-instructor="{{ $isInstructor ? '1' : '0' }}" data-csrf="{{ csrf_token() }}" data-presence="{{ route('sessions.presence',$session) }}" data-signals="{{ route('sessions.signals',$session) }}" data-send="{{ route('sessions.signals.send',$session) }}" data-recording="{{ route('sessions.recording',$session) }}" data-attendance-status="{{ route('sessions.attendance-status',$session) }}" data-complete="{{ route('sessions.complete',$session) }}" data-share-files="{{ route('sessions.files.share',$session) }}" data-grade-url-template="{{ route('materials.grade', ['material' => '__ID__']) }}" data-delete-url-template="{{ route('materials.share.destroy', ['material' => '__ID__']) }}">
<header class="live-header live-header-compact">
<div class="live-header-info">
<span class="live-kicker">{{ $en ? 'IN-PORTAL LIVE CLASS' : 'PORTAL İÇİ CANLI SINIF' }}</span>
<h1>{{ $session->title }}</h1>
<span class="live-header-meta">{{ $session->course->title }} · {{ $session->starts_at?->format('d.m.Y H:i') }}</span>
</div>
<div class="live-status"><i></i><span id="roomStatus">{{ $en ? 'Waiting for connection' : 'Bağlantı bekleniyor' }}</span></div>
</header>
<div class="live-layout">
<main class="live-main">
@if($isInstructor)
<div class="host-controls" id="hostControls">
<button type="button" id="muteAllBtn"><x-lucide name="mic-off" size="14" /> <span>{{ $en ? 'Mute all' : 'Tümünü sustur' }}</span></button>
<button type="button" id="camerasOffAllBtn"><x-lucide name="video" size="14" /> <span>{{ $en ? 'Turn off all cameras' : 'Tüm kameraları kapat' }}</span></button>
<button type="button" id="screensOffAllBtn"><x-lucide name="screen-share" size="14" /> <span>{{ $en ? 'Stop all screen shares' : 'Tüm ekran paylaşımlarını durdur' }}</span></button>
</div>
@endif
<div class="stage-main" id="stageMain">
<div class="empty-stage" id="emptyStage"><span><x-lucide name="video" size="28" /></span><strong>{{ $en ? 'Ready to join the class?' : 'Sınıfa katılmaya hazır mısınız?' }}</strong><small>{{ $en ? 'Turn on your camera and microphone to join the session.' : 'Kamera ve mikrofonunuzu açarak derse katılın.' }}</small><div class="pre-join-toggles"><label><input type="checkbox" id="wantVideo" checked> {{ $en ? 'Camera' : 'Kamera' }}</label><label><input type="checkbox" id="wantAudio" checked> {{ $en ? 'Microphone' : 'Mikrofon' }}</label></div><p class="join-error" id="joinError" style="display:none"></p></div>
</div>
<div class="presentation-stage" id="presentationStage" style="display:none"></div>
<div class="control-bar">
<button class="control primary" id="join"><x-lucide name="video" size="16" /> <span>{{ $en ? 'Join' : 'Katıl' }}</span></button>
<button class="control" id="mute" disabled><x-lucide name="mic" size="16" /> <span>{{ $en ? 'Microphone' : 'Mikrofon' }}</span></button>
<button class="control" id="camera" disabled><x-lucide name="video" size="16" /> <span>{{ $en ? 'Camera' : 'Kamera' }}</span></button>
<button class="control" id="raiseHand" disabled><span>✋</span> <span id="raiseHandLabel">{{ $en ? 'Raise hand' : 'El kaldır' }}</span></button>
<button type="button" class="control" id="documentBtn" disabled><x-lucide name="upload" size="16" /> <span>{{ $en ? 'Add document' : 'Belge ekle' }}</span></button>
<input type="file" id="documentInput" accept=".pdf,.doc,.docx,.ppt,.pptx" style="display:none">
@if($isInstructor)
<button type="button" class="control" id="presentBtn" disabled><x-lucide name="monitor-up" size="16" /> <span>{{ $en ? 'Present' : 'Sun' }}</span></button>
<input type="file" id="presentInput" accept=".pdf,.doc,.docx,.ppt,.pptx" style="display:none">
@endif
<button class="control" id="screen" disabled><x-lucide name="screen-share" size="16" /> <span>{{ $en ? 'Share screen' : 'Ekran paylaş' }}</span></button>
@if($isInstructor)<button class="control record" id="record" disabled><x-lucide name="circle" size="16" /> <span>{{ $en ? 'Start recording' : 'Kaydı başlat' }}</span></button>@endif
@if(!$isInstructor && $session->meeting_status!=='not_applicable')<button class="control" id="completeSession" disabled title="{{ $en ? 'Enabled once you have stayed in the room long enough' : 'Yeterli süre odada kaldığınızda etkinleşir' }}"><x-lucide name="check" size="16" /> <span>{{ $en ? 'Complete session' : 'Dersi tamamla' }}</span></button>@endif
<button class="control leave" id="leave" disabled><x-lucide name="log-out" size="16" /> <span>{{ $en ? 'Leave' : 'Çıkış' }}</span></button>
</div>
</main>
<aside class="live-sidebar">
<section class="panel participants-panel">
<div class="panel-title"><h2>{{ $en ? 'Participants' : 'Katılımcılar' }}</h2><span id="participantCount">1</span></div>
<div id="participants" class="participant-list">
<div class="video-tile local-tile" id="localTile" data-tile-id="local">
<video id="localVideo" autoplay muted playsinline style="display:none"></video>
<div class="tile-placeholder" id="localPlaceholder"><span class="tile-avatar-lg">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span></div>
<div class="hand-badge" id="localHandBadge">✋</div>
<div class="tile-footer"><span class="tile-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name,0,1)) }}</span><span>{{ auth()->user()->name }} <em>{{ $en ? 'Me' : 'Ben' }}</em></span><b id="localMic"><x-lucide name="mic" size="14" /></b><button type="button" class="tile-pin" data-tile-id="local" title="{{ $en ? 'Pin to main stage' : 'Büyük ekranda sabitle' }}"><x-lucide name="target" size="14" /></button></div>
</div>
</div>
</section>
<section class="panel chat-panel"><div class="panel-title"><h2>{{ $en ? 'Class chat' : 'Sınıf sohbeti' }}</h2><span><x-lucide name="message-circle" size="16" /></span></div><div class="chat-log" id="chat">@foreach($messages as $message)<div class="chat-message"><strong>{{ $message->user->name }}</strong><p>{{ $message->body }}</p></div>@endforeach</div><form class="chat-form" method="post" action="{{ route('sessions.messages',$session) }}">@csrf<input name="body" placeholder="{{ $en ? 'Write a message...' : 'Mesaj yazın...' }}" required><button type="submit"><x-lucide name="send" size="16" /></button></form></section>
<section class="panel files-panel">
<div class="panel-title"><h2>{{ $en ? 'Shared files' : 'Paylaşılan dosyalar' }}</h2><span><x-lucide name="file-text" size="16" /></span></div>
<div class="files-list" id="filesList">
@forelse($session->materials as $material)
<div class="shared-file" id="file-{{ $material->id }}" data-material-id="{{ $material->id }}">
<x-lucide name="file-text" size="14" />
<div class="shared-file-body">
<a href="{{ asset('storage/'.$material->path) }}" target="_blank" rel="noopener">{{ $material->title }}</a>
<small>{{ $material->uploadedBy?->name ?? '—' }}</small>
<div class="shared-file-grade" data-grade-slot>
@if($material->score !== null)
<span class="shared-file-score">{{ $material->score }} / 100</span>
@elseif($isInstructor)
<form class="grade-form" data-grade-form data-material-id="{{ $material->id }}">
<input type="number" min="0" max="100" placeholder="{{ $en ? 'Score' : 'Puan' }}" class="grade-score-input" required>
<button type="submit">{{ $en ? 'Save & send' : 'Kaydet ve gönder' }}</button>
</form>
@endif
</div>
</div>
@if($isInstructor || $material->uploaded_by_id === auth()->id())
<button type="button" class="shared-file-delete" data-delete-file title="{{ $en ? 'Delete' : 'Sil' }}"><x-lucide name="trash" size="14" /></button>
@endif
</div>
@empty
@endforelse
</div>
<p class="panel-empty" id="filesEmpty" @if($session->materials->isNotEmpty()) style="display:none" @endif>{{ $en ? 'No files have been shared in this session yet.' : 'Bu oturumda henüz dosya paylaşılmadı.' }}</p>
</section>
</aside>
</div>
</div>
<script>
const root=document.getElementById('room'),me=Number(root.dataset.user),instructor=Number(root.dataset.instructor),isInstructor=root.dataset.isInstructor==='1',csrf=root.dataset.csrf;let stream=null,peers={},recorder=null,chunks=[],polling=false;
let featuredId=null,pinnedId=null,handRaised=false,raisedHands=new Set(),isScreenSharing=false,knownNames={};
const micIconHtml={!! json_encode(\App\Support\Icon::svg('mic', 14)) !!};
const micOffIconHtml={!! json_encode(\App\Support\Icon::svg('mic-off', 14)) !!};
const targetIconHtml={!! json_encode(\App\Support\Icon::svg('target', 14)) !!};
const fileIconHtml={!! json_encode(\App\Support\Icon::svg('file-text', 14)) !!};
const trashIconHtml={!! json_encode(\App\Support\Icon::svg('trash', 14)) !!};
const i18n={
    participant: {{ \Illuminate\Support\Js::from($en ? 'Participant' : 'Katılımcı') }},
    instructor: {{ \Illuminate\Support\Js::from($en ? 'Instructor' : 'Eğitmen') }},
    connected: {{ \Illuminate\Support\Js::from($en ? 'Connected to class' : 'Sınıfa bağlanıldı') }},
    joinNoDevice: {{ \Illuminate\Support\Js::from($en ? 'You must leave at least your camera or microphone on to join.' : 'Katılmak için kamera veya mikrofondan en az birini açık bırakmalısınız.') }},
    errNotAllowed: {{ \Illuminate\Support\Js::from($en ? 'Camera/microphone permission was denied. Allow it from the lock icon in your browser address bar and try again.' : 'Kamera/mikrofon izni reddedildi. Tarayıcı adres çubuğundaki kilit simgesinden izin verip tekrar deneyin.') }},
    errNotFound: {{ \Illuminate\Support\Js::from($en ? 'No camera or microphone found. Check your device and try again.' : 'Kamera veya mikrofon bulunamadı. Cihazınızı kontrol edip tekrar deneyin.') }},
    errNotReadable: {{ \Illuminate\Support\Js::from($en ? 'Your camera or microphone is being used by another application. Close other apps and try again.' : 'Kamera veya mikrofona başka bir uygulama tarafından erişiliyor. Diğer uygulamaları kapatıp tekrar deneyin.') }},
    errGeneric: {{ \Illuminate\Support\Js::from($en ? 'Could not access camera/microphone: ' : 'Kameraya/mikrofona erişilemedi: ') }},
    canComplete: {{ \Illuminate\Support\Js::from($en ? 'Session can be completed' : 'Ders tamamlanabilir') }},
    remaining: {{ \Illuminate\Support\Js::from($en ? 'Time remaining: ' : 'Kalan süre: ') }},
    seconds: {{ \Illuminate\Support\Js::from($en ? 's' : 'sn') }},
    sessionCompleted: {{ \Illuminate\Support\Js::from($en ? 'Session marked as completed' : 'Ders tamamlandı olarak işaretlendi') }},
    recordingUploaded: {{ \Illuminate\Support\Js::from($en ? 'Recording uploaded to the server' : 'Kayıt sunucuya aktarıldı') }},
    stopRecording: {{ \Illuminate\Support\Js::from($en ? 'Stop recording' : 'Kaydı durdur') }},
    startRecording: {{ \Illuminate\Support\Js::from($en ? 'Start recording' : 'Kaydı başlat') }},
    raiseHand: {{ \Illuminate\Support\Js::from($en ? 'Raise hand' : 'El kaldır') }},
    lowerHand: {{ \Illuminate\Support\Js::from($en ? 'Lower hand' : 'Eli indir') }},
    openFile: {{ \Illuminate\Support\Js::from($en ? 'Open file' : 'Dosyayı aç') }},
    mutedByHost: {{ \Illuminate\Support\Js::from($en ? 'The instructor muted everyone' : 'Eğitmen herkesi susturdu') }},
    cameraOffByHost: {{ \Illuminate\Support\Js::from($en ? 'The instructor turned off all cameras' : 'Eğitmen tüm kameraları kapattı') }},
    screenOffByHost: {{ \Illuminate\Support\Js::from($en ? 'The instructor stopped all screen shares' : 'Eğitmen tüm ekran paylaşımlarını durdurdu') }},
    score: {{ \Illuminate\Support\Js::from($en ? 'Score' : 'Puan') }},
    saveAndSend: {{ \Illuminate\Support\Js::from($en ? 'Save & send' : 'Kaydet ve gönder') }},
    deleteFile: {{ \Illuminate\Support\Js::from($en ? 'Delete' : 'Sil') }},
    confirmDeleteFile: {{ \Illuminate\Support\Js::from($en ? 'Delete this file for everyone?' : 'Bu dosya herkes için silinsin mi?') }}
};
const send=async(type,payload,recipient_id=null)=>fetch(root.dataset.send,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({type,payload,recipient_id})});
function setStatus(t){document.getElementById('roomStatus').textContent=t}
function tileEl(id){return id==='local'?document.getElementById('localTile'):document.getElementById('tile-'+id)}
function updatePinButtons(){document.querySelectorAll('.tile-pin').forEach(function(btn){btn.classList.toggle('is-pinned',pinnedId===btn.dataset.tileId)})}
function promote(id){
    const el=tileEl(id);if(!el)return;
    const main=document.getElementById('stageMain'),list=document.getElementById('participants');
    const emptyStage=document.getElementById('emptyStage');
    if(featuredId){const prev=tileEl(featuredId);if(prev&&prev!==el)list.appendChild(prev)}
    if(emptyStage&&emptyStage.parentElement===main)emptyStage.remove();
    main.appendChild(el);
    featuredId=id;
    updatePinButtons();
}
function togglePin(id){pinnedId=(pinnedId===id)?null:id;updatePinButtons();if(pinnedId)promote(id)}
document.addEventListener('click',function(e){
    const pinBtn=e.target.closest('.tile-pin');
    if(pinBtn){e.stopPropagation();togglePin(pinBtn.dataset.tileId);return}
    const tile=e.target.closest('#participants .video-tile');
    if(tile)promote(tile.dataset.tileId);
});
/**
 * Ensures a tile exists for a participant without ever touching one that's
 * already there -- heartbeat() calls this for everyone present every 5s,
 * and a live <video srcObject> would be destroyed and re-negotiated for
 * nothing if the row were rebuilt from scratch each time.
 */
function ensureTile(id,name){
    let tile=document.getElementById('tile-'+id);
    if(tile)return tile;
    tile=document.createElement('div');tile.className='video-tile';tile.id='tile-'+id;tile.dataset.tileId=String(id);
    tile.innerHTML='<video autoplay playsinline></video><div class="tile-placeholder"><span class="tile-avatar-lg">'+name.slice(0,1).toUpperCase()+'</span></div><div class="hand-badge" data-hand-badge>✋</div><div class="tile-footer"><span class="tile-avatar">'+name.slice(0,1).toUpperCase()+'</span><span>'+name+'</span><b>'+micIconHtml+'</b><button type="button" class="tile-pin" data-tile-id="'+id+'" title="{{ $en ? "Pin to main stage" : "Büyük ekranda sabitle" }}">'+targetIconHtml+'</button></div>';
    document.getElementById('participants').appendChild(tile);
    if(!isInstructor&&id===instructor&&!pinnedId)promote(id);
    else if(!featuredId&&!pinnedId)promote(id);
    return tile;
}
function addRemote(id,name){
    const tile=ensureTile(id,name);
    const video=tile.querySelector('video');
    const placeholder=tile.querySelector('.tile-placeholder');
    if(placeholder)placeholder.style.display='none';
    video.style.display='';
    return video;
}
function peer(id,name,initiator){if(peers[id])return peers[id];let pc=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});peers[id]=pc;if(stream)stream.getTracks().forEach(t=>pc.addTrack(t,stream));pc.ontrack=e=>addRemote(id,name).srcObject=e.streams[0];pc.onicecandidate=e=>e.candidate&&send('candidate',e.candidate,id);pc.onconnectionstatechange=()=>{if(['failed','disconnected','closed'].includes(pc.connectionState)){delete peers[id]}};if(initiator)pc.createOffer().then(o=>pc.setLocalDescription(o).then(()=>send('offer',o,id)));return pc}
async function heartbeat(){
    if(!stream)return;
    await fetch(root.dataset.presence,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
    let users=await(await fetch(root.dataset.presence,{headers:{'Accept':'application/json'}})).json();
    let presentIds=new Set(['local']);
    users.forEach(u=>{
        if(u.id===me)return;
        presentIds.add(String(u.id));
        knownNames[u.id]=u.name;
        const tile=ensureTile(u.id,u.name);
        const badge=tile.querySelector('[data-hand-badge]');
        if(badge)badge.classList.toggle('is-visible',raisedHands.has(u.id));
        peer(u.id,u.name,instructor===me);
    });
    document.querySelectorAll('.video-tile[data-tile-id]').forEach(function(tile){
        if(!presentIds.has(tile.dataset.tileId)){
            if(peers[tile.dataset.tileId])delete peers[tile.dataset.tileId];
            if(featuredId===tile.dataset.tileId){featuredId=null;promote('local')}
            tile.remove();
        }
    });
    document.getElementById('participantCount').textContent=users.length;
    setTimeout(heartbeat,5000);
}
function showPresentation(data){
    const stage=document.getElementById('presentationStage');
    const isPdf=(data.type||'')==='application/pdf';
    stage.innerHTML=isPdf?('<iframe src="'+data.url+'"></iframe>'):('<div class="presentation-fallback"><p>'+data.title+'</p><a class="btn btn-outline-secondary" href="'+data.url+'" target="_blank" rel="noopener">'+i18n.openFile+'</a></div>');
    stage.style.display='block';
}
function hidePresentation(){const stage=document.getElementById('presentationStage');stage.style.display='none';stage.innerHTML=''}
/**
 * Shared by the local upload confirmation and the live 'document-shared'
 * broadcast others receive -- a document a participant shares lands in the
 * Files panel for everyone, with a grade form for the instructor if it
 * isn't scored yet. Grading itself (see the submit handler below) is a
 * separate, after-the-fact review action, not part of this broadcast.
 */
function renderFileRow(data){
    if(document.getElementById('file-'+data.id))return;
    document.getElementById('filesEmpty').style.display='none';
    const row=document.createElement('div');
    row.className='shared-file';row.id='file-'+data.id;row.dataset.materialId=data.id;
    const gradeSlot=isInstructor
        ? '<form class="grade-form" data-grade-form data-material-id="'+data.id+'"><input type="number" min="0" max="100" placeholder="'+i18n.score+'" class="grade-score-input" required><button type="submit">'+i18n.saveAndSend+'</button></form>'
        : '';
    const deleteBtn=(isInstructor||data.uploaderId===me)
        ? '<button type="button" class="shared-file-delete" data-delete-file title="'+i18n.deleteFile+'">'+trashIconHtml+'</button>'
        : '';
    row.innerHTML='<span>'+fileIconHtml+'</span><div class="shared-file-body"><a href="'+data.url+'" target="_blank" rel="noopener">'+data.title+'</a><small>'+(data.uploaderName||'')+'</small><div class="shared-file-grade" data-grade-slot>'+gradeSlot+'</div></div>'+deleteBtn;
    document.getElementById('filesList').prepend(row);
}
function removeFileRow(id){
    document.getElementById('file-'+id)?.remove();
    if(!document.getElementById('filesList').children.length)document.getElementById('filesEmpty').style.display='';
}
document.getElementById('filesList')?.addEventListener('click',async function(e){
    const btn=e.target.closest('[data-delete-file]');
    if(!btn)return;
    const row=btn.closest('.shared-file');
    if(!row||!confirm(i18n.confirmDeleteFile))return;
    const id=row.dataset.materialId;
    const url=root.dataset.deleteUrlTemplate.replace('__ID__',id);
    const res=await fetch(url,{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
    if(!res.ok)return;
    removeFileRow(id);
    send('document-removed',{id:Number(id)});
});
document.getElementById('documentBtn')?.addEventListener('click',()=>document.getElementById('documentInput').click());
document.getElementById('presentBtn')?.addEventListener('click',()=>document.getElementById('presentInput').click());
document.getElementById('documentInput')?.addEventListener('change',async function(e){
    const file=e.target.files[0];if(!file)return;
    const fd=new FormData();fd.append('file',file);
    const res=await fetch(root.dataset.shareFiles,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd});
    if(!res.ok){e.target.value='';return}
    const data=await res.json();
    renderFileRow(data);
    send('document-shared',data);
    e.target.value='';
});
document.getElementById('filesList')?.addEventListener('submit',async function(e){
    const form=e.target.closest('[data-grade-form]');
    if(!form)return;
    e.preventDefault();
    const score=form.querySelector('.grade-score-input').value;
    const url=root.dataset.gradeUrlTemplate.replace('__ID__',form.dataset.materialId);
    const res=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:JSON.stringify({score})});
    if(!res.ok)return;
    const result=await res.json();
    const slot=form.closest('[data-grade-slot]')||form.parentElement;
    slot.innerHTML='<span class="shared-file-score">'+result.score+' / 100</span>';
});
async function poll(){
    if(!stream)return;
    let data=await(await fetch(root.dataset.signals,{headers:{'Accept':'application/json'}})).json();
    for(const s of data){
        if(s.type==='offer'||s.type==='answer'||s.type==='candidate'){
            let p=peers[s.sender_id]||peer(s.sender_id,knownNames[s.sender_id]||i18n.participant,false),payload=JSON.parse(s.payload);
            if(s.type==='offer'){await p.setRemoteDescription(payload);let answer=await p.createAnswer();await p.setLocalDescription(answer);await send('answer',answer,s.sender_id)}
            else if(s.type==='answer')await p.setRemoteDescription(payload);
            else if(s.type==='candidate')try{await p.addIceCandidate(payload)}catch(e){}
        }else if(s.type==='mute-all'){
            let t=stream.getAudioTracks()[0];if(t&&t.enabled){t.enabled=false;document.getElementById('mute').classList.add('is-off');document.getElementById('localMic').innerHTML=micOffIconHtml;setStatus(i18n.mutedByHost)}
        }else if(s.type==='camera-off-all'){
            let t=stream.getVideoTracks()[0];if(t&&t.enabled){t.enabled=false;document.getElementById('camera').classList.add('is-off');setStatus(i18n.cameraOffByHost)}
        }else if(s.type==='screenshare-stop-all'){
            if(isScreenSharing){stopScreenShare();setStatus(i18n.screenOffByHost)}
        }else if(s.type==='hand-raise'){
            let payload=JSON.parse(s.payload);
            if(payload.raised)raisedHands.add(s.sender_id);else raisedHands.delete(s.sender_id);
            let badge=document.querySelector('#tile-'+s.sender_id+' [data-hand-badge]');
            if(badge)badge.classList.toggle('is-visible',payload.raised);
        }else if(s.type==='presentation-shown'){
            showPresentation(JSON.parse(s.payload));
        }else if(s.type==='presentation-hidden'){
            hidePresentation();
        }else if(s.type==='document-shared'){
            renderFileRow(JSON.parse(s.payload));
        }else if(s.type==='document-removed'){
            removeFileRow(JSON.parse(s.payload).id);
        }
    }
    setTimeout(poll,1000)
}
async function checkAttendance(){if(!stream||!root.dataset.attendanceStatus)return;let data=await(await fetch(root.dataset.attendanceStatus,{headers:{'Accept':'application/json'}})).json();let btn=document.getElementById('completeSession');if(btn){btn.disabled=!data.eligible;btn.title=data.eligible?i18n.canComplete:i18n.remaining+Math.max(0,data.required_seconds-data.attended_seconds)+' '+i18n.seconds}setTimeout(checkAttendance,5000)}
function showJoinError(msg){let el=document.getElementById('joinError');if(el){el.textContent=msg;el.style.display='block'}}
async function join(){
if(stream)return;
const joinBtn=document.getElementById('join'),errorEl=document.getElementById('joinError');
const wantVideo=document.getElementById('wantVideo')?.checked??true,wantAudio=document.getElementById('wantAudio')?.checked??true;
if(errorEl)errorEl.style.display='none';
if(!wantVideo&&!wantAudio){showJoinError(i18n.joinNoDevice);return}
joinBtn.disabled=true;
try{
stream=await navigator.mediaDevices.getUserMedia({video:wantVideo,audio:wantAudio});
}catch(err){
joinBtn.disabled=false;
const messages={NotAllowedError:i18n.errNotAllowed,NotFoundError:i18n.errNotFound,NotReadableError:i18n.errNotReadable};
showJoinError(messages[err.name]||(i18n.errGeneric+err.message));
return;
}
document.getElementById('localVideo').srcObject=stream;
document.getElementById('localVideo').style.display='';
document.getElementById('localPlaceholder').style.display='none';
document.querySelectorAll('.control:not(#join)').forEach(b=>b.disabled=false);
if(!stream.getVideoTracks().length)document.getElementById('camera').disabled=true;
if(!stream.getAudioTracks().length)document.getElementById('mute').disabled=true;
document.getElementById('completeSession')&&(document.getElementById('completeSession').disabled=true);
joinBtn.disabled=true;
setStatus(i18n.connected);
promote('local');
heartbeat();poll();checkAttendance();
}
document.getElementById('join').onclick=join;document.getElementById('mute').onclick=()=>{let t=stream?.getAudioTracks()[0];if(t){t.enabled=!t.enabled;document.getElementById('mute').classList.toggle('is-off',!t.enabled);document.getElementById('localMic').innerHTML=t.enabled?micIconHtml:micOffIconHtml}};document.getElementById('camera').onclick=()=>{let t=stream?.getVideoTracks()[0];if(t){t.enabled=!t.enabled;document.getElementById('camera').classList.toggle('is-off',!t.enabled)}};function stopScreenShare(){let sender=Object.values(peers).flatMap(p=>p.getSenders()).find(x=>x.track?.kind==='video');if(sender&&stream.getVideoTracks()[0])sender.replaceTrack(stream.getVideoTracks()[0]);document.getElementById('localVideo').srcObject=stream;isScreenSharing=false;document.getElementById('screen').classList.remove('is-off')}
document.getElementById('screen').onclick=async()=>{if(!stream){await join();if(!stream)return}if(isScreenSharing){stopScreenShare();return}let s=await navigator.mediaDevices.getDisplayMedia({video:true}),sender=Object.values(peers).flatMap(p=>p.getSenders()).find(x=>x.track?.kind==='video');if(sender)sender.replaceTrack(s.getVideoTracks()[0]);document.getElementById('localVideo').srcObject=s;isScreenSharing=true;document.getElementById('screen').classList.add('is-off');s.getVideoTracks()[0].onended=()=>stopScreenShare()};document.getElementById('leave').onclick=()=>{stream?.getTracks().forEach(t=>t.stop());location.href='{{ route('learning.index') }}'};
document.getElementById('raiseHand').onclick=function(){handRaised=!handRaised;this.classList.toggle('is-off',handRaised);document.getElementById('raiseHandLabel').textContent=handRaised?i18n.lowerHand:i18n.raiseHand;document.getElementById('localHandBadge').classList.toggle('is-visible',handRaised);send('hand-raise',{raised:handRaised})};
document.getElementById('completeSession')?.addEventListener('click',async()=>{let r=await fetch(root.dataset.complete,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});if(r.ok||r.redirected){setStatus(i18n.sessionCompleted);document.getElementById('completeSession').disabled=true}});document.getElementById('record')?.addEventListener('click',async e=>{if(!recorder){if(!stream){await join();if(!stream)return}chunks=[];recorder=new MediaRecorder(stream,{mimeType:'video/webm'});recorder.ondataavailable=x=>x.data.size&&chunks.push(x.data);recorder.onstop=async()=>{let fd=new FormData();fd.append('recording',new File([new Blob(chunks,{type:'video/webm'})],'session-recording.webm',{type:'video/webm'}));await fetch(root.dataset.recording,{method:'POST',headers:{'X-CSRF-TOKEN':csrf},body:fd});setStatus(i18n.recordingUploaded)};recorder.start();e.currentTarget.querySelector('span').textContent=i18n.stopRecording}else{recorder.stop();recorder=null;e.currentTarget.querySelector('span').textContent=i18n.startRecording}});
document.getElementById('presentInput')?.addEventListener('change',async function(e){
    const file=e.target.files[0];if(!file)return;
    if(document.getElementById('presentationStage').style.display==='block'){hidePresentation();send('presentation-hidden',{})}
    const fd=new FormData();fd.append('file',file);
    const res=await fetch(root.dataset.shareFiles,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:fd});
    if(!res.ok){e.target.value='';return}
    const data=await res.json();
    showPresentation(data);
    send('presentation-shown',data);
    e.target.value='';
});
document.getElementById('muteAllBtn')?.addEventListener('click',()=>send('mute-all',{}));
document.getElementById('camerasOffAllBtn')?.addEventListener('click',()=>send('camera-off-all',{}));
document.getElementById('screensOffAllBtn')?.addEventListener('click',()=>send('screenshare-stop-all',{}));
</script>
@endsection
