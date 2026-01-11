<x-mail::message>
    # Risposta alla tua segnalazione

    Gentile {{ $report->user?->name ?? $report->email }},

    abbiamo elaborato la tua segnalazione **#{{ $report->id }}** relativa a "{{ $report->category_label }}".

    **La nostra risposta:**
    {{ $responseContent }}

    ---

    **Dettagli della tua segnalazione:**
    * **Data:** {{ $report->created_at->format('d/m/Y') }}
    * **Descrizione:** {{ $report->description }}

    Grazie per aver contribuito a migliorare Phandalverse!

    by <a href="https://www.github.com/RickyMandich">Ricky Mandich</a>
</x-mail::message>