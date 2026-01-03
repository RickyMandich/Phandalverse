<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class CustomUserProvider extends EloquentUserProvider
{
    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $plain = $credentials['password'];
        
        // Applica la tua logica custom per generare la password
        $customPassword = $plain . env('APP_KEY', '42') . "#{$user->getAuthIdentifier()}";
        
        return Hash::check($customPassword, $user->getAuthPassword());
    }
}