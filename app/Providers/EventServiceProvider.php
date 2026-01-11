<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        /*
        \Illuminate\Mail\Events\MessageSent::class => [
            \App\Listeners\ForwardMailToTelegram::class,
        ],
        */
        // aggiungi qui altri eventi se serve
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }
}