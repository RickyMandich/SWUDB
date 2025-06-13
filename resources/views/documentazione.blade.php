@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">{{ 'Documentazione' }}</div>
                <div class="card-body">
                    <div class="markdown-content">
                        {!! Illuminate\Support\Str::markdown(file_get_contents(base_path('documentation.md'))) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
