<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\AdministratorAnnouncement;
use App\Jobs\SendQueuedEmail;
use App\Http\Controllers\JobController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AdminAnnouncementController extends Controller
{
    /**
     * Show the form for creating a new announcement.
     */
    public function create()
    {
        if (!Auth::user() || !Auth::user()->admin) {
            abort(403);
        }

        $breadcrumbs = [
            ['text' => 'Admin', 'url' => route('admin.dashboard')],
            ['text' => 'Invia Annuncio', 'url' => null],
        ];

        return view('admin.announcement.create', compact('breadcrumbs'));
    }

    /**
     * Send the announcement to all users.
     */
    public function send(Request $request)
    {
        if (!Auth::user() || !Auth::user()->admin) {
            abort(403);
        }

        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $subject = $request->subject;
        $messageBody = $request->message;

        $users = User::all();
        
        // Dispatch job email per ogni utente
        foreach ($users as $user) {
            SendQueuedEmail::dispatch(
                new AdministratorAnnouncement($subject, $messageBody), 
                $user->email, 
                'Annuncio Amministratore'
            );
        }

        // Avvia il processore in background (fire-and-forget)
        JobController::fireAndForgetGet(route('job.processEmailQueue'), [
            'token' => env('JOB_TOKEN')
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'Annuncio messo in coda per l\'invio a ' . $users->count() . ' utenti. Il processo è attivo in background.');
    }
}
