@extends('layouts.app')
@section('title', "Build $nome - Collezione")
@section('content')
    <livewire:deck-build-manager
        :nome="$nome"
        :user="$user"
        :deck="$deck"
        :deckObject="$deckObject"
        :proprietario="$proprietario"
    />
@endsection

@push('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            @if(session('warning'))
                Livewire.dispatch('showMessage', {
                    type: 'warning',
                    message: '{{ session('warning') }}'
                });
            @endif
            @if(session('success'))
                Livewire.dispatch('showMessage', {
                    type: 'success',
                    message: '{{ session('success') }}'
                });
            @endif
        });
    </script>
@endpush
