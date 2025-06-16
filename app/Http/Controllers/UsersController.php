<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Controller for user management and administration
 * Controller per la gestione utenti e amministrazione
 *
 * This controller provides functionality for admin users to manage
 * all system users, including viewing, editing, and changing admin status.
 */
class UsersController extends Controller
{
    /**
     * Display a listing of all users with admin management capabilities
     * Mostra l'elenco di tutti gli utenti con capacità di gestione admin
     *
     * This method is restricted to admin users only and provides a comprehensive
     * view of all registered users with their details and admin status.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse The users index view or error redirect
     */
    public function index()
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        $users = User::orderBy('created_at', 'desc')->get();
        
        return view('users.index', [
            'users' => $users,
            'totalUsers' => $users->count(),
            'adminUsers' => $users->where('admin', true)->count(),
            'regularUsers' => $users->where('admin', false)->count()
        ]);
    }

    /**
     * Show detailed information for a specific user
     * Mostra informazioni dettagliate per un utente specifico
     *
     * @param int $id User ID
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse The user detail view or error redirect
     */
    public function show($id)
    {
        if (!Auth::admin()) {
            return view("errors.403");
        }

        $user = User::findOrFail($id);
        
        return view('users.show', [
            'user' => $user
        ]);
    }

    /**
     * Toggle admin status for a specific user
     * Attiva/disattiva lo stato admin per un utente specifico
     *
     * This method allows admin users to grant or revoke admin privileges
     * for other users. Includes validation to prevent self-demotion.
     *
     * @param Request $request HTTP request
     * @param int $id User ID
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function toggleAdmin(Request $request, $id)
    {
        if (!Auth::admin()) {
            return redirect()->route('users.index')->with('error', 'Non hai i permessi per eseguire questa azione');
        }

        $user = User::findOrFail($id);
        
        // Previeni auto-rimozione dei privilegi admin
        if ($user->id === Auth::id() && $user->admin) {
            return redirect()->route('users.index')->with('warning', 'Non puoi rimuovere i tuoi stessi privilegi di amministratore');
        }

        $user->admin = !$user->admin;
        $user->save();

        $status = $user->admin ? 'promosso ad amministratore' : 'rimosso dai privilegi di amministratore';
        
        return redirect()->route('users.index')->with('success', "L'utente {$user->name} è stato {$status}");
    }

    /**
     * Update user information
     * Aggiorna le informazioni dell'utente
     *
     * @param Request $request HTTP request containing user data
     * @param int $id User ID
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function update(Request $request, $id)
    {
        if (!Auth::admin()) {
            return redirect()->route('users.index')->with('error', 'Non hai i permessi per eseguire questa azione');
        }

        $user = User::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255|unique:users,name,' . $user->id,
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        
        // Aggiorna la password solo se fornita
        if ($request->filled('password')) {
            $request->validate([
                'password' => 'min:8|confirmed',
            ]);
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('users.show', $user->id)->with('success', 'Informazioni utente aggiornate con successo');
    }

    /**
     * Delete a user account
     * Elimina un account utente
     *
     * @param int $id User ID
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function destroy($id)
    {
        if (!Auth::admin()) {
            return redirect()->route('users.index')->with('error', 'Non hai i permessi per eseguire questa azione');
        }

        $user = User::findOrFail($id);
        
        // Previeni auto-eliminazione
        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')->with('warning', 'Non puoi eliminare il tuo stesso account');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', "L'utente {$userName} è stato eliminato con successo");
    }
}
