<?php

namespace App\Http\Controllers;

use App\Models\UserReport;
use App\Services\CustomLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReportResponseMail;
use Log;

class ReportController extends Controller
{
    /**
     * Mostra il form per creare una nuova segnalazione
     */
    public function create(Request $request)
    {
        $referrerUrl = $request->query('from', '');

        return view('reports.create', [
            'categories' => UserReport::CATEGORIES,
            'referrerUrl' => $referrerUrl,
        ]);
    }

    /**
     * Salva una nuova segnalazione
     */
    public function store(Request $request)
    {
        Log::debug('cerco di creare una segnalazione con questi dati:\n' .
            "category: " . print_r($request->category, true) . "\n" .
            "description: " . print_r($request->description, true) . "\n" .
            "page_urls: " . print_r($request->page_urls, true) . "\n" .
            "email: " . print_r($request->email, true));
        $validated = $request->validate([
            'category' => 'required|in:logic,display,other',
            'description' => 'required|string|min:10|max:2000',
            'page_urls' => 'nullable|string|max:1000',
            'email' => 'nullable|email',
        ]);

        // Converti le URL in array (separate da newline)
        $pageUrls = null;
        if (!empty($validated['page_urls'])) {
            $pageUrls = array_filter(
                array_map('trim', explode("\n", $validated['page_urls']))
            );
        }

        $report = UserReport::create([
            'category' => $validated['category'],
            'description' => $validated['description'],
            'page_urls' => $pageUrls,
            'user_id' => Auth::id(), // null se non loggato
            'email' => $validated['email'],
        ]);

        // Notifica Telegram per nuova segnalazione
        try {
            if (env('TELEGRAM_BOT_TOKEN')) {
                $from = Auth::check() ? (Auth::user()->email ?? Auth::user()->name) : $report->email;
                \App\Services\TelegramService::notifyNewReport(
                    'Segnalazione - ' . $report->category,
                    $report->description,
                    $from,
                );
            }
        } catch (\Exception $ex) {
            \Illuminate\Support\Facades\Log::error('Impossibile inviare notifica Telegram per nuova segnalazione: ' . $ex->getMessage());
        }

        return redirect()
            ->route('report.create')
            ->with('success', 'Grazie! La tua segnalazione è stata inviata con successo.');
    }

    /**
     * Lista delle segnalazioni (solo admin)
     */
    public function index(Request $request)
    {
        $query = UserReport::with('user')->latest();

        // Default a "pending" se non specificato
        if (!$request->has('status')) {
            $request->merge(['status' => 'pending']);
        }

        // Filtro per stato (escludi solo se 'all')
        if ($request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filtro per categoria
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $reports = $query->paginate(20);

        return view('reports.index', [
            'reports' => $reports,
            'categories' => UserReport::CATEGORIES,
            'statuses' => UserReport::STATUSES,
        ]);
    }

    /**
     * Mostra una singola segnalazione (solo admin)
     */
    public function show(UserReport $report)
    {
        return view('reports.show', [
            'report' => $report,
            'statuses' => UserReport::STATUSES,
        ]);
    }

    /**
     * Aggiorna lo stato di una segnalazione (solo admin)
     */
    public function update(Request $request, UserReport $report)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,in_progress,resolved,rejected',
            'admin_notes' => 'nullable|string|max:2000',
            'response' => 'nullable|string|max:2000',
        ]);

        $report->update($validated);

        if ($report->wasChanged('response') && !empty($report->response)) {
            $email = $report->user ? $report->user->email : $report->email;

            if ($email) {
                try {
                    Mail::to($email)->send(new ReportResponseMail($report, $report->response));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Errore invio mail risposta segnalazione: ' . $e->getMessage());
                }
            }
        }

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('success', 'Segnalazione aggiornata.');
    }
}

