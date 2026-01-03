<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class CustomUserProvider extends EloquentUserProvider
{
    const NEW_HASH_PREFIX = '§'; // Carattere speciale per identificare il nuovo formato
    
    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $plain = $credentials['password'];
        $storedPassword = $user->getAuthPassword();
        
        // Controlla se è il nuovo formato (inizia con §)
        if (str_starts_with($storedPassword, self::NEW_HASH_PREFIX)) {
            // Rimuovi il prefisso e valida con la nuova logica
            $actualHash = substr($storedPassword, 1);
            $customPassword = $plain . env('APP_KEY', '42') . "#{$user->getAuthIdentifier()}";
            
            return Hash::check($customPassword, $actualHash);
        }
        
        // Vecchio formato: valida normalmente
        if (Hash::check($plain, $storedPassword)) {
            // Password corretta! Aggiorna al nuovo formato
            $this->upgradePassword($user, $plain);
            return true;
        }
        
        return false;
    }
    
    /**
     * Aggiorna la password al nuovo formato
     */
    protected function upgradePassword(Authenticatable $user, string $plainPassword)
    {
        $newHash = Hash::make($plainPassword . env('APP_KEY', '42') . "#{$user->getAuthIdentifier()}");
        
        // Aggiungi il prefisso per identificare il nuovo formato
        $user->password = self::NEW_HASH_PREFIX . $newHash;
        $user->save();
    }
}