@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>{{ __('custom.Guida Avanzata') }}</span>
                        <a href="{{ route('documentazione') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i>Torna alla Documentazione
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="markdown-content">
                        {!! Illuminate\Support\Str::markdown(file_get_contents(base_path('guida-avanzata.md'))) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
