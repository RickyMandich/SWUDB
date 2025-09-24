<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\EmailVerificationMail;
use App\Events\MessageCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Controller for handling email verification
 * Controller per gestire la verifica email
 *
 * Handles email verification token generation, sending verification emails,
 * and processing verification links.
 */
class EmailVerificationController extends Controller
{
    /**
     * Verify email using token from GET parameter
     * Verifica email usando token dal parametro GET
     *
     * @param Request $request HTTP request with token parameter
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error message
     */
    public function verify(Request $request)
    {
        $token = $request->get('token');
        
        if (!$token) {
            return redirect()->route('login')->with('error', 'Token di verifica mancante.');
        }

        $user = User::where('email_verification_token', $token)->first();
        
        if (!$user) {
            return redirect()->route('login')->with('error', 'Token di verifica non valido o scaduto.');
        }

        if ($user->isEmailVerified()) {
            return redirect()->route('login')->with('info', 'Email già verificata. Puoi effettuare il login.');
        }

        // Mark email as verified
        $user->markEmailAsVerified();
        
        MessageCreated::dispatch("Email verificata per utente: " . $user->email);
        
        return redirect()->route('login')->with('success', 'Email verificata con successo! Ora puoi effettuare il login.');
    }

    /**
     * Resend verification email
     * Reinvia email di verifica
     *
     * @param Request $request HTTP request with email
     * @return \Illuminate\Http\RedirectResponse Redirect with success/error message
     */
    public function resend(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();
        
        if ($user->isEmailVerified()) {
            return back()->with('info', 'Email già verificata.');
        }

        // Generate new token and send email
        $this->sendVerificationEmail($user);
        
        return back()->with('success', 'Email di verifica inviata nuovamente.');
    }

    /**
     * Send verification email to user
     * Invia email di verifica all'utente
     *
     * @param User $user The user to send verification email to
     * @return void
     */
    public function sendVerificationEmail(User $user)
    {
        // Generate verification token
        $token = $user->generateEmailVerificationToken();
        
        // Create verification URL
        $verificationUrl = route('email.verify', ['token' => $token]);
        
        // Use email queue service for verification emails
        // Usa il servizio di coda email per le email di verifica
        \App\Services\EmailQueueService::queue(
            new EmailVerificationMail($user, $verificationUrl),
            $user->email,
            'Email verifica utente'
        );
    }

    /**
     * Show resend verification form
     * Mostra form per reinviare verifica
     *
     * @return \Illuminate\View\View
     */
    public function showResendForm()
    {
        return view('auth.verify-email');
    }
}
