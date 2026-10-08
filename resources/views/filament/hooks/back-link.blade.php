@unless(request()->is('admin') || request()->is('admin/') || request()->is('admin/login'))
@php($en = app()->getLocale() === 'en')
<button type="button" class="fi-back-link" onclick="history.length > 1 ? history.back() : (window.location = '{{ url('/admin') }}')">
    ← {{ $en ? 'Back' : 'Geri' }}
</button>
<style>
.fi-back-link{display:inline-flex;align-items:center;gap:6px;margin:0 0 12px;padding:0;background:none;border:0;box-shadow:none;color:#64748b;font-size:13px;font-weight:600;cursor:pointer}
.fi-back-link:hover{color:#334155}
</style>
@endunless
