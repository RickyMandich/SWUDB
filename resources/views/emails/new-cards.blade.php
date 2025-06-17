@extends('emails.layout')
@section('content')
    <h2 style="color: #2c3e50;">Nuove carte disponibili</h2>
    <p style="font-size: 16px;">Sono disponibili queste nuove carte:</p>

    <div style="margin: 20px 0;">
        @foreach($cards as $card)
            <div style="margin: 8px 0; padding: 10px; background-color: #f8f9fa; border-left: 4px solid #3490dc; border-radius: 4px;">
                <a href="{{ $card['url'] }}" style="color: #3490dc; text-decoration: none; font-weight: bold; font-size: 16px;">
                    {{ $card['snippet'] }}
                </a>
                <div style="font-size: 12px; color: #6c757d; margin-top: 4px;">
                    Clicca per visualizzare i dettagli della carta
                </div>
            </div>
        @endforeach
    </div>

    <p style="font-size: 14px; color: #6c757d; margin-top: 20px;">
        Clicca sui link sopra per visualizzare i dettagli di ogni carta nel database.
    </p>
@endsection
