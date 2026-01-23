<!DOCTYPE html>
<html>

<head>
    <title>Richiesta Strumenti da Master</title>
</head>

<body>
    <h1>Nuova richiesta di accesso agli strumenti da Master</h1>
    <p>L'utente <strong>{{ $user->name }}</strong> ({{ $user->email }}) ha richiesto l'accesso agli strumenti da Master.
    </p>
    <p>Puoi gestire questa richiesta dalla pagina di gestione utenti nell'area admin.</p>
    <p>
        <a href="{{ route('admin.users') }}"
            style="display: inline-block; padding: 10px 20px; background-color: #0d6efd; color: white; text-decoration: none; border-radius: 5px;">
            Vai alla gestione utenti
        </a>
    </p>
</body>

</html>