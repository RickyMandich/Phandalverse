<?php

namespace App\Http\Controllers;

use App\Models\SystemError;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Check if user is admin, return 403 view if not
     */
    private function checkAdmin()
    {
        if (!Auth::isAdmin()) {
            return view('errors.403');
        }
        return null;
    }

    /**
     * Display list of system errors
     */
    public function errors(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $query = SystemError::query()->orderBy('created_at', 'desc');

        if (!isset($request->status)) {
            $status = 'new';
        } else {
            $status = $request->status;
        }

        // Filter by status if provided
        if ($status !== 'all') {
            $query->where('status', $request->status);
        }

        $errors = $query->paginate(20);

        return view('admin.errors.index', compact('errors'));
    }

    /**
     * Display single error details
     */
    public function showError(SystemError $error)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        return view('admin.errors.show', compact('error'));
    }

    /**
     * Update error status and notes
     */
    public function updateError(Request $request, SystemError $error)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $validated = $request->validate([
            'status' => 'required|in:new,in_progress,resolved,ignored',
            'admin_notes' => 'nullable|string',
        ]);

        $error->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $error->admin_notes,
            'resolved_by' => in_array($validated['status'], ['resolved', 'ignored']) ? Auth::id() : null,
            'resolved_at' => in_array($validated['status'], ['resolved', 'ignored']) ? now() : null,
        ]);

        return redirect()->route('admin.errors.show', $error)->with('success', 'Stato errore aggiornato');
    }

    /**
     * Quick action from email link
     */
    public function quickActionError(SystemError $error, string $action)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        if (!in_array($action, ['resolved', 'ignored', 'in_progress'])) {
            return redirect()->route('admin.errors')->with('error', 'Azione non valida');
        }

        $error->update([
            'status' => $action,
            'resolved_by' => in_array($action, ['resolved', 'ignored']) ? Auth::id() : null,
            'resolved_at' => in_array($action, ['resolved', 'ignored']) ? now() : null,
        ]);

        return redirect()->route('admin.errors.show', $error)->with('success', 'Errore segnato come ' . $action);
    }

    // ========== GESTIONE UTENTI ==========

    /**
     * Display list of all users
     */
    public function users(Request $request)
    {
        $query = User::query()->orderBy('created_at', 'desc');

        // Ricerca per nome o email
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show form to create a new user
     */
    public function createUser()
    {
        return view('admin.users.create');
    }

    /**
     * Store a new user
     */
    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'admin' => 'boolean',
            'master' => 'boolean',
            'master_utils' => 'boolean',
            'showEmbedLink' => 'boolean',
            'collapseEmbed' => 'boolean',
        ]);

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'admin' => $request->has('admin'),
            'master' => $request->has('master'),
            'master_utils' => $request->has('master_utils'),
            'showEmbedLink' => $request->has('showEmbedLink'),
            'collapseEmbed' => $request->has('collapseEmbed'),
        ]);

        // Notifica su Telegram la creazione del nuovo utente
        try {
            if (env('TELEGRAM_BOT_TOKEN')) {
                \App\Services\TelegramService::notify(
                    'Nuovo utente',
                    "Nome: {$newUser->name}\nEmail: {$newUser->email}\nAdmin: " . ($newUser->admin ? 'sì' : 'no')
                );
            }
        } catch (\Exception $ex) {
            \Illuminate\Support\Facades\Log::error('Impossibile inviare notifica Telegram per nuovo utente: ' . $ex->getMessage());
        }

        return redirect()->route('admin.users')->with('success', 'Utente creato con successo');
    }

    /**
     * Show form to edit an existing user
     */
    public function editUser(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update an existing user's profile data
     */
    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validateWithBag('profile', [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'admin' => 'boolean',
            'master' => 'boolean',
            'master_utils' => 'boolean',
            'showEmbedLink' => 'boolean',
            'collapseEmbed' => 'boolean',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'admin' => $request->has('admin'),
            'master' => $request->has('master'),
            'master_utils' => $request->has('master_utils'),
            'showEmbedLink' => $request->has('showEmbedLink'),
            'collapseEmbed' => $request->has('collapseEmbed'),
        ]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'Dati utente aggiornati con successo');
    }

    /**
     * Update an existing user's password
     */
    public function updateUserPassword(Request $request, User $user)
    {
        $validated = $request->validateWithBag('password', [
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Notifica su Telegram che la password dell'utente è stata modificata
        try {
            $hasToken = env('TELEGRAM_BOT_TOKEN') ? true : false;
            $hasChatId = env('TELEGRAM_ADMIN_CHAT_ID') ? true : false;
            $actor = \Illuminate\Support\Facades\Auth::user();
            $by = $actor ? ($actor->email ?? $actor->name) : 'sistema';

            \Illuminate\Support\Facades\Log::info('Telegram notify attempt for password change', [
                'has_token' => $hasToken,
                'has_chat_id' => $hasChatId,
                'target_user' => $user->email,
                'actor' => $by,
            ]);

            if ($hasToken && $hasChatId) {
                \App\Services\TelegramService::notify(
                    'Password modificata',
                    "Utente: {$user->email}\nModificata da: {$by}"
                );

                \Illuminate\Support\Facades\Log::info('Telegram notify invoked for password change', ['target_user' => $user->email]);
            } else {
                \Illuminate\Support\Facades\Log::warning('Telegram not configured for password change notification', ['has_token' => $hasToken, 'has_chat_id' => $hasChatId]);
            }
        } catch (\Exception $ex) {
            \Illuminate\Support\Facades\Log::error('Impossibile inviare notifica Telegram per password aggiornata: ' . $ex->getMessage());
        }

        return redirect()->route('admin.users.edit', $user)->with('success', 'Password aggiornata con successo');
    }

    /**
     * Delete a user
     */
    public function deleteUser(User $user)
    {
        // Non permettere di eliminare se stessi
        if ($user->id === Auth::id()) {
            return redirect()->route('admin.users')->with('error', 'Non puoi eliminare te stesso');
        }

        $user->delete();

        return redirect()->route('admin.users')->with('success', 'Utente eliminato con successo');
    }

    /**
     * Approve a master request
     */
    public function approveMasterRequest(User $user)
    {
        $user->update([
            'master_utils' => true,
            'master_request' => false,
        ]);

        return redirect()->route('admin.users')->with('success', "L'utente {$user->name} è ora un Master Utils.");
    }

    /**
     * Deny a master request
     */
    public function denyMasterRequest(User $user)
    {
        $user->update([
            'master_request' => false,
        ]);

        return redirect()->route('admin.users')->with('success', "Richiesta di {$user->name} negata.");
    }
    // ========== STATISTICHE ==========

    /**
     * Display statistics page
     */
    public function statistics(Request $request)
    {
        // Default to last 7 days if no dates provided
        $startDate = $request->input('start_date', now()->subDays(7)->startOfDay()->format('Y-m-d H:i:s'));
        $endDate = $request->input('end_date', now()->endOfDay()->format('Y-m-d H:i:s'));

        // Base query for summary cards
        $baseQuery = \App\Models\Statistic::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($request->has('user_id') && $request->user_id) {
            $baseQuery->where('user_id', $request->user_id);
        }

        // Calculate summary stats
        $totalVisits = (clone $baseQuery)->count();
        $uniqueVisitors = (clone $baseQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $uniqueIPs = (clone $baseQuery)->distinct('ip_address')->count('ip_address');
        $avgResponseTime = (clone $baseQuery)->avg('response_time');

        // --- Data for Chart.js (2x2 Grid) ---

        // 1. Weekly Trends (6-hour blocks)
        $weekStart = now()->subDays(7)->startOfDay();
        $weekTrends = \App\Models\Statistic::query()
            ->where('created_at', '>=', $weekStart)
            ->selectRaw("
                CONCAT(DATE_FORMAT(created_at, '%d/%m '), LPAD(FLOOR(HOUR(created_at)/6)*6, 2, '0'), ':00') as label,
                COUNT(*) as count
            ")
            ->groupBy('label')
            ->orderByRaw('MIN(created_at)')
            ->get();

        // 2. Monthly Trends (Daily)
        $monthStart = now()->subDays(30)->startOfDay();
        $monthTrends = \App\Models\Statistic::query()
            ->where('created_at', '>=', $monthStart)
            ->selectRaw("DATE_FORMAT(created_at, '%d/%m') as label, COUNT(*) as count")
            ->groupBy('label')
            ->orderByRaw('MIN(created_at)')
            ->get();

        // 3. Weekly Distribution (Pie - All users)
        $weekDistribution = \App\Models\Statistic::query()
            ->where('statistics.created_at', '>=', $weekStart)
            ->selectRaw('COALESCE(users.name, statistics.ip_address) as label, COUNT(*) as count')
            ->leftJoin('users', 'statistics.user_id', '=', 'users.id')
            ->groupBy('label')
            ->orderByDesc('count')
            ->get();

        // 4. Monthly Distribution (Pie - All users)
        $monthDistribution = \App\Models\Statistic::query()
            ->where('statistics.created_at', '>=', $monthStart)
            ->selectRaw('COALESCE(users.name, statistics.ip_address) as label, COUNT(*) as count')
            ->leftJoin('users', 'statistics.user_id', '=', 'users.id')
            ->groupBy('label')
            ->orderByDesc('count')
            ->get();

        // Get grouped data for table (this still respects the user filters)
        $groupedStats = \App\Models\Statistic::getGroupedByUserAndIp($startDate, $endDate);

        if ($request->has('user_id') && $request->user_id) {
            $groupedStats->where('user_id', $request->user_id);
        }

        $stats = $groupedStats->orderByDesc('request_count')->paginate(20)->withQueryString();
        $users = User::orderBy('name')->get(); // For filter dropdown

        return view('admin.statistics', compact(
            'stats',
            'users',
            'startDate',
            'endDate',
            'totalVisits',
            'uniqueVisitors',
            'uniqueIPs',
            'avgResponseTime',
            'weekTrends',
            'monthTrends',
            'weekDistribution',
            'monthDistribution'
        ));
    }

    /**
     * Export statistics specific requests details for a group
     */
    public function statisticsDetails(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $userId = $request->input('user_id'); // Can be 'guest'
        $ipAddress = $request->input('ip_address');

        $query = \App\Models\Statistic::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('ip_address', $ipAddress)
            ->orderByDesc('created_at');

        if ($userId && $userId !== 'guest') {
            $query->where('user_id', $userId);
        } else {
            $query->whereNull('user_id');
        }

        $details = $query->get();

        return response()->json($details);
    }

    /**
     * Export statistics to CSV (Raw records)
     */
    public function exportStatisticsCSV(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(7)->format('Y-m-d H:i:s'));
        $endDate = $request->input('end_date', now()->format('Y-m-d H:i:s'));

        $filename = "statistics_raw_{$startDate}_{$endDate}.csv";

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($startDate, $endDate, $request) {
            $file = fopen('php://output', 'w');

            // Write headers (Match DB structure)
            fputcsv($file, ['ID', 'User', 'IP Address', 'URL', 'Method', 'Status', 'Response Time (ms)', 'Referrer', 'User Agent', 'Created At']);

            $query = \App\Models\Statistic::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->with('user')
                ->orderByDesc('created_at');

            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            $query->chunk(500, function ($rows) use ($file) {
                foreach ($rows as $row) {
                    fputcsv($file, [
                        $row->id,
                        $row->user ? $row->user->name : 'Guest',
                        $row->ip_address,
                        $row->url,
                        $row->http_method,
                        $row->response_status,
                        $row->response_time,
                        $row->referrer,
                        $row->user_agent,
                        $row->created_at->format('Y-m-d H:i:s')
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export statistics to JSON (Raw records)
     */
    public function exportStatisticsJSON(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(7)->format('Y-m-d H:i:s'));
        $endDate = $request->input('end_date', now()->format('Y-m-d H:i:s'));

        $filename = "statistics_raw_{$startDate}_{$endDate}.json";

        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($startDate, $endDate, $request) {
            $query = \App\Models\Statistic::query()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->with('user')
                ->orderByDesc('created_at');

            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            echo "[";
            $first = true;
            $query->chunk(500, function ($rows) use (&$first) {
                foreach ($rows as $row) {
                    if (!$first) {
                        echo ",";
                    }
                    echo json_encode([
                        'id' => $row->id,
                        'user' => $row->user ? $row->user->name : 'Guest',
                        'ip_address' => $row->ip_address,
                        'url' => $row->url,
                        'method' => $row->http_method,
                        'status' => $row->response_status,
                        'response_time' => $row->response_time,
                        'referrer' => $row->referrer,
                        'user_agent' => $row->user_agent,
                        'created_at' => $row->created_at->format('Y-m-d H:i:s')
                    ]);
                    $first = false;
                }
            });
            echo "]";
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Display database query page
     */
    public function database()
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        return view('admin.database');
    }

    /**
     * Execute a raw SQL query
     */
    public function executeQuery(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $validated = $request->validate([
            'query' => 'required|string',
        ]);

        $query = trim($validated['query']);
        $isSelect = stripos($query, 'select') === 0 || stripos($query, 'show') === 0 || stripos($query, 'describe') === 0 || stripos($query, 'explain') === 0;

        try {
            if ($isSelect) {
                $results = DB::select($query);
                $affectedRows = count($results);
            } else {
                $affectedRows = DB::statement($query);
                if (stripos($query, 'update') === 0 || stripos($query, 'delete') === 0 || stripos($query, 'insert') === 0) {
                    $affectedRows = DB::affectingStatement($query);
                }
                $results = null;
            }

            Log::channel('admin')->info('SQL Query Executed', [
                'user_id' => Auth::id(),
                'user_email' => Auth::user()->email,
                'query' => $query,
                'affected_rows' => $affectedRows
            ]);

            return view('admin.database', [
                'query' => $query,
                'results' => $results,
                'affectedRows' => $affectedRows,
                'isSelect' => $isSelect,
                'success' => 'Query eseguita con successo'
            ]);
        } catch (\Exception $e) {
            return view('admin.database', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send a test email using the configured mailer and queue
     */
    public function testMail(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $email = $request->input('email', Auth::user()->email);

        try {
            // Utilizzo il servizio di coda per testare l'intero flusso (rate limiting incluso)
            \App\Services\EmailQueueService::queue(
                new \App\Mail\ErrorNotificationEmail(new \Exception('Test invio email tramite Altervista Mailer')),
                $email,
                'Test manuale mailer'
            );

            return view('admin.test-mail', [
                'success' => "Email di test accodata con successo per {$email}.",
                'email' => $email
            ]);
        } catch (\Exception $e) {
            Log::error('Errore test mail: ' . $e->getMessage());
            return view('admin.test-mail', [
                'error' => "Errore durante l'accodamento: " . $e->getMessage(),
                'email' => $email
            ]);
        }
    }
}

