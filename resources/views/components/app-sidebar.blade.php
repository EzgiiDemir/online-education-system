@props(['brandRoute', 'groups'])
@php($en = session('locale', 'tr') === 'en')
<aside class="app-sidebar" id="appSidebar">
<div class="sidebar-brand">
<a class="sidebar-brand-link" href="{{ $brandRoute }}"><img src="{{ \App\Models\PortalSetting::current()->logoUrl() }}" alt="{{ \App\Models\PortalSetting::current()->portal_name }}" class="brand-logo"><span class="brand-full">{{ __('Eğitim Merkezi') }}</span></a>
<button type="button" class="sidebar-collapse-btn" data-sidebar-collapse aria-label="{{ $en ? 'Collapse/expand menu' : 'Menüyü daralt/genişlet' }}"><x-lucide name="arrow-left" size="18" /></button>
</div>
<nav class="sidebar-nav">
@foreach($groups as $group)
<div class="sidebar-group">
@if(!empty($group['label']))<span class="sidebar-group-label">{{ $group['label'] }}</span>@endif
@foreach($group['items'] as $item)
<a class="sidebar-link {{ !empty($item['active']) ? 'is-active' : '' }}" href="{{ $item['url'] }}" @if(!empty($item['target'])) target="{{ $item['target'] }}" @endif>
<x-lucide :name="$item['icon']" size="18" />
<span class="sidebar-link-label">{{ $item['label'] }}</span>
@if(!empty($item['count']))<span class="tab-count">{{ $item['count'] }}</span>@endif
</a>
@endforeach
</div>
@endforeach
</nav>
{{ $slot ?? '' }}
</aside>
<div class="sidebar-backdrop" data-sidebar-backdrop></div>
