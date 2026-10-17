<?php

namespace App\Http\Controllers;

use App\Enums\DeckFormat;
use App\Services\DeckImporter;
use App\Services\DeckValidation\DeckFormatValidatorFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeckImportController extends Controller
{
    public function create(): View
    {
        return view('decks.import');
    }

    /**
     * Imports a deck from an uploaded TXT/JSON file or from an https URL, as a new deck (version 1) of the logged user
     * Importa un mazzo da un file TXT/JSON caricato o da un URL https, come nuovo mazzo (versione 1) dell'utente loggato
     */
    public function store(Request $request, DeckImporter $importer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('decks')->where(fn ($q) => $q->where('user_id', $request->user()->id)),
            ],
            'format' => ['required', Rule::enum(DeckFormat::class)],
            'is_public' => ['boolean'],
            'file' => ['required_without:url', 'nullable', 'file', 'extensions:txt,json', 'max:2048'], // KB
            'url' => ['required_without:file', 'nullable', 'url:https', 'max:2048'],
        ]);

        $owner = $request->user();
        $format = DeckFormat::from($validated['format']);
        $isPublic = $request->boolean('is_public');

        $result = $request->hasFile('file')
            ? $importer->fromContent($request->file('file')->get(), $owner, $validated['name'], $format, $isPublic)
            : $importer->fromUrl($validated['url'], $owner, $validated['name'], $format, $isPublic);

        if ($result['deck'] === null) {
            return back()->withInput()
                ->withErrors(['import' => $result['error'] ?? 'Import non riuscito.'])
                ->with('import-warnings', $result['notFound']);
        }

        $warnings = $result['notFound'];
        if ($result['sideboardIgnored'] > 0) {
            $warnings[] = "Sideboard non supportata: {$result['sideboardIgnored']} carte ignorate.";
        }

        // Informativa, come dopo ogni salvataggio: mostra subito cosa manca per essere legale nel formato scelto
        $errors = DeckFormatValidatorFactory::make($format)->validate($result['deck']->fresh());

        return redirect()->route('decks.edit', [$owner->name, $result['deck']->name])
            ->with('status', 'Mazzo importato.')
            ->with('deck-errors', $errors)
            ->with('import-warnings', $warnings);
    }
}
