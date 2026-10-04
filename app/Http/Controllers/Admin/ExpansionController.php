<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expansion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpansionController extends Controller
{
    /**
     * Lists every expansion, tokens included (T* codes are separate rows on purpose)
     * Elenca tutte le espansioni, token inclusi (i codici T* sono righe a sé, volutamente)
     */
    public function index(Request $request): View
    {
        $filter = (bool) $request->input('filter', true);

        if ($filter) {
            $expansions = Expansion::where('confirmed', false)->orderByDesc('rotation')->orderByDesc('legal_date')->get();
        } else {
            $expansions = Expansion::orderByDesc('rotation')->orderByDesc('legal_date')->get();
        }

        return view('admin.expansions.index', compact('expansions', 'filter'));
    }

    /**
     * Updates the manually curated fields of one expansion
     * Aggiorna i campi curati a mano di una singola espansione
     */
    public function update(Request $request, Expansion $expansion): RedirectResponse
    {
        $validated = $request->validate([
            'legal_date' => ['nullable', 'date'],
            'rotation' => ['required', 'string', 'max:1'],
            'group_main_expansion' => ['nullable', 'string', 'exists:expansions,expansion'],
        ]);
        $validated['confirmed'] = $request->boolean('confirmed'); // una checkbox non spuntata non arriva nel payload

        $expansion->update($validated);

        return back()
            ->with('status', "Espansione {$expansion->expansion} aggiornata.")
            ->with('status_level', 'success');
    }
}
