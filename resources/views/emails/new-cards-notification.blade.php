<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuove Carte SWUDB</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background: #495057;
            padding: 20px;
            border-radius: 0 0 8px 8px;
        }
        .card-item {
            background: #343a40;
            margin: 10px 0;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card-name {
            font-weight: bold;
            color: #007bff;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .card-details {
            font-size: 14px;
            color: #666;
        }
        .card-link {
            display: inline-block;
            margin-top: 8px;
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        .card-link:hover {
            text-decoration: underline;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
        .stats {
            background: #6c757d;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Nuove Carte Aggiunte!</h1>
        <p>Star Wars Unlimited Database</p>
    </div>

    <div class="content">
        <div class="stats">
            <h2>{{ $count }} {{ $count === 1 ? 'nuova carta aggiunta' : 'nuove carte aggiunte' }}</h2>
            <p>Le seguenti carte sono state appena aggiunte al database SWUDB:</p>
        </div>

        @foreach($newCards as $card)
            <div class="card-item">
                <div class="card-name">{{ $card['nome'] ?? 'Nome non disponibile' }}</div>
                <div class="card-details">
                    @if(isset($card['espansione']))
                        <strong>Espansione:</strong> {{ $card['espansione'] }}<br>
                    @endif
                    @if(isset($card['numero']))
                        <strong>Numero:</strong> {{ $card['numero'] }}<br>
                    @endif
                    @if(isset($card['costo']))
                        <strong>Costo:</strong> {{ $card['costo'] }}<br>
                    @endif
                    @if(isset($card['tipo']))
                        <strong>Tipo:</strong> {{ $card['tipo'] }}<br>
                    @endif
                    @if(isset($card['aspetto']))
                        <strong>Aspetto:</strong> {{ $card['aspetto'] }}<br>
                    @endif
                </div>
                @if(isset($card['cid']))
                    <a href="{{ url('/carte/' . $card['cid']) }}" class="card-link">
                        👁️ Visualizza Carta
                    </a>
                @endif
            </div>
        @endforeach

        <div style="margin-top: 30px; text-align: center;">
            <a href="{{ url('/carte') }}" style="background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
                🔍 Esplora Tutte le Carte
            </a>
        </div>
    </div>

    <div class="footer">
        <p>Questa è una notifica automatica da SWUDB.<br>
        Visita <a href="{{ url('/') }}">{{ url('/') }}</a> per esplorare il database completo.</p>
    </div>
</body>
</html>
