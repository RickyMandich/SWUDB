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
            'regularUsers' => $users->where('admin', false)->count(),
            'verifiedUsers' => $users->filter(function($user) { return $user->isEmailVerified(); })->count(),
            'unverifiedUsers' => $users->filter(function($user) { return !$user->isEmailVerified(); })->count()
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

    /**
     * Toggle email verification status for a specific user
     * Attiva/disattiva lo stato di verifica email per un utente specifico
     *
     * @param int $id User ID
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function toggleEmailVerification($id)
    {
        if (!Auth::admin()) {
            return redirect()->route('users.index')->with('error', 'Non hai i permessi per eseguire questa azione');
        }

        $user = User::findOrFail($id);

        if ($user->isEmailVerified()) {
            // Remove verification
            $user->email_verified_at = null;
            $user->save();
            $status = 'rimossa la verifica email';
        } else {
            // Mark as verified
            $user->markEmailAsVerified();
            $status = 'verificata l\'email';
        }

        return redirect()->route('users.index')->with('success', "Per l'utente {$user->name} è stata {$status}");
    }

    /**
     * Resend verification email for a specific user
     * Reinvia email di verifica per un utente specifico
     *
     * @param int $id User ID
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function resendVerificationEmail($id)
    {
        if (!Auth::admin()) {
            return redirect()->route('users.index')->with('error', 'Non hai i permessi per eseguire questa azione');
        }

        $user = User::findOrFail($id);

        if ($user->isEmailVerified()) {
            return redirect()->route('users.index')->with('warning', "L'utente {$user->name} ha già l'email verificata");
        }

        $emailVerificationController = new \App\Http\Controllers\EmailVerificationController();
        $emailVerificationController->sendVerificationEmail($user);

        return redirect()->route('users.index')->with('success', "Email di verifica inviata nuovamente a {$user->name}");
    }



    /**
     * Update the user's own profile information
     * Aggiorna le informazioni del profilo dell'utente corrente
     *
     * @param Request $request HTTP request containing user data
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

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

        return redirect()->route('dashboard')->with('success', 'Profilo aggiornato con successo');
    }

    /**
     * Resend verification email for the current user
     * Reinvia email di verifica per l'utente corrente
     *
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error messages
     */
    public function resendOwnVerificationEmail()
    {
        $user = Auth::user();

        if ($user->isEmailVerified()) {
            return redirect()->route('dashboard')->with('info', 'La tua email è già verificata');
        }

        // Generate new verification token and send email
        $emailVerificationController = new \App\Http\Controllers\EmailVerificationController();
        $emailVerificationController->sendVerificationEmail($user);

        return redirect()->route('dashboard')->with('success', 'Email di verifica inviata con successo');
    }

    /**
     * Delete the user's own account
     * Elimina l'account dell'utente corrente
     *
     * @param Request $request HTTP request
     * @return \Illuminate\Http\RedirectResponse Redirect to home page
     */
    public function deleteOwnAccount(Request $request)
    {
        $user = Auth::user();

        // Validate password for security
        $request->validate([
            'password' => 'required',
        ]);

        if (!Hash::check($request->password, $user->password)) {
            return redirect()->route('dashboard')->with('error', 'Password non corretta');
        }

        // Prevent admin self-deletion if they are the only admin
        if ($user->admin) {
            $adminCount = User::where('admin', true)->count();
            if ($adminCount <= 1) {
                return redirect()->route('dashboard')->with('error', 'Non puoi eliminare il tuo account: sei l\'unico amministratore del sistema');
            }
        }

        $userName = $user->name;

        // Logout and delete
        Auth::logout();
        $user->delete();

        return redirect()->route('index')->with('success', "Account {$userName} eliminato con successo");
    }
}
