{{-- resources/views/components/flash-message.blade.php --}}
@if (session('status'))
    @php
        $color = match (session('status_level')) {
            'success' => 'bg-green-600',
            'error' => 'bg-red-600',
            'warning' => 'bg-yellow-600',
            default => 'bg-blue-600',
        };
    @endphp
    <div {{ $attributes->merge(['class' => "mb-4 px-4 py-2 rounded-sm text-white {$color}"]) }}>
        {{ session('status') }}
    </div>
@endif
