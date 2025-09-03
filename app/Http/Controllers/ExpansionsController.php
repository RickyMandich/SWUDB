<?php

namespace App\Http\Controllers;

use App\Models\Expansion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpansionsController extends Controller
{
    /**
     * Display expansions management page
     * Mostra la pagina di gestione espansioni
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse Expansions management view or error redirect
     */
    public function index(Request $request)
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        if($request->input('filtra') === "on"){
            $expansions = Expansion::where("confermato", false)->orderBy('uscita')->get();
        }else{
            $expansions = Expansion::orderBy('uscita')->get();
        }

        return view('admin.expansions', compact('expansions'));
    }

    /**
     * Update expansion data
     * Aggiorna i dati dell'espansione
     *
     * @param Request $request HTTP request with expansion data
     * @return \Illuminate\Http\RedirectResponse Redirect back with success/error message
     */
    public function update(Request $request)
    {
        if (!Auth::admin()) {
            return redirect()->back()->with('error', 'Accesso negato');
        }

        $request->validate([
            'espansione' => 'required|string|max:10',
            'uscita' => 'required|string|max:65',
            'rotazione' => 'required|string|size:1|regex:/^[0A-Z]$/'
        ]);

        try {
            $expansion = Expansion::where('espansione', $request->espansione)->first();

            if (!$expansion) {
                return redirect()->back()->with('error', 'Espansione non trovata');
            }

            // Aggiornamento con query builder diretto
            $result = DB::table('expansions')
                ->where('espansione', $request->espansione)
                ->update([
                    'uscita' => $request->uscita,
                    'rotazione' => $request->rotazione,
                    'confermato' => $request->has('confermato') ? false : true
                ]);

            if ($result) {
                return redirect()->back()->with('success', "Espansione {$request->espansione} aggiornata con successo");
            } else {
                return redirect()->back()->with('error', 'Nessuna riga aggiornata');
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore durante l\'aggiornamento: ' . $e->getMessage());
        }
    }
}
