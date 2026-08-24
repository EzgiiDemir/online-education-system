@auth
@php($workspaces = auth()->user()->workspaces())
@if(count($workspaces) > 1)
@php($en = session('locale', 'tr') === 'en')
@php($labels = ['learner' => $en ? 'Learner' : 'Öğrenci', 'instructor' => $en ? 'Instructor' : 'Eğitmen', 'manager' => $en ? 'Manager' : 'Yönetici', 'admin' => $en ? 'Admin' : 'Yönetici (Admin)'])
@php($routes = ['learner' => route('lms.home'), 'instructor' => route('instructor.dashboard'), 'manager' => route('manager.dashboard'), 'admin' => url('/admin')])
@php($current = auth()->user()->role)
<div class="workspace-switcher dropdown">
<button class="workspace-switcher-trigger" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
<x-lucide name="layout-grid" size="14" />
<span>{{ $en ? 'Workspace' : 'Çalışma alanı' }}: {{ $labels[$current] ?? $current }}</span>
<x-lucide name="chevron-down" size="14" />
</button>
<div class="dropdown-menu workspace-switcher-menu">
@foreach($workspaces as $key)
<a class="dropdown-item {{ $key === $current ? 'is-active' : '' }}" href="{{ $routes[$key] ?? '#' }}">{{ $labels[$key] ?? $key }}</a>
@endforeach
</div>
</div>
@endif
@endauth
