<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemErrorController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status'); // 'open' | 'resolved' | 'ignored' | null (tutti)

        $errors = SystemError::when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(30)
            ->withQueryString(); // mantiene il filtro ?status= nei link di paginazione

        return view('admin.errors.index', compact('errors', 'status'));
    }

    public function show(SystemError $systemError): View
    {
        return view('admin.errors.show', compact('systemError'));
    }

    /**
     * Updates the status of a single error (resolved/ignored) from a row-level button
     * Aggiorna lo stato di un singolo errore (risolto/ignorato) da un pulsante riga-per-riga
     */
    public function update(Request $request, SystemError $systemError): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:open,resolved,ignored']]);

        $systemError->update([
            'status' => $validated['status'],
            'resolved_at' => $validated['status'] === SystemError::STATUS_RESOLVED ? now() : null,
        ]);

        return back()->with('status', 'Errore aggiornato.');
    }

    /**
     * Bulk action: applies the same status to every selected error id at once
     * Azione bulk: applica lo stesso stato a tutti gli id di errore selezionati insieme
     */
    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:system_errors,id'],
            'status' => ['required', 'in:open,resolved,ignored'],
        ]);

        SystemError::whereIn('id', $validated['ids'])->update([
            'status' => $validated['status'],
            'resolved_at' => $validated['status'] === 'open' ? null : now(),
        ]);

        return back()->with('status', count($validated['ids']).' errori aggiornati.');
    }
}