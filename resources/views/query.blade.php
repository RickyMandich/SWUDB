@extends('layouts.app')
@section('title', $query)
@section('content')
    <form action="query">
        <label for="query">
            inserisci la query
        </label>
        <a href="{{ route("index") }}" class="btn btn-primary">torna al sito</a>
        <input type="text" name="query" id="inputQuery" value="{{ $query }}">
    </form>
    <?php $header = [];
    $j=0;?>
    @foreach ($result as $row)
    <?php $i=0;?>
        @foreach ($row as $key=>$column)
            @foreach ($result as $line)
                @if (!isset($line->$key))
                    <?php $line->$key = "";?>
                @endif
            @endforeach
            @if(!isset($header[$key]))
                <?php $header[$key] = "";?>
            @endif
        @endforeach
        <?php $i++;?>
    @endforeach
    <table border="">
        <tr>
            @foreach ($header as $key=>$value)
                <th>
                    {{ $key }}
                </th>
            @endforeach
        </tr>
        @foreach ($result as $row)
            <tr>
                @foreach ($row as $column)
                    <td>
                        {{ $column }}
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>
@endsection
@section('style')
    <style>
        input{
            display: block;
            width: 100%;
        }
    </style>
@endsection