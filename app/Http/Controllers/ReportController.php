<?php

namespace App\Http\Controllers;

use App\Models\UserReport;
use App\Services\CustomLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
        $categoriesValidator = 'required|in:' . UserReport::getCategoriesKeysForValidate();
        Log::debug("cerco di creare una segnalazione con questi dati:\n" .
            "\tcategory: " . print_r($request->category, true) . "\n" .
            "\tdescription: " . print_r($request->description, true) . "\n" .
            "\tpage_urls: " . print_r($request->page_urls, true) . "\n" .
            "\temail: " . print_r($request->email, true) . "\n" .
            "categories validator: |$categoriesValidator|");


        $request->mergeIfMissing([
            'email' => null,
            'page_urls' => null,
        ]);

        $validated = $request->validate([
            'category' => $categoriesValidator,
            'description' => 'required|string|min:10|max:2000',
            'page_urls' => 'nullable|string|max:1000',
            'email' => 'nullable|email',
            'images.*' => 'nullable|image|max:5120', // max 5MB per immagine
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

        // Gestione immagini
        if ($request->hasFile('images')) {
            $directory = "reports/{$report->id}";

            // Crea la cartella se non esiste (anche se store() lo farebbe comunque)
            if (!Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            foreach ($request->file('images') as $image) {
                $image->store($directory, 'public');
            }
        }

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
    /**
     * Mostra l'immagine di una segnalazione aggirando il symlink storage di Laravel (utile su hosting condivisi)
     */
    public function showImage($path)
    {
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }
}

