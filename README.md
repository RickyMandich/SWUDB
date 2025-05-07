# todo list
- ~~sistemare l'intestazione dell'immagine~~
- ~~sistemare icona unica~~
- ~~sistemare webscraping segnalini~~
- ~~sistemare l'accesso a mazzi~~
- ~~sistemare i popup~~
- ~~sistemare webscraping descrizione leader~~
- ~~sistemare visualizzazione descrizione leader~~
- ~~sistemare la posizion del footer~~
- ~~sistemare la posizione dei messaggi di errore~~
- ~~scrivere i termini di servizio~~
- ~~scrivere le traduzioni~~
- ~~sistemare bordi aggiunta/rimozione carte~~
- sistemare la barra di ricerca nella navbar


# sistemare la barra di ricarca

Per spostare la barra di ricerca in modo che sia visibile accanto al menu anche su dispositivi mobili, puoi riorganizzare il layout della navbar e utilizzare le classi di Bootstrap per gestire il posizionamento. Ecco come fare:

### Modifica il codice della navbar

Sposta il form della barra di ricerca fuori dalla sezione del menu a tendina e posizionalo accanto al menu principale. Puoi utilizzare le classi di Bootstrap come `d-none`, `d-md-block`, e `ms-auto` per gestire la visibilità e il posizionamento.

Ecco il codice aggiornato:

```php
<div class="collapse navbar-collapse" id="navbarSupportedContent">
    <!-- Left Side Of Navbar -->
    <ul class="navbar-nav me-auto">
        <li class="nav-item">
            <a class="nav-link" href="{{ route('mazzi') }}">{{ __('custom.mazzi') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="{{ route('carte') }}">{{ __('custom.carte') }}</a>
        </li>
    </ul>

    <!-- Search Bar -->
    <form action="{{ route('carte') }}" class="d-flex ms-auto me-3">
        <input class="form-control" type="text" placeholder="{{ __('custom.searchCard') }}" name="nome" id="nome">
    </form>

    <!-- Right Side Of Navbar -->
    <ul class="navbar-nav ms-auto">
        <!-- Authentication Links -->
        @guest
            @if (Route::has('login'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('login') }}">{{ __('custom.Login') }}</a>
                </li>
            @endif

            @if (Route::has('register'))
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('register') }}">{{ __('custom.Register') }}</a>
                </li>
            @endif
        @else
        <li class="nav-item dropdown">
            <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                {{ Auth::user()->name }}
            </a>

            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                <a class="dropdown-item" href="{{ route('dashboard') }}">
                    {{ __('custom.Dashboard') }}
                </a>

                <a class="dropdown-item" href="{{ route('logout') }}"
                   onclick="event.preventDefault();
                                 document.getElementById('logout-form').submit();">
                    {{ __('custom.Logout') }}
                </a>

                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
            </div>
        </li>
        @endguest
    </ul>
</div>
```

---

### Cosa è cambiato?

1. **Barra di ricerca spostata:**
   - Il form della barra di ricerca è stato spostato fuori dalla lista di navigazione principale e posizionato accanto al menu con `ms-auto` (margine sinistro automatico) per allinearlo a destra.

2. **Visibilità su dispositivi mobili:**
   - La barra di ricerca non è più inclusa nel menu a tendina e sarà visibile accanto al menu anche su schermi piccoli.

---

### Test del layout

1. **Controlla su desktop:**
   - La barra di ricerca dovrebbe essere visibile accanto al menu principale.

2. **Controlla su dispositivi mobili:**
   - Apri il sito su uno schermo piccolo o utilizza gli strumenti di sviluppo del browser per simulare un dispositivo mobile. La barra di ricerca dovrebbe essere visibile accanto al menu.

Se necessario, puoi aggiungere ulteriori classi CSS per personalizzare ulteriormente il layout.

Similar code found with 3 license types