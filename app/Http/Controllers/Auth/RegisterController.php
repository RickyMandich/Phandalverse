<?php

namespace App\Http\Controllers\Auth;

use App\Auth\CustomUserProvider;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
    protected $redirectTo = '/home';

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        $data['admin'] = 0;
        if($data['email'] == 'ricky.mandich@gmail.com'){
            $data['admin'] = 1;
        }

        // Crea l'utente con una password temporanea
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make('temp_' . $data['password']), // temporaneo
            'admin' => $data['admin'],
        ]);

        // Genera la password con il nuovo formato e aggiungi il prefisso
        $personalSalt = env('APP_KEY', '42') . "#$user->id";
        $newHash = Hash::make($data['password'] . $personalSalt);
        $finalPassword = CustomUserProvider::NEW_HASH_PREFIX . $newHash;
        
        $user->password = $finalPassword;
        
        Log::info("Nuova registrazione: {$user->email}");
        Log::info("Password formato: " . substr($finalPassword, 0, 30) . "...");
        
        $user->save();

        // Invia notifica Telegram per nuova registrazione
        try {
            if (env('TELEGRAM_BOT_TOKEN')) {
                \App\Services\TelegramService::notify(
                    'Nuovo utente registrato',
                    "Nome: {$user->name}\nEmail: {$user->email}"
                );
            }
        } catch (\Exception $ex) {
            Log::error('Impossibile inviare notifica Telegram per registrazione: ' . $ex->getMessage());
        }

        return $user;
    }
}
