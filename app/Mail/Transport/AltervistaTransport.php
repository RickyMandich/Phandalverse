<?php

namespace App\Mail\Transport;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * Custom Mailer Transport per Altervista.
 * Utilizza la funzione nativa mail() di PHP per aggirare le restrizioni degli hosting condivisi
 * e rispettare i limiti di invio di Altervista.
 */
class AltervistaTransport extends AbstractTransport
{
    /**
     * Esegue l'invio fisico del messaggio tramite mail().
     */
    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        
        // Recupero destinatari
        $to = implode(', ', array_map(fn($recipient) => $recipient->getAddress(), $email->getTo()));
        
        // Oggetto
        $subject = $email->getSubject();
        
        // Corpo del messaggio (preferenza HTML)
        $body = $email->getHtmlBody();
        $isHtml = true;
        
        if (!$body) {
            $body = $email->getTextBody();
            $isHtml = false;
        }
        
        // Gestione Mittente (From) - Obbligatorio per Altervista per non apparire come "Apache"
        $from = $email->getFrom()[0] ?? null;
        $fromName = $from ? $from->getName() : config('mail.from.name');
        $fromAddress = $from ? $from->getAddress() : config('mail.from.address');
        
        // Pulizia nome da eventuali virgolette
        $safeFromName = str_replace('"', '', $fromName);
        
        // Costruzione Headers
        $headers = [];
        $headers[] = 'From: "' . $safeFromName . '" <' . $fromAddress . '>';
        $headers[] = 'Reply-To: ' . $fromAddress;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: ' . ($isHtml ? 'text/html' : 'text/plain') . '; charset=utf-8';
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $headersString = implode("\r\n", $headers);

        // Invio tramite funzione nativa
        $success = @mail($to, $subject, $body, $headersString);

        if (!$success) {
            throw new \Exception("Errore nell'invio della mail tramite funzione mail() di PHP su Altervista.");
        }
    }

    /**
     * Rappresentazione testuale del transport.
     */
    public function __toString(): string
    {
        return 'altervista';
    }
}
