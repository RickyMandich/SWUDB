<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        
        $result = DB::select($query);
        
        // Se la query riguarda la tabella cards e non ha ORDER BY, applica mergeSort
        $queryLower = strtolower(trim($query));
        $isCardsQuery = strpos($queryLower, 'from cards') !== false || strpos($queryLower, 'from `cards`') !== false;
        $hasOrderBy = strpos($queryLower, 'order by') !== false;

        $sortApplied = false;
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
        
        return view("query", [
            "result" => $result,
            "query" => $query,
            "sorted" => $sortApplied
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

        // Statistiche del sistema
        $stats = [
            'total_cards' => DB::table('cards')->count(),
            'total_users' => DB::table('users')->count(),
            'admin_users' => DB::table('users')->where('admin', true)->count(),
            'total_decks' => DB::table('decks')->where('nome', '!=', 'collezione')->count(),
            'public_decks' => DB::table('decks')->where('public', true)->where('nome', '!=', 'collezione')->count(),
            'recent_users' => DB::table('users')->where('created_at', '>=', now()->subDays(7))->count(),
            'total_collections' => DB::table('decks')->where('nome', 'collezione')->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
