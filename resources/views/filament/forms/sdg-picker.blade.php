@php($language = $get('language') ?: 'tr')
@php($languageGoals = $goals[$language] ?? $goals['tr'])
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4" x-data style="display:grid !important;grid-template-columns:repeat(4,minmax(0,1fr)) !important;gap:12px !important;">
    @foreach($languageGoals as $number => $label)
        <label class="group relative cursor-pointer overflow-hidden rounded-lg border bg-white p-1 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900" style="display:block !important;min-width:0;" wire:key="sdg-{{ $number }}">
            <input type="checkbox" class="peer absolute right-1 top-1 z-10 h-4 w-4" value="{{ $number }}" wire:model="{{ $getStatePath() }}">
            @php($iconName = 'goal-'.str_pad($number, 2, '0', STR_PAD_LEFT))
            @php($localizedSvg = public_path('images/sdg/'.$language.'/'.$iconName.'.svg'))
            @php($localizedPng = public_path('images/sdg/'.$language.'/'.$iconName.'.png'))
            @php($iconPath = file_exists($localizedSvg) ? asset('images/sdg/'.$language.'/'.$iconName.'.svg') : (file_exists($localizedPng) ? asset('images/sdg/'.$language.'/'.$iconName.'.png') : asset('images/sdg/'.$iconName.'.png')))
            <img src="{{ $iconPath }}" alt="SDG {{ $number }} - {{ $label }}" class="mx-auto rounded-md object-cover" style="width:72px !important;height:72px !important;max-width:72px !important;">
            <div class="mt-1 text-center text-[10px] font-semibold leading-tight text-gray-700 dark:text-gray-200">{{ $number }} · {{ $label }}</div>
        </label>
    @endforeach
</div>
<p class="mt-2 text-sm text-gray-500">{{ $language === 'en' ? 'Select the official UN goals related to this course.' : 'Eğitimle ilişkili resmi BM amaçlarını seçin.' }}</p>
