<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Benvenuto</title>
</head>
<body style="background-color: #f9f9f9; font-family: Arial, sans-serif; padding: 30px; color: #333;">
    <div style="max-width: 600px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05);">
        <h2 style="color: #2c3e50;">Grazie per esserti registrato</h2>
        <p style="font-size: 16px;">Benvenuto <strong>{{ $name }}</strong>!</p>
        <p style="margin-top: 20px;">Siamo felici di averti con noi. Se hai domande o problemi, non esitare a contattarci.</p>
        <a href="{{ url('/') }}" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #3490dc; color: white; text-decoration: none; border-radius: 4px;">
            Torna al sito
        </a>
    </div>
    <p style="text-align: center; font-size: 12px; color: #999; margin-top: 40px;">
        © {{ now()->year }} Il tuo sito. Tutti i diritti riservati.
    </p>
</body>
</html>
