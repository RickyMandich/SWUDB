@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>

                <div class="card-body fs-4">
                    @if(session('error'))
                        <div class="row mb-3 justify-content">
                            <div class="col-md-12 text-danger text-center">
                                {{ session('error') }}
                            </div>
                        </div>
                    @endif
                    @if(session('warning'))
                        <div class="row mb-3 justify-content">
                            <div class="col-md-12 text-warning text-center">
                                {{ session('warning') }}
                            </div>
                        </div>
                    @endif
                    @if (session('status'))
                    <div class="row mb-3 justify-content">
                        <div class="col-md-12 text-warning text-center">
                            {{ session('status') }}
                        </div>
                    </div>
                    @endif


                    <div class="row mb-3 justify-content">
                        <div class="col-md-12 text-center">
                            <h1>{{ __('Benvenuto, ') . Auth::user()->name . '!' }}</h1>
                        </div>
                    </div>

                    <div class="row mb-3 justify-content">
                        <span class="col-md-5 text-md-end">
                            {{ __("nome") }}:
                        </span>
                        <span class="col-md-7">
                            {{ Auth::user()->name }}
                        </span>
                    </div>

                    <div class="row mb-3 justify-content">
                        <span class="col-md-5 text-md-end">
                            {{ __("email") }}:
                        </span>
                        <span class="col-md-7">
                            {{ Auth::user()->email }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
