<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\SystemError;
use Throwable;

class ErrorNotificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $exception;
    public $exceptionClass;
    public $errorMessage;
    public $errorFile;
    public $errorLine;
    public $requestUrl;
    public $requestMethod;
    public $userAgent;
    public $timestamp;
    public $systemError;

    public function __construct(
        Throwable $exception,
        ?string $requestUrl = null,
        ?string $requestMethod = null,
        ?string $userAgent = null,
        ?SystemError $systemError = null
    ) {
        $this->exception = $exception;
        $this->exceptionClass = get_class($exception);
        $this->errorMessage = $exception->getMessage();
        $this->errorFile = $exception->getFile();
        $this->errorLine = $exception->getLine();
        $this->requestUrl = $requestUrl;
        $this->requestMethod = $requestMethod;
        $this->userAgent = $userAgent;
        $this->timestamp = now()->format('d/m/Y H:i:s');
        $this->systemError = $systemError;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[' . config('app.name') . '] Errore Sistema - ' . class_basename($this->exception),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.error-notification',
            with: [
                'exceptionClass' => $this->exceptionClass,
                'errorMessage' => $this->errorMessage,
                'errorFile' => $this->errorFile,
                'errorLine' => $this->errorLine,
                'requestUrl' => $this->requestUrl,
                'requestMethod' => $this->requestMethod,
                'userAgent' => $this->userAgent,
                'timestamp' => $this->timestamp,
                'systemError' => $this->systemError,
            ],
        );
    }
}

