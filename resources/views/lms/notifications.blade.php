@extends('lms.layout')
@section('title','Bildirimler · Arucad Campus LMS')
@section('content')
@php($en = session('locale', 'tr') === 'en')
<div class="section-heading"><div><span class="eyebrow">{{ $en ? 'NOTIFICATIONS' : 'BİLDİRİMLER' }}</span><h1>{{ $en ? 'Notifications' : 'Bildirimler' }}</h1></div>
@if(auth()->user()->unreadNotifications->isNotEmpty())
<form method="post" action="{{ route('notifications.read-all') }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="button secondary">{{ $en ? 'Mark all read' : 'Tümünü okundu işaretle' }}</button></form>
@endif
</div>
<div class="card surface">
@forelse($notifications as $notification)
<div class="requirement-row" style="align-items:flex-start">
<div>
<b style="{{ $notification->read_at ? '' : 'color:var(--brand-dark)' }}">{{ $notification->data['message'] ?? '—' }}</b><br>
<small class="muted">{{ $notification->created_at->format('d.m.Y H:i') }}</small>
</div>
@unless($notification->read_at)
<form method="post" action="{{ route('notifications.read', $notification->id) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="button secondary">{{ $en ? 'Mark read' : 'Okundu' }}</button></form>
@endunless
</div>
@empty
<p class="muted" style="margin:0">{{ $en ? 'No notifications yet.' : 'Henüz bildirim yok.' }}</p>
@endforelse
</div>
{{ $notifications->links('lms.partials.pagination') }}
@endsection
