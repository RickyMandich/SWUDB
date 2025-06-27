<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\SystemError;

/**
 * Controller for administrative functions
 * Controller per le funzioni amministrative
 *
 * This controller handles admin-only functionality including database queries,
 * documentation pages, and other administrative tools.
 */
class AdminController extends Controller
{
    /**
     * Execute database queries with automatic mergeSort for cards
     * Esegue query del database con mergeSort automatico per le carte
     *
     * This method provides a query interface for admin users with automatic
     * sorting applied to card queries that don't have ORDER BY clauses.
     * Handles both SELECT queries (returns results) and modification queries
     * (INSERT, UPDATE, DELETE - returns affected rows count).
     * Includes proper MySQL error handling.
     *
     * @param Request $request HTTP request containing query parameter
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse Query results view or error redirect
     */
    public function query(Request $request)
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        $get = $request->all();
        $query = $get["query"] ?? "SELECT * FROM cards limit 10";

        $result = null;
        $affectedRows = null;
        $error = null;
        $isSelectQuery = false;
        $sortApplied = false;

        try {
            // Determina se è una query SELECT o di modifica
            $queryLower = strtolower(trim($query));
            $isSelectQuery = strpos($queryLower, 'select') === 0 ||
                           strpos($queryLower, 'show') === 0 ||
                           strpos($queryLower, 'describe') === 0 ||
                           strpos($queryLower, 'desc') === 0 ||
                           strpos($queryLower, 'explain') === 0;

            if ($isSelectQuery) {
                // Query SELECT - restituisce risultati
                $result = DB::select($query);

                // Se la query riguarda la tabella cards e non ha ORDER BY, applica mergeSort
                $isCardsQuery = strpos($queryLower, 'from cards') !== false || strpos($queryLower, 'from `cards`') !== false;
                $hasOrderBy = strpos($queryLower, 'order by') !== false;

                if ($isCardsQuery && !$hasOrderBy && !empty($result)) {
                    // Verifica che i risultati abbiano gli attributi necessari per il mergeSort
                    $requiredAttributes = ['nome', 'tipo', 'aspettoPrimario', 'aspettoSecondario', 'costo', 'uscita', 'numero', 'espansione'];
                    $firstResult = (array) $result[0];
                    $hasRequiredAttributes = true;

                    foreach ($requiredAttributes as $attr) {
                        if (!array_key_exists($attr, $firstResult)) {
                            $hasRequiredAttributes = false;
                            break;
                        }
                    }

                    if ($hasRequiredAttributes) {
                        try {
                            // Converte gli oggetti stdClass in array per il mergeSort
                            $resultArray = array_map(fn($item) => (array) $item, $result);

                            // Applica il mergeSort
                            $sortedResult = CardsController::mergeSort($resultArray);

                            // Converte di nuovo in oggetti stdClass per mantenere la compatibilità con la view
                            $result = array_map(fn($item) => (object) $item, $sortedResult);
                            $sortApplied = true;
                        } catch (\Exception $e) {
                            // Se il mergeSort fallisce, mantieni l'ordine originale
                            $sortApplied = false;
                        }
                    }
                }
            } else {
                // Query di modifica (INSERT, UPDATE, DELETE) - restituisce numero righe modificate
                $affectedRows = DB::affectingStatement($query);
            }

        } catch (\Exception $e) {
            // Gestione errori MySQL
            $error = $e->getMessage();
        }

