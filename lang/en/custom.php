<?php

return[
    /*
    |--------------------------------------------------------------------------
    | Custom Language Lines
    |--------------------------------------------------------------------------
    | The following language lines are the messages that appear
    | in different parts of the application. You can change them to customize
    | the appearance of your application.
    |
    */
    'welcome' => config("app.domain", "UnlimitedDB.net").' is a fan-made site for Star Wars: Unlimited fans.',
    'upperFooter' => 
        config("app.domain", "SWUDB.net")." v.".env("APP_VERSION").' is an unofficial fan-made site. The textual and graphic information on the site related to Star Wars: Unlimited, including card images and symbols, are copyrighted by Fantasy Flight Publishing Inc and Lucasfilm Ltd. '.config("app.domain", "SWUDB.net").' is not produced or endorsed by FFG or LFL.',
    'lowerFooter' => 
        'All other content © 2023 - 2025 '.config("app.domain", "SWUDB.net").". Use of this site constitutes acceptance of ".config("app.domain", "SWUDB.net").' Terms of Service.',
    'swudb' => config("app.domain", "SWUDB.net"),
    'SWUDB' => config("app.domain", "SWUDB.net"),
    'mazzi' => 'Decks',
    'carte' => 'Cards',
    'searchCard' => 'Search a Card',
    'Login' => 'Login',
    'Register' => 'Register',
    'Logout' => 'Logout',
    'Dashboard' => 'Dashboard',
    'refreshDB' => 'Refresh Database',
    'next' => 'Next',
    'back' => 'Previous',
    'query' => 'Query Database',
    'nome' => 'Name',
    'email' => 'Email',
    'Benvenuto, ' => 'Welcome, ',
    'contactMail' => "you can contact us at <a class='text-muted' href='mailto:info@unlimiteddb.net'>info@unlimiteddb.net</a>,",
    'documentazione' => 'here you can find the',
    'Documentazione' => 'Documentation',
    'Guida Avanzata' => 'Advanced Guide',
    'Collezione' => 'Collection'
];