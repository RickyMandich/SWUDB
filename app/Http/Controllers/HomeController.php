<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Controller for handling authenticated user dashboard and home functionality
 * Controller per gestire la dashboard e funzionalità home per utenti autenticati
 *
 * This controller provides the main dashboard interface for logged-in users,
 * requiring authentication for all actions.
 */
class HomeController extends Controller
{
    /**
     * Create a new controller instance with authentication middleware
     * Crea una nuova istanza del controller con middleware di autenticazione
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard for authenticated users
     * Mostra la dashboard dell'applicazione per utenti autenticati
     *
     * @return \Illuminate\Contracts\Support\Renderable The dashboard view
     */
    public function index()
    {
        return view('users.dashboard');
    }
}
