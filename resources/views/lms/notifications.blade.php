@php($notificationsLayout = match(auth()->user()->role) { 'instructor' => 'lms.instructor-layout', 'manager' => 'lms.manager-layout', default => 'lms.layout' })
@extends($notificationsLayout)
@section('title', (session('locale', 'tr') === 'en' ? 'Notifications' : 'Bildirimler').' · Arucad Campus LMS')
@section('page-title', session('locale', 'tr') === 'en' ? 'Notifications' : 'Bildirimler')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'NOTIFICATIONS' : 'BİLDİRİMLER' }}</span><h1>{{ $en ? 'Notifications' : 'Bildirimler' }}</h1></div>
@if(auth()->user()->unreadNotifications->isNotEmpty())
<form method="post" action="{{ route('notifications.read-all') }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-outline-secondary">{{ $en ? 'Mark all read' : 'Tümünü okundu işaretle' }}</button></form>
@endif
</div>
<div class="card surface">
@forelse($notifications as $notification)
@if($loop->first)<ul class="list-group list-group-flush">@endif
<li class="list-group-item requirement-row" style="align-items:flex-start">
<div>
<b style="{{ $notification->read_at ? '' : 'color:var(--brand-dark)' }}">@unless($notification->read_at)<span class="badge" style="margin-right:6px">{{ $en ? 'NEW' : 'YENİ' }}</span>@endunless{{ $notification->data['message'] ?? '—' }}</b><br>
<small class="muted">{{ $notification->created_at->format('d.m.Y H:i') }}</small>
</div>
@unless($notification->read_at)
<form method="post" action="{{ route('notifications.read', $notification->id) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-outline-secondary">{{ $en ? 'Mark read' : 'Okundu' }}</button></form>
@endunless
</li>
@if($loop->last)</ul>@endif
@empty
<p class="muted" style="margin:0">{{ $en ? 'No notifications yet.' : 'Henüz bildirim yok.' }}</p>
@endforelse
</div>
{{ $notifications->links('lms.partials.pagination') }}
@endsection
