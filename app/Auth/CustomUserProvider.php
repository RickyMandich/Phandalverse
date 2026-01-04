<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CustomUserProvider extends EloquentUserProvider
{
    const NEW_HASH_PREFIX = 'V2:'; // Prefisso chiaro e sicuro
    
    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $plain = $credentials['password'];
        $storedPassword = $user->getAuthPassword();
        
        Log::info("=== LOGIN ATTEMPT User ID: {$user->getAuthIdentifier()} ===");
        Log::info("Password stored (first 20 chars): " . substr($storedPassword, 0, 20));
        
        // Controlla se è il nuovo formato (inizia con V2:)
        if (str_starts_with($storedPassword, self::NEW_HASH_PREFIX)) {
            Log::info("✓ Rilevato NUOVO formato password");
            
            // Rimuovi il prefisso correttamente
            $actualHash = substr($storedPassword, strlen(self::NEW_HASH_PREFIX));
            $customPassword = $plain . env('APP_KEY', '42') . "#{$user->getAuthIdentifier()}";
            
            Log::info("Hash estratto (first 20 chars): " . substr($actualHash, 0, 20));
            
            $isValid = Hash::check($customPassword, $actualHash);
            Log::info("Risultato validazione: " . ($isValid ? 'SUCCESS ✓' : 'FAILED ✗'));
            
            return $isValid;
        }
        
        Log::info("⚠ Rilevato VECCHIO formato password");
        
        // Vecchio formato: valida normalmente
        if (Hash::check($plain, $storedPassword)) {
            Log::info("✓ Password vecchio formato corretta! Upgrading...");
            $this->upgradePassword($user, $plain);
            return true;
        }
        
        // Fallback: password già aggiornata ma senza prefisso (caso edge)
        if (Hash::check($plain . env('APP_KEY', '42') . "#{$user->getAuthIdentifier()}", $storedPassword)) {
            Log::info("✓ Password nuovo formato SENZA prefisso! Aggiungendo prefisso...");
            $this->upgradePassword($user, $plain);
            return true;
        }
        
        Log::info("✗ Password non valida");
        return false;
    }
    
    /**
     * Aggiorna la password al nuovo formato
     */
    protected function upgradePassword(Authenticatable $user, string $plainPassword)
    {
        Log::info(">>> Inizio upgrade password User ID: {$user->getAuthIdentifier()}");
        
        $personalSalt = env('APP_KEY', '42') . "#{$user->getAuthIdentifier()}";
        $newHash = Hash::make($plainPassword . $personalSalt);
        $finalPassword = self::NEW_HASH_PREFIX . $newHash;
        
        Log::info("Nuovo hash generato (first 30 chars): " . substr($finalPassword, 0, 30));
        
        $user->password = $finalPassword;
        $user->save();
        
        Log::info("<<< Password upgrade completato con successo!");
    }
}