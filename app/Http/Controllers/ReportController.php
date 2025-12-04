<?php

namespace App\Http\Controllers;

use App\Models\UserReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $validated = $request->validate([
            'category' => 'required|in:logic,display,other',
            'description' => 'required|string|min:10|max:2000',
            'page_urls' => 'nullable|string|max:1000',
        ]);

        // Converti le URL in array (separate da newline)
        $pageUrls = null;
        if (!empty($validated['page_urls'])) {
            $pageUrls = array_filter(
                array_map('trim', explode("\n", $validated['page_urls']))
            );
        }

        UserReport::create([
            'category' => $validated['category'],
            'description' => $validated['description'],
            'page_urls' => $pageUrls,
            'user_id' => Auth::id(), // null se non loggato
        ]);

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

        // Filtro per stato
        if ($request->filled('status')) {
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
        ]);

        $report->update($validated);

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('success', 'Segnalazione aggiornata.');
    }
}

