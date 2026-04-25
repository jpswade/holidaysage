@props([
    'name' => 'circle',
    'class' => 'h-4 w-4',
])

@php
    $paths = [
        'users' => ['M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2', 'M8.5 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M20 8v6', 'M23 11h-6'],
        'waves' => ['M2 6c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2', 'M2 12c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2', 'M2 18c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2'],
        'plane' => ['M17.8 19.2 16 11l5-3.5a1 1 0 0 0-.6-1.8L14 6l-3.2-4.6a1 1 0 0 0-1.8.2L8 6l-5.4-.3A1 1 0 0 0 2 7.5L7 11l-1.8 8.2a1 1 0 0 0 1.5 1.1L11 18l4.3 2.3a1 1 0 0 0 1.5-1.1Z'],
        'car' => ['M14 16H9m10 0h1a1 1 0 0 0 .96-1.27l-1.35-4.86A2 2 0 0 0 17.7 8H6.3a2 2 0 0 0-1.91 1.87L3.04 14.73A1 1 0 0 0 4 16h1', 'M6 16v2', 'M18 16v2', 'M7 12h10'],
        'triangle-alert' => ['m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3', 'M12 9v4', 'M12 17h.01'],
        'trending-up' => ['M22 7 13.5 15.5 8.5 10.5 2 17', 'M16 7h6v6'],
        'clock-3' => ['circle 12 12 10', 'M12 6v6l4 2'],
        'refresh-cw' => ['M21 2v6h-6', 'M3 22v-6h6', 'M3.5 9a9 9 0 0 1 14.13-3.36L21 8', 'M20.5 15a9 9 0 0 1-14.13 3.36L3 16'],
        'footprints' => ['M4 16s1.5 2 4 2 4-2 4-4-1.5-4-4-4-4 2-4 4 1.5 2 4 2', 'M14 10s1.2 1.6 3.5 1.6S21 10.2 21 8s-1.4-3.6-3.5-3.6S14 5.8 14 8c0 1 .3 1.5.8 2'],
    ];
    $icon = $paths[$name] ?? [];
@endphp

<svg {{ $attributes->merge(['class' => $class]) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @foreach ($icon as $path)
        @if (str_starts_with($path, 'circle '))
            @php [, $cx, $cy, $r] = explode(' ', $path); @endphp
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"></circle>
        @else
            <path d="{{ $path }}"></path>
        @endif
    @endforeach
</svg>
