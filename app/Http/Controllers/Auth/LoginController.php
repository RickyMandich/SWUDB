<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your dashboard screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Attempt to log the user into the application.
     * Override to check email verification
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function attemptLogin(Request $request)
    {
        $credentials = $this->credentials($request);

        // First check if credentials are valid
        if (Auth::validate($credentials)) {
            $user = User::where('email', $credentials['email'])->first();

            // Check if email is verified
            if (!$user->isEmailVerified()) {
                return false;
            }
        }

        return $this->guard()->attempt(
            $credentials, $request->boolean('remember')
        );
    }

    /**
     * Get the failed login response instance.
     * Override to handle unverified email
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        $credentials = $this->credentials($request);

        // Check if user exists and email is not verified
        if (Auth::validate($credentials)) {
            $user = User::where('email', $credentials['email'])->first();

            if (!$user->isEmailVerified()) {
                return redirect()->route('email.resend.form')
                    ->withInput($request->only('email'))
                    ->with('error', 'Devi verificare la tua email prima di effettuare il login.')
                    ->with('email', $credentials['email']);
            }
        }

        return redirect()->back()
            ->withInput($request->only($this->username(), 'remember'))
            ->withErrors([
                $this->username() => trans('auth.failed'),
            ]);
    }
}
