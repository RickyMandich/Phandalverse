<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferma la tua email</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap');
        
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f4f7f9;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        
        .header {
            background: linear-gradient(135deg, #6e45e2 0%, #88d3ce 100%);
            padding: 50px 30px;
            text-align: center;
            color: #ffffff;
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        
        .content {
            padding: 40px 30px;
            color: #333333;
            line-height: 1.6;
        }
        
        .content p {
            margin-bottom: 25px;
            font-size: 16px;
        }
        
        .cta-container {
            text-align: center;
            margin: 40px 0;
        }
        
        .button {
            background-color: #6e45e2;
            color: #ffffff !important;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(110, 69, 226, 0.3);
        }
        
        .footer {
            padding: 30px;
            text-align: center;
            background-color: #fafbfc;
            color: #888888;
            font-size: 13px;
        }
        
        .footer a {
            color: #6e45e2;
            text-decoration: none;
        }
        
        .divider {
            height: 1px;
            background-color: #eeeeee;
            margin: 30px 0;
        }
        
        .fallback {
            font-size: 12px;
            color: #999999;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Benvenuto su {{ config('app.name') }} 🌌</h1>
        </div>
        
        <div class="content">
            <p>Ciao <strong>{{ $user->name ?? 'Giocatore' }}</strong>,</p>
            <p>Siamo entusiasti di averti con noi! Per completare la tua registrazione e sbloccare tutte le funzionalità del portale (come il Vault e il DM Screen), devi confermare il tuo indirizzo email.</p>
            
            <div class="cta-container">
                <a href="{{ $url }}" class="button">Conferma Email Ora</a>
            </div>
            
            <p>Se non hai creato tu questo account, puoi semplicemente ignorare questa email.</p>
            
            <div class="divider"></div>
            
            <p class="fallback">
                Se hai problemi con il pulsante, copia e incolla questo link nel tuo browser:<br>
                <a href="{{ $url }}">{{ $url }}</a>
            </p>
        </div>
        
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Tutti i diritti riservati.</p>
            <p>Phandalin te ne sarà grata. 🐉</p>
        </div>
    </div>
</body>
</html>
