@php($en = app()->getLocale() === 'en')
<div class="nav-lang" role="group" aria-label="{{ $en ? 'Language' : 'Dil' }}">
<a href="{{ route('language.switch', 'tr') }}" class="{{ ! $en ? 'is-active' : '' }}" hreflang="tr" lang="tr" aria-current="{{ ! $en ? 'true' : 'false' }}">TR</a>
<a href="{{ route('language.switch', 'en') }}" class="{{ $en ? 'is-active' : '' }}" hreflang="en" lang="en" aria-current="{{ $en ? 'true' : 'false' }}">EN</a>
</div>
