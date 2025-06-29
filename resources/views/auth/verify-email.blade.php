@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card bg-dark border-secondary">
                <div class="card-header text-light">
                    <h4 class="mb-0">
                        <i class="fas fa-envelope-circle-check me-2"></i>
                        Verifica Email
                    </h4>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            {{ session('error') }}
                        </div>
                    @endif

                    @if (session('info'))
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            {{ session('info') }}
                        </div>
                    @endif

                    <div class="text-light mb-4">
                        <p>Non hai ricevuto l'email di verifica? Inserisci il tuo indirizzo email qui sotto per ricevere un nuovo link di verifica.</p>
                    </div>

                    <form method="POST" action="{{ route('email.resend') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label text-light">
                                <i class="fas fa-envelope me-2"></i>
                                Indirizzo Email
                            </label>
                            <input id="email" 
                                   type="email" 
                                   class="form-control bg-dark border-secondary text-light @error('email') is-invalid @enderror" 
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   required 
                                   autocomplete="email" 
                                   autofocus
                                   placeholder="Inserisci il tuo indirizzo email">

                            @error('email')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>
                                Reinvia Email di Verifica
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <a href="{{ route('login') }}" class="text-decoration-none">
                            <i class="fas fa-arrow-left me-2"></i>
                            Torna al Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