        return view("query", [
            "result" => $result,
            "affectedRows" => $affectedRows,
            "error" => $error,
            "query" => $query,
            "sorted" => $sortApplied,
            "isSelectQuery" => $isSelectQuery
        ]);
    }

    /**
     * Display Terms of Service page
     * Mostra la pagina Termini di Servizio
     *
     * @return \Illuminate\View\View Terms of Service view
     */
    public function termsOfService()
    {
        return view("docs.termOfService");
    }

    /**
     * Display Privacy Policy page
     * Mostra la pagina Privacy Policy
     *
     * @return \Illuminate\View\View Privacy Policy view
     */
    public function privacyPolicy()
    {
        return view("docs.privacy");
    }

    /**
     * Display Documentation page
     * Mostra la pagina Documentazione
     *
     * @return \Illuminate\View\View Documentation view
     */
    public function documentation()
    {
        return view("documentazione");
    }

    /**
     * Display Advanced Guide page
     * Mostra la pagina Guida Avanzata
     *
     * @return \Illuminate\View\View Advanced guide view
     */
    public function advancedGuide()
    {
        return view("guida-avanzata");
    }

    /**
     * Display admin dashboard with system overview
     * Mostra la dashboard admin con panoramica del sistema
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse Admin dashboard or error redirect
     */
    public function dashboard()
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        // Statistiche del sistema usando modelli Eloquent
        $stats = [
            'total_cards' => \App\Models\Card::count(),
            'total_users' => \App\Models\User::count(),
            'admin_users' => \App\Models\User::where('admin', true)->count(),
            'total_decks' => \App\Models\Deck::where('nome', '!=', 'collezione')->count(),
            'public_decks' => \App\Models\Deck::where('public', true)->where('nome', '!=', 'collezione')->count(),
            'recent_users' => \App\Models\User::where('created_at', '>=', now()->subDays(7))->count(),
            'total_collections' => \App\Models\Deck::where('nome', 'collezione')->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    /**
     * Display system errors management page
     * Mostra la pagina di gestione degli errori di sistema
     *
     * @param Request $request HTTP request with optional filters
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse Errors management view or error redirect
     */
    public function errors(Request $request)
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        $query = SystemError::with(['user', 'resolvedBy'])
            ->orderBy('created_at', 'desc');

        // Apply status filter if provided
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }else{
            $query->where('status', 'new');
        }

        // Apply search filter if provided
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('exception_class', 'like', "%{$search}%")
                  ->orWhere('file', 'like', "%{$search}%");
            });
        }

        $errors = $query->paginate(20);

        // Get statistics for dashboard
        $stats = [
            'total' => SystemError::count(),
            'new' => SystemError::where('status', 'new')->count(),
            'in_progress' => SystemError::where('status', 'in_progress')->count(),
            'resolved' => SystemError::where('status', 'resolved')->count(),
            'ignored' => SystemError::where('status', 'ignored')->count(),
        ];

        return view('admin.errors', compact('errors', 'stats'));
    }

    /**
     * Show detailed view of a specific error
     * Mostra la vista dettagliata di un errore specifico
     *
     * @param SystemError $error The error to display
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse Error detail view or error redirect
     */
    public function showError(SystemError $error)
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        return view('admin.error-detail', compact('error'));
    }

    /**
     * Update error status and notes
     * Aggiorna lo stato e le note dell'errore
     *
     * @param Request $request HTTP request with status and notes
     * @param SystemError $error The error to update
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View Redirect back with success message or error view
     */
    public function updateError(Request $request, SystemError $error)
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        $request->validate([
            'status' => 'required|in:new,in_progress,resolved,ignored',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $admin = Auth::user();
        $status = $request->status;
        $notes = $request->admin_notes;

        switch ($status) {
            case 'resolved':
                $error->markAsResolved($admin, $notes);
                break;
            case 'ignored':
                $error->markAsIgnored($admin, $notes);
                break;
            case 'in_progress':
                $error->markAsInProgress($admin, $notes);
                break;
            default:
                $error->update([
                    'status' => $status,
                    'admin_notes' => $notes,
                ]);
        }

        return redirect()->back()->with('success', 'Stato errore aggiornato con successo.');
    }

    /**
     * Quick action to update error status from email links
     * Azione rapida per aggiornare lo stato dell'errore dai link email
     *
     * @param SystemError $error The error to update
     * @param string $action The action to perform (resolved, ignored)
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Contracts\View\View Redirect to error detail with success message or error view
     */
    public function quickActionError(SystemError $error, string $action)
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        if (!in_array($action, ['resolved', 'ignored'])) {
            return redirect()->route('admin.errors')->with('error', 'Azione non valida.');
        }

        $admin = Auth::user();
        $notes = "Aggiornato tramite azione rapida email";

        switch ($action) {
            case 'resolved':
                $error->markAsResolved($admin, $notes);
                $message = 'Errore segnato come risolto con successo.';
                break;
            case 'ignored':
                $error->markAsIgnored($admin, $notes);
                $message = 'Errore segnato come ignorato con successo.';
                break;
        }

        return redirect()->route('admin.errors.show', $error)->with('success', $message);
    }

    /**
     * Batch action to update multiple errors status
     * Azione batch per aggiornare lo stato di più errori
     *
     * @param Request $request HTTP request with action and error IDs
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Contracts\View\View Redirect back with success message or error view
     */
    public function batchActionErrors(Request $request)
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        $request->validate([
            'action' => 'required|in:resolved,ignored,in_progress',
            'selected_errors' => 'array|min:1',
            'selected_errors.*' => 'exists:system_errors,id',
            'filter_new' => 'nullable|boolean',
        ]);

        $admin = Auth::user();
        $action = $request->action;
        $notes = "Aggiornato tramite azione batch";

        // Handle batch action for all new errors
        if ($request->filter_new) {
            $errors = SystemError::where('status', 'new')->get();
        } else {
            // Handle batch action for selected errors
            $errorIds = $request->selected_errors ?? [];
            if (empty($errorIds)) {
                return redirect()->back()->with('error', 'Nessun errore selezionato.');
            }
            $errors = SystemError::whereIn('id', $errorIds)->get();
        }

        if ($errors->isEmpty()) {
            return redirect()->back()->with('error', 'Nessun errore trovato per l\'azione richiesta.');
        }

        $updatedCount = 0;
        foreach ($errors as $error) {
            switch ($action) {
                case 'resolved':
                    $error->markAsResolved($admin, $notes);
                    break;
                case 'ignored':
                    $error->markAsIgnored($admin, $notes);
                    break;
                case 'in_progress':
                    $error->markAsInProgress($admin, $notes);
                    break;
            }
            $updatedCount++;
        }

        $actionText = match($action) {
            'resolved' => 'risolti',
            'ignored' => 'ignorati',
            'in_progress' => 'in lavorazione',
            default => 'aggiornati'
        };

        return redirect()->back()->with('success', "{$updatedCount} errori segnati come {$actionText} con successo.");
    }
}
