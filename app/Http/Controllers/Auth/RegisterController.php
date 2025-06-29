<?php

namespace App\Http\Controllers\Auth;

use App\Events\MessageCreated;

use App\Http\Controllers\JobController;
use App\Http\Controllers\Controller;

use App\Mail\WelcomeEmail;
use App\Http\Controllers\EmailVerificationController;

use App\Models\User;

use Illuminate\Foundation\Auth\RegistersUsers;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
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
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9-_]+$/'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.regex' => 'Il nome può contenere solo lettere, numeri, trattini e underscore.',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data){
        if($data["email"] == "ricky.mandich@gmail.com"){
            MessageCreated::dispatch("Nuovo admin: ".$data["email"]);
            $data["admin"] = 1;
        }else{
            MessageCreated::dispatch("Nuovo user: ".$data["email"]);
            $data["admin"] = 0;
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'admin' => $data['admin'],
        ]);

        // Send email verification instead of welcome email
        $emailVerificationController = new EmailVerificationController();
        $emailVerificationController->sendVerificationEmail($user);

        return $user;
    }

    /**
     * Handle a registration request for the application.
     * Override to prevent automatic login and redirect to verification notice
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function register(\Illuminate\Http\Request $request)
    {
        $this->validator($request->all())->validate();

        $user = $this->create($request->all());

        return redirect()->route('login')->with('success', 'Registrazione completata! Controlla la tua email per verificare l\'account prima di effettuare il login.');
    }
}
