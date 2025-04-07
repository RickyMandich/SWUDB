@extends('layouts.app')
@section('content')
    <style>
        .content {
            flex-grow: 1; /* Occupa tutto lo spazio disponibile */
            overflow: auto; /* Abilita lo scorrimento */
            max-width: 100%; /* Usa tutta la larghezza disponibile */
            height: 86.1vh; /* Altezza massima pari all'altezza dello schermo */
            display: flex;
            flex-direction: column;
        }
        
        .table thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa; /* Sfondo per l'intestazione */
            z-index: 1;
        }
    </style>
    <div class="container content">
        <div class="table-container">
            <table class="table table-hover table-striped table-bordered">
                <thead class="table-light sticky-top">
                    <tr>
                        @foreach ($result[0] as $key=> $value)
                            @if($key != "id" && $key != "snippet")
                                <th>{{ $key }}</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result as $row)
                    <tr>
                        @foreach ($row as $key=>$value)
                            @if($key != "id" && $key != "snippet")
                                <td>{{ $value }}</td>
                            @endif
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection