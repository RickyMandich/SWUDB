{{-- resources/views/components/flash-message.blade.php --}}
@if (session('status'))
    <div
        class="mb-4 {{ match (session('status_level')) {'success' => 'bg-green-600','error' => 'bg-red-600','warning' => 'bg-yellow-600',default => 'bg-blue-600'} ?? '' }}">
        {{ session('status') }}
    </div>
@endif
