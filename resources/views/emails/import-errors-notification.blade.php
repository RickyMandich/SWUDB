<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapporto Errori Importazione SWUDB</title>
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
            background: linear-gradient(135deg, #dc3545, #c82333);
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .success { color: #28a745; }
        .warning { color: #ffc107; }
        .danger { color: #dc3545; }
        .info { color: #17a2b8; }
        .secondary { color: #6c757d; }
        
        .error-list {
            background: white;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
        .error-item {
            padding: 10px;
            margin: 5px 0;
            background: #58151c;
            border: 1px solid #842029;
            border-radius: 3px;
            color: #ea868f;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
        }
        .summary {
            background: #6c757d;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Rapporto Errori Importazione</h1>
        <p>Star Wars Unlimited Database - Admin</p>
    </div>

    <div class="content">
        <div class="summary">
            <h3>Riepilogo Importazione</h3>
            <p>L'importazione delle carte è stata completata con alcuni problemi che richiedono attenzione.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number success">{{ $successCount }}</div>
                <div class="stat-label">Aggiunte</div>
            </div>
            <div class="stat-card">
                <div class="stat-number danger">{{ $errorCount }}</div>
                <div class="stat-label">Errori</div>
            </div>
            <div class="stat-card">
                <div class="stat-number warning">{{ $duplicateCount }}</div>
                <div class="stat-label">Duplicate</div>
            </div>
            <div class="stat-card">
                <div class="stat-number info">{{ $totalProcessed }}</div>
                <div class="stat-label">Elaborate</div>
            </div>
        </div>

        @if($errorCount > 0 && !empty($errors))
            <div class="error-list">
                <h4>🚨 Dettagli Errori</h4>
                <p>I seguenti errori si sono verificati durante l'importazione:</p>
                
                @foreach(array_slice($errors, 0, 10) as $error)
                    <div class="error-item">
                        {{ $error }}
                    </div>
                @endforeach
                
                @if(count($errors) > 10)
                    <div class="error-item" style="background: #d1ecf1; border-color: #bee5eb; color: #0c5460;">
                        ... e altri {{ count($errors) - 10 }} errori. Controlla i log per i dettagli completi.
                    </div>
                @endif
            </div>
        @endif

        @if($duplicateCount > 0)
            <div style="background: #664d03; border: 1px solid #997404; border-radius: 5px; padding: 15px; margin-top: 15px;">
                <h4>📋 Carte Duplicate</h4>
                <p>{{ $duplicateCount }} carte erano già presenti nel database e sono state saltate.</p>
            </div>
        @endif

        <div style="margin-top: 30px; text-align: center;">
            <a href="{{ url('/admin/errors') }}" style="background: #dc3545; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">
                🔧 Gestisci Errori
            </a>
            <a href="{{ url('/admin/dashboard') }}" style="background: #6c757d; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-left: 10px;">
                📊 Dashboard Admin
            </a>
        </div>
    </div>

    <div class="footer">
        <p>Questa è una notifica automatica per amministratori SWUDB.<br>
        Accedi al <a href="{{ url('/admin/dashboard') }}">pannello admin</a> per maggiori dettagli.</p>
    </div>
</body>
</html>
