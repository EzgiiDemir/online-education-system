@props(['brandRoute', 'groups'])
<aside class="app-sidebar" id="appSidebar">
<div class="sidebar-brand">
<a class="sidebar-brand-link" href="{{ $brandRoute }}"><span class="brand-full">Arucad Campus</span> <span>LMS</span></a>
<button type="button" class="sidebar-collapse-btn" data-sidebar-collapse aria-label="Menüyü daralt/genişlet"><x-lucide name="arrow-left" size="18" /></button>
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
