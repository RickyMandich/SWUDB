<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    /**
     * Lists every user with their assigned roles, for the admin overview table
     * Elenca tutti gli utenti con i loro ruoli assegnati, per la tabella di riepilogo admin
     * @return View
     */
    public function index(): View
    {
        $users = User::with('roles')->orderBy('name')->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    /**
     * Shows the edit form for a single user: every role/permission plus which ones are currently assigned
     * Mostra il form di modifica di un utente: tutti i ruoli/permessi disponibili e quali sono già assegnati
     */
    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'permissions'));
    }

    /**
    * Overwrites the user's roles and direct permissions with whatever was checked in the form
    * Sovrascrive ruoli e permessi diretti dell'utente con quanto selezionato nel form
    */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->syncRoles($validated['roles'] ?? []);
        $user->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('admin.users.index')->with('status', 'Utente aggiornato.');
    }
}