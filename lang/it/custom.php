<?php

return[
    /*
    |--------------------------------------------------------------------------
    | Custom Language Lines
    |--------------------------------------------------------------------------
    | Le seguenti righe di linguaggio sono i messaggi che compaiono
    | in diverse parti dell'applicazione. Puoi cambiarli per personalizzare
    | l'aspetto della tua applicazione.
    |
    */
    'welcome' => config("app.domain", "UnlimitedDB.net").' è un sito creato dai fan per i fan di Star Wars: Unlimited.',
    'upperFooter' => 
        config("app.domain", "SWUDB.net")." v.".env("APP_VERSION").' è un sito non ufficiale fatto dai fan. Le informazioni testuali e grafiche presenti sul sito relative a Star Wars: Unlimited, incluse immagini delle carte e simboli, hanno il copyright di Fantasy Flight Publishing Inc e Lucasfilm Ltd. '.config("app.domain", "SWUDB.net").' non è prodotto o approvato da FFG or LFL.',
    'lowerFooter' => 
        'Tutti gli altri contenuti © 2023 - 2025 '.config("app.domain", "SWUDB.net").". l'uso di questo sito costituisce l'accettazione dei termini di servizio di ".config("app.domain", "SWUDB.net"),
    'swudb' => config("app.domain", "SWUDB.net"),
    'SWUDB' => config("app.domain", "SWUDB.net"),
    'mazzi' => 'Mazzi',
    'carte' => 'Carte',
    'searchCard' => 'Cerca una Carta',
    'Login' => 'Accedi',
    'Register' => 'Registrati',
    'Logout' => 'Esci',
    'Dashboard' => 'Profilo',
    'refreshDB' => 'Aggiorna il Database',
    'next' => 'Prossima',
    'back' => 'Precedente',
    'query' => 'Accedi al Database',
    'nome' => 'Nome',
    'email' => 'Email',
    'Benvenuto, ' => 'Benvenuto, ',
    'contactMail' => "puoi contattarci a <a class='text-muted' href='mailto:info@unlimiteddb.net'>info@unlimiteddb.net</a>,",
    'documentazione' => 'qui puoi trovare la',
    'Documentazione' => 'Documentazione',
    'Guida Avanzata' => 'Guida Avanzata',
    'Collezione' => 'Collezione'
];