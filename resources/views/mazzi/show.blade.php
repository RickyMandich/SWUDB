@extends('layouts.app')
@section('title', "$nome di $user")
@section('content')
    <livewire:deck-manager
        :nome="$nome"
        :user="$user"
        :deck="$deck"
        :deckObject="$deckObject"
        :size="$size"
        :proprietario="$proprietario"
        :carte="$carte"
        :mazzo="$mazzo"
    />
@endsection

@push('scripts')
    <script>
        document.addEventListener('livewire:initialized', () => {
            // Gestione delle notifiche di sistema
            @if(session('warning'))
                Livewire.dispatch('showMessage', {
                    type: 'warning',
                    message: '{{ session('warning') }}'
                });
            @endif
            @if(session('success'))
                Livewire.dispatch('showMessage', {
                    type: 'success',
                    message: '{{session('success')}}'
                });
            @endif
        });
    </script>
@endpush