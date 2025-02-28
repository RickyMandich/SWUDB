# Guida alla Migrazione da PHP Tradizionale a Laravel su Altervista
## Creata dal team di esperti Laravel con 25 anni di esperienza

## Indice
1. [Valutazione Preliminare](#valutazione-preliminare)
2. [Preparazione dell'Ambiente di Sviluppo](#preparazione-dellambiente-di-sviluppo)
3. [Creazione del Progetto Laravel](#creazione-del-progetto-laravel)
4. [Migrazione del Database](#migrazione-del-database)
5. [Conversione della Logica di Business](#conversione-della-logica-di-business)
6. [Implementazione delle Viste](#implementazione-delle-viste)
7. [Configurazione di Laravel per Altervista](#configurazione-di-laravel-per-altervista)
8. [Deployment su Altervista](#deployment-su-altervista)
9. [Reindirizzamenti e Gestione URL](#reindirizzamenti-e-gestione-url)
10. [Ottimizzazione e Manutenzione](#ottimizzazione-e-manutenzione)

## Valutazione Preliminare

**Dr. Marco Rossi, Architetto di Sistemi Web**

Prima di iniziare la migrazione, è fondamentale analizzare il sito PHP esistente per identificare:

- **Struttura del database**: Tabelle, relazioni, indici
- **Funzionalità chiave**: Moduli, logica di business, integrazioni esterne
- **Contenuti statici e dinamici**: Pagine, risorse multimediali, generazione dinamica
- **Flussi utente**: Registrazione, login, navigazione principale
- **Dipendenze esterne**: Librerie, API, servizi esterni

Creare un documento dettagliato che mappi questi elementi per pianificare la migrazione in modo metodico.

### Lista di controllo preliminare:
- [ ] Database schema documentato
- [ ] Funzionalità principali identificate
- [ ] Dipendenze esterne catalogate
- [ ] Requisiti di hosting Altervista verificati (supporto Laravel)
- [ ] Backup completo del sito esistente
- [ ] Analisi del traffico per pianificare la migrazione con minimo impatto

## Preparazione dell'Ambiente di Sviluppo

**Ing. Sofia Bianchi, DevOps Specialist**

Per iniziare a lavorare con Laravel, configura un ambiente di sviluppo locale:

1. **Installa i prerequisiti**:
   ```bash
   # Installa PHP 8.x (o versione supportata da Altervista)
   # Installa Composer globalmente
   curl -sS https://getcomposer.org/installer | php
   sudo mv composer.phar /usr/local/bin/composer
   
   # Installa Node.js e NPM per la gestione asset
   ```

2. **Configura Git per il controllo versione**:
   ```bash
   git init
   git config --global user.name "Il tuo nome"
   git config --global user.email "tua@email.com"
   ```

3. **Crea una struttura di branch appropriata**:
   ```bash
   git branch develop
   git checkout develop
   ```

4. **Imposta un database locale** che rispecchi l'ambiente di produzione su Altervista, preferibilmente importando un dump del database esistente.

5. **Configura un server di sviluppo locale** usando Laravel Valet (Mac), Laravel Homestead (multipiattaforma) o XAMPP/WAMP/MAMP.

## Creazione del Progetto Laravel

**Prof. Luca Verdi, Laravel Lead Developer**

Ora creiamo la base della nuova applicazione Laravel:

1. **Inizializza un nuovo progetto Laravel**:
   ```bash
   composer create-project laravel/laravel swudb-laravel
   cd swudb-laravel
   ```

2. **Configura il file `.env`** con le credenziali del database locale:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nome_database_locale
   DB_USERNAME=username_locale
   DB_PASSWORD=password_locale
   ```

3. **Installa pacchetti utili** per la migrazione:
   ```bash
   composer require laravel/ui          # Per autenticazione
   composer require intervention/image  # Per la manipolazione immagini
   composer require spatie/laravel-permission  # Per gestione ruoli/permessi
   ```

4. **Genera lo scaffolding dell'autenticazione** (se necessario):
   ```bash
   php artisan ui bootstrap --auth  # O vue/react in base alle tue preferenze
   npm install && npm run dev
   ```

5. **Imposta la struttura base** del progetto:
   ```bash
   # Crea eventuali namespace personalizzati
   php artisan make:provider CustomServiceProvider
   ```

## Migrazione del Database

**Dott.ssa Elena Neri, Database Specialist**

La migrazione del database richiede attenzione particolare:

1. **Crea le migrazioni Laravel** per replicare lo schema esistente:
   ```bash
   # Per ogni tabella nel database originale
   php artisan make:migration create_tablename_table
   ```

2. **Esempio di migrazione per una tabella utenti**:
   ```php
   Schema::create('users', function (Blueprint $table) {
       $table->id();
       $table->string('name');
       $table->string('email')->unique();
       $table->timestamp('email_verified_at')->nullable();
       $table->string('password');
       // Altri campi da migrare dal database esistente
       $table->rememberToken();
       $table->timestamps();
   });
   ```

3. **Crea i modelli Eloquent** per mappare le tabelle:
   ```bash
   php artisan make:model NomeModello
   ```

4. **Definisci relazioni tra modelli** in base allo schema DB esistente:
   ```php
   // App\Models\User.php
   public function posts() {
       return $this->hasMany(Post::class);
   }
   
   // App\Models\Post.php
   public function user() {
       return $this->belongsTo(User::class);
   }
   ```

5. **Migra i dati esistenti** usando un processo in due fasi:
   - Crea script di esportazione dal vecchio database
   - Importa i dati nel nuovo schema usando seeders:
   ```bash
   php artisan make:seeder UsersTableSeeder
   ```

   ```php
   // Esempio di seeder
   public function run() {
       $users = [
           // Dati importati dal vecchio database
       ];
       
       foreach ($users as $user) {
           User::create([
               'name' => $user['name'],
               'email' => $user['email'],
               'password' => Hash::make($user['password']),
           ]);
       }
   }
   ```

## Conversione della Logica di Business

**Ing. Alessandro Gialli, Software Architect**

La conversione della logica di business richiede un approccio strutturato:

1. **Identifica i principali flussi di dati** nel codice PHP esistente

2. **Crea Controllers Laravel** per gestire le funzionalità:
   ```bash
   php artisan make:controller NomeController --resource
   ```

3. **Implementa la logica business** nei controllers o meglio ancora in classi Service dedicate:
   ```php
   namespace App\Services;
   
   class UserService {
       public function registerUser(array $data) {
           // Logica di registrazione
       }
       
       // Altri metodi di business
   }
   ```

4. **Registra i servizi** nel container di Laravel:
   ```php
   // In un ServiceProvider
   $this->app->singleton(UserService::class, function ($app) {
       return new UserService();
   });
   ```

5. **Crea Request classe per validazione**:
   ```bash
   php artisan make:request StoreUserRequest
   ```

   ```php
   // Definisci regole di validazione
   public function rules() {
       return [
           'name' => 'required|string|max:255',
           'email' => 'required|email|unique:users,email',
           'password' => 'required|min:8|confirmed',
       ];
   }
   ```

6. **Utilizza API Resources** per formattare le risposte:
   ```bash
   php artisan make:resource UserResource
   ```

7. **Implementa middleware personalizzati** se necessario:
   ```bash
   php artisan make:middleware CheckUserRole
   ```

## Implementazione delle Viste

**Dott.ssa Laura Blu, UX/UI Specialist**

La migrazione delle viste al sistema Blade di Laravel:

1. **Crea layout base** in `resources/views/layouts`:
   ```php
   <!-- resources/views/layouts/app.blade.php -->
   <!DOCTYPE html>
   <html>
   <head>
       <title>@yield('title')</title>
       @vite(['resources/css/app.css', 'resources/js/app.js'])
       @stack('styles')
   </head>
   <body>
       @include('partials.nav')
       
       <div class="container">
           @yield('content')
       </div>
       
       @include('partials.footer')
       @stack('scripts')
   </body>
   </html>
   ```

2. **Estrai componenti riutilizzabili** in partials:
   ```php
   <!-- resources/views/partials/nav.blade.php -->
   <nav>
       <!-- Navigazione -->
   </nav>
   ```

3. **Crea le viste specifiche** per ogni pagina:
   ```php
   <!-- resources/views/pages/home.blade.php -->
   @extends('layouts.app')
   
   @section('title', 'Home Page')
   
   @section('content')
       <h1>Benvenuto nel nuovo sito</h1>
       <!-- Contenuto della pagina -->
   @endsection
   ```

4. **Utilizza componenti Blade** per elementi ripetitivi:
   ```php
   // Crea componente
   php artisan make:component Button
   
   // Utilizzo in vista
   <x-button type="submit" class="primary">Invia</x-button>
   ```

5. **Gestisci gli asset statici**:
   - Usa Vite (o Laravel Mix nelle versioni meno recenti) per compilare CSS/JS
   - Copia le immagini in `public/images/`
   - Gestisci i file upload in `storage/app/public/` e crea symbolic link:
   ```bash
   php artisan storage:link
   ```

## Configurazione di Laravel per Altervista

**Dr. Marco Rossi, Architetto di Sistemi Web**

Configurare Laravel per funzionare correttamente su Altervista:

1. **Configura il file `.htaccess`** nella radice per reindirizzare al public folder:
   ```apache
   <IfModule mod_rewrite.c>
       RewriteEngine On
       RewriteRule ^(.*)$ public/$1 [L]
   </IfModule>
   ```

2. **Modifica il file `index.php`** nella root per puntare alle directory corrette:
   ```php
   // Copia index.php dalla cartella public alla root e modifica i percorsi
   require __DIR__.'/vendor/autoload.php';
   $app = require_once __DIR__.'/bootstrap/app.php';
   ```

3. **Configura il file `.env` per produzione**:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=http://swudb.altervista.org
   
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=swudb  # Il tuo db Altervista
   DB_USERNAME=swudb  # Il tuo username Altervista
   DB_PASSWORD=********
   ```

4. **Imposta le directory con permessi corretti**:
   ```bash
   chmod -R 755 storage bootstrap/cache
   ```

5. **Considera limitazioni specifiche di Altervista**:
   - Timeout di esecuzione script
   - Limiti di memoria PHP
   - Requisiti specifici del server che potrebbero richiedere modifiche

## Deployment su Altervista

**Ing. Sofia Bianchi, DevOps Specialist**

Processo di deployment su Altervista:

1. **Prepara l'applicazione per la produzione**:
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   npm run build  # O npm run production per versioni che usano Mix
   ```

2. **Carica i file su Altervista** utilizzando FTP o SSH:
   - Utilizza un client FTP come FileZilla
   - Mantieni la struttura delle directory originale
   - Assicurati di caricare anche i file nascosti come `.htaccess`

3. **Struttura di caricamento consigliata**:
   ```
   /
   ├── app/
   ├── bootstrap/
   ├── config/
   ├── database/
   ├── public/  # Contenuto da renderle accessibile via web
   ├── resources/
   ├── routes/
   ├── storage/
   ├── vendor/
   ├── .env
   ├── .htaccess  # Redirect a public
   ├── index.php  # Modificato per indirizzare correttamente
   └── composer.json
   ```

4. **Esegui le migrazioni del database** tramite phpMyAdmin di Altervista o comandi artisan se supportati:
   ```bash
   php artisan migrate
   php artisan db:seed  # Se necessario
   ```

5. **Verifica il funzionamento** dell'applicazione:
   - Controlla gli URL principali
   - Testa funzionalità di login/registrazione
   - Verifica che form e funzionalità CRUD funzionino

## Reindirizzamenti e Gestione URL

**Prof. Luca Verdi, Laravel Lead Developer**

Gestione della transizione tra vecchi e nuovi URL:

1. **Crea reindirizzamenti** per mantenere la compatibilità con i vecchi URL:
   ```php
   // routes/web.php
   Route::get('vecchio-percorso', function () {
       return redirect('nuovo-percorso');
   });
   ```

2. **Implementa reindirizzamenti globali** con middleware:
   ```php
   namespace App\Http\Middleware;
   
   class RedirectLegacyUrls
   {
       public function handle($request, Closure $next)
       {
           $oldPaths = [
               // Mappa di vecchi percorsi => nuovi percorsi
               'pagina.php' => 'pagina',
               // ...
           ];
   
           $path = $request->path();
           if (isset($oldPaths[$path])) {
               return redirect($oldPaths[$path]);
           }
   
           return $next($request);
       }
   }
   ```

3. **Aggiungi il middleware** al kernel HTTP:
   ```php
   protected $middlewareGroups = [
       'web' => [
           // ...
           \App\Http\Middleware\RedirectLegacyUrls::class,
       ],
   ];
   ```

4. **Utilizza route parameters** per gestire URL dinamici:
   ```php
   // Vecchio: articolo.php?id=123
   // Nuovo: /articoli/123
   
   Route::get('articoli/{id}', [ArticoloController::class, 'show']);
   ```

5. **Utilizza fallback routes** per gestire URL non trovati:
   ```php
   Route::fallback(function () {
       return response()->view('errors.404', [], 404);
   });
   ```

## Ottimizzazione e Manutenzione

**Dott.ssa Elena Neri, Database Specialist**

Ottimizzazioni post-migrazione:

1. **Implementa caching** per migliorare le performance:
   ```php
   // Caching delle query
   $users = Cache::remember('all_users', 3600, function () {
       return User::all();
   });
   
   // Caching delle viste
   php artisan view:cache
   ```

2. **Ottimizza le query del database**:
   - Aggiungi indici appropriati
   - Utilizza eager loading per evitare N+1 query:
   ```php
   $posts = Post::with('user', 'comments')->get();
   ```

3. **Implementa queues** per operazioni pesanti:
   ```php
   php artisan make:job ProcessReport
   
   // Dispatch del job
   ProcessReport::dispatch($reportData);
   ```

4. **Configurazioni di sicurezza**:
   - Verifica che `APP_DEBUG=false` in produzione
   - Implementa CSRF protection su tutti i form
   - Utilizza autenticazione per le rotte sensibili
   - Considera l'utilizzo di un servizio CAPTCHA per i form pubblici

5. **Monitoraggio e logging**:
   - Configura logging avanzato in `config/logging.php`
   - Considera l'implementazione di un servizio di monitoraggio
   - Imposta notifiche per errori critici

6. **Piano di backup**:
   - Backup regolari del database
   - Backup dei file dell'applicazione
   - Test di ripristino periodici

## Conclusione

La migrazione da PHP tradizionale a Laravel su Altervista è un processo complesso ma gratificante che porterà numerosi vantaggi in termini di manutenibilità, scalabilità e velocità di sviluppo futuro. Seguendo questa guida passo-passo, avrai trasformato il tuo sito in un'applicazione moderna e robusta basata su Laravel.

Ricorda che è possibile eseguire la migrazione incrementalmente, partendo dalle funzionalità meno critiche per poi espandere gradualmente fino a coprire l'intero sito.

Per supporto aggiuntivo, la comunità Laravel è molto attiva e offre numerose risorse, forum e documentazione dettagliata.

Buon lavoro con la tua nuova applicazione Laravel!
