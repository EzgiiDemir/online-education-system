@php($en = session('locale', 'tr') === 'en')
<ul class="nav nav-tabs">
<li class="nav-item"><a class="nav-link {{ $active === 'training' ? 'is-active' : '' }}" href="{{ route('reports.index') }}">{{ $en ? 'Training' : 'Kayıt' }}</a></li>
<li class="nav-item"><a class="nav-link {{ $active === 'completion' ? 'is-active' : '' }}" href="{{ route('reports.completion') }}">{{ $en ? 'Completion' : 'Tamamlanma' }}</a></li>
<li class="nav-item"><a class="nav-link {{ $active === 'attendance' ? 'is-active' : '' }}" href="{{ route('reports.attendance') }}">{{ $en ? 'Attendance' : 'Katılım' }}</a></li>
<li class="nav-item"><a class="nav-link {{ $active === 'instructors' ? 'is-active' : '' }}" href="{{ route('reports.instructors') }}">{{ $en ? 'Instructors' : 'Eğitmenler' }}</a></li>
</ul>
