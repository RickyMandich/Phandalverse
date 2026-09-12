<?php

namespace App\Http\Controllers;

use App\Models\AccessGroup;
use App\Models\Campaign;
use App\Models\SystemError;
use App\Models\User;
use App\Services\AccessControlService;
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
        if (!Auth::check() || !Auth::user()->isAdmin()) {
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

    // ========== GESTIONE CAMPAGNE ==========

    /**
     * Display list of all campaigns
     */
    public function campaigns()
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $campaigns = Campaign::withCount(['users', 'accessGroups', 'telegramSubscribers'])
            ->orderBy('order')
            ->paginate(20);

        return view('admin.campaigns.index', compact('campaigns'));
    }

    /**
     * Show form to create a new campaign
     */
    public function createCampaign()
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $maxOrder = (int) Campaign::max('order');
        $suggestedOrder = $maxOrder > 0 ? $maxOrder + 10 : 10;
        $users = User::orderBy('name')->get();

        return view('admin.campaigns.create', compact('suggestedOrder', 'users'));
    }

    /**
     * Store a new campaign
     */
    public function storeCampaign(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $validated = $request->validate([
            'folder_name' => [
                'required',
                'string',
                'max:255',
                'unique:campaigns,folder_name',
                'regex:/^[a-zA-Z0-9_\-]+$/',
            ],
            'display_name' => 'required|string|max:255',
            'order' => 'required|integer|unique:campaigns,order',
            'users' => 'nullable|array',
            'users.*' => 'exists:users,id',
        ], [
            'folder_name.regex' => 'Il nome cartella/branch deve contenere solo lettere, numeri, trattini e underscore (compatibile con i branch git).',
        ]);

        $campaign = Campaign::create([
            'folder_name' => $validated['folder_name'],
            'display_name' => $validated['display_name'],
            'order' => $validated['order'],
        ]);

        if ($request->has('users')) {
            $campaign->users()->sync($request->input('users', []));
        }

        return redirect()->route('admin.campaigns')->with('success', 'Campagna creata con successo');
    }

    /**
     * Show form to edit a campaign
     */
    public function editCampaign(Campaign $campaign)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $users = User::orderBy('name')->get();
        $campaignUserIds = $campaign->users->pluck('id')->toArray();

        return view('admin.campaigns.edit', compact('campaign', 'users', 'campaignUserIds'));
    }

    /**
     * Update an existing campaign
     */
    public function updateCampaign(Request $request, Campaign $campaign)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $validated = $request->validate([
            'folder_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('campaigns')->ignore($campaign->id),
                'regex:/^[a-zA-Z0-9_\-]+$/',
            ],
            'display_name' => 'required|string|max:255',
            'order' => [
                'required',
                'integer',
                Rule::unique('campaigns')->ignore($campaign->id),
            ],
            'users' => 'nullable|array',
            'users.*' => 'exists:users,id',
        ], [
            'folder_name.regex' => 'Il nome cartella/branch deve contenere solo lettere, numeri, trattini e underscore (compatibile con i branch git).',
        ]);

        $campaign->update([
            'folder_name' => $validated['folder_name'],
            'display_name' => $validated['display_name'],
            'order' => $validated['order'],
        ]);

        if ($request->has('users')) {
            $campaign->users()->sync($request->input('users', []));
        }

        return redirect()->route('admin.campaigns')->with('success', 'Campagna aggiornata con successo');
    }

    /**
     * Delete a campaign
     */
    public function deleteCampaign(Campaign $campaign)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $campaign->delete();

        return redirect()->route('admin.campaigns')->with('success', 'Campagna eliminata con successo');
    }

    /**
     * Scollega il gruppo Telegram associato a una campagna (e rimuove la relativa iscrizione).
     */
    public function unlinkTelegramGroup(Campaign $campaign)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        if ($campaign->telegram_chat_id) {
            \App\Models\TelegramSubscriber::where('chat_id', $campaign->telegram_chat_id)
                ->where('thread_id', $campaign->telegram_thread_id)
                ->where('campaign_id', $campaign->id)
                ->delete();

            $campaign->update(['telegram_chat_id' => null, 'telegram_thread_id' => null]);
        }

        return redirect()->route('admin.campaigns')->with('success', 'Gruppo Telegram scollegato dalla campagna');
    }

    // ========== ISCRIZIONI TELEGRAM ==========

    /**
     * Display list of all Telegram subscriptions
     */
    public function telegramSubscribers(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $query = \App\Models\TelegramSubscriber::with('campaign')->orderBy('created_at', 'desc');

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        $subscribers = $query->paginate(30)->withQueryString();

        $telegramUserIds = $subscribers->getCollection()->pluck('telegram_user_id')->filter()->unique();
        $linkedUsers = User::whereIn('telegram_user_id', $telegramUserIds)->get()->keyBy('telegram_user_id');

        $campaigns = Campaign::orderBy('order')->get();
        $totalSubscribers = \App\Models\TelegramSubscriber::count();
        $linkedCount = \App\Models\TelegramSubscriber::whereNotNull('telegram_user_id')->count();

        return view('admin.telegram.index', compact('subscribers', 'linkedUsers', 'campaigns', 'totalSubscribers', 'linkedCount'));
    }

    /**
     * Delete a Telegram subscription
     */
    public function deleteTelegramSubscriber(\App\Models\TelegramSubscriber $subscriber)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $subscriber->delete();

        return redirect()->route('admin.telegram.index')->with('success', 'Iscrizione eliminata con successo');
    }

    // ========== GESTIONE UTENTI ==========

    /**
     * Display list of all users
     */
    public function users(Request $request)
    {
        $query = User::query()->with(['accessGroups', 'campaigns', 'defaultCampaign'])->orderBy('created_at', 'desc');

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
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $accessGroups = AccessGroup::with(['parent', 'campaign'])->orderBy('name')->get();
        $campaigns = Campaign::orderBy('order')->get();

        return view('admin.users.create', compact('accessGroups', 'campaigns'));
    }

    /**
     * Store a new user
     */
    public function storeUser(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'admin' => 'boolean',
            'master' => 'boolean',
            'master_utils' => 'boolean',
            'showEmbedLink' => 'boolean',
            'collapseEmbed' => 'boolean',
            'default_campaign_id' => 'nullable|exists:campaigns,id',
            'campaigns' => 'nullable|array',
            'campaigns.*' => 'exists:campaigns,id',
            'access_groups' => 'nullable|array',
            'access_groups.*' => 'exists:access_groups,id',
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
            'default_campaign_id' => $validated['default_campaign_id'] ?? null,
            'email_verified_at' => $request->has('verified') ? now() : null,
        ]);

        if ($request->has('campaigns')) {
            $newUser->campaigns()->sync($request->input('campaigns', []));
        }

        if ($request->has('access_groups')) {
            $newUser->accessGroups()->sync($request->input('access_groups', []));
        }

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
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $accessGroups = AccessGroup::with(['parent', 'campaign'])->orderBy('name')->get();
        $userGroupIds = $user->accessGroups->pluck('id')->toArray();
        $campaigns = Campaign::orderBy('order')->get();
        $userCampaignIds = $user->campaigns->pluck('id')->toArray();

        return view('admin.users.edit', compact('user', 'accessGroups', 'userGroupIds', 'campaigns', 'userCampaignIds'));
    }

    /**
     * Update an existing user's profile data
     */
    public function updateUser(Request $request, User $user)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $validated = $request->validateWithBag('profile', [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'admin' => 'boolean',
            'master' => 'boolean',
            'master_utils' => 'boolean',
            'showEmbedLink' => 'boolean',
            'collapseEmbed' => 'boolean',
            'default_campaign_id' => 'nullable|exists:campaigns,id',
            'campaigns' => 'nullable|array',
            'campaigns.*' => 'exists:campaigns,id',
            'access_groups' => 'nullable|array',
            'access_groups.*' => 'exists:access_groups,id',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'admin' => $request->has('admin'),
            'master' => $request->has('master'),
            'master_utils' => $request->has('master_utils'),
            'showEmbedLink' => $request->has('showEmbedLink'),
            'collapseEmbed' => $request->has('collapseEmbed'),
            'default_campaign_id' => $validated['default_campaign_id'] ?? null,
        ]);

        $user->campaigns()->sync($request->input('campaigns', []));
        $user->accessGroups()->sync($request->input('access_groups', []));

        if ($request->has('verified') && !$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        } elseif (!$request->has('verified') && $user->email_verified_at) {
            $user->email_verified_at = null;
            $user->save();
        }

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

        try {
            $hasToken = env('TELEGRAM_BOT_TOKEN') ? true : false;
            $hasChatId = env('TELEGRAM_ADMIN_CHAT_ID') ? true : false;
            $actor = \Illuminate\Support\Facades\Auth::user();
            $by = $actor ? ($actor->email ?? $actor->name) : 'sistema';

            if ($hasToken && $hasChatId) {
                \App\Services\TelegramService::notify(
                    'Password modificata',
                    "Utente: {$user->email}\nModificata da: {$by}"
                );
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
        $startDate = $request->input('start_date', now()->subDays(7)->startOfDay()->format('Y-m-d H:i:s'));
        $endDate = $request->input('end_date', now()->endOfDay()->format('Y-m-d H:i:s'));

        $baseQuery = \App\Models\Statistic::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($request->has('user_id') && $request->user_id) {
            $baseQuery->where('user_id', $request->user_id);
        }

        $totalVisits = (clone $baseQuery)->count();
        $uniqueVisitors = (clone $baseQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $uniqueIPs = (clone $baseQuery)->distinct('ip_address')->count('ip_address');
        $avgResponseTime = (clone $baseQuery)->avg('response_time');

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

        $monthStart = now()->subDays(30)->startOfDay();
        $monthTrends = \App\Models\Statistic::query()
            ->where('created_at', '>=', $monthStart)
            ->selectRaw("DATE_FORMAT(created_at, '%d/%m') as label, COUNT(*) as count")
            ->groupBy('label')
            ->orderByRaw('MIN(created_at)')
            ->get();

        $weekDistribution = \App\Models\Statistic::query()
            ->where('statistics.created_at', '>=', $weekStart)
            ->selectRaw('COALESCE(users.name, statistics.ip_address) as label, COUNT(*) as count')
            ->leftJoin('users', 'statistics.user_id', '=', 'users.id')
            ->groupBy('label')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $topPages = (clone $baseQuery)
            ->selectRaw('url, COUNT(*) as count, AVG(response_time) as avg_time')
            ->groupBy('url')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $users = User::orderBy('name')->get();

        return view('admin.statistics', compact(
            'totalVisits',
            'uniqueVisitors',
            'uniqueIPs',
            'avgResponseTime',
            'weekTrends',
            'monthTrends',
            'weekDistribution',
            'topPages',
            'users',
            'startDate',
            'endDate'
        ));
    }

    public function exportStatisticsCSV(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $startDate = $request->input('start_date', now()->subDays(7)->startOfDay()->format('Y-m-d H:i:s'));
        $endDate = $request->input('end_date', now()->endOfDay()->format('Y-m-d H:i:s'));

        $filename = "statistics_" . date('Y-m-d_H-i-s') . ".csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () use ($startDate, $endDate, $request) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'User', 'IP Address', 'URL', 'Method', 'Status', 'Response Time (ms)', 'Referrer', 'User Agent', 'Created At']);

            $query = \App\Models\Statistic::with('user')
                ->whereBetween('created_at', [$startDate, $endDate]);

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

    public function exportStatisticsJSON(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $startDate = $request->input('start_date', now()->subDays(7)->startOfDay()->format('Y-m-d H:i:s'));
        $endDate = $request->input('end_date', now()->endOfDay()->format('Y-m-d H:i:s'));

        $filename = "statistics_" . date('Y-m-d_H-i-s') . ".json";
        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () use ($startDate, $endDate, $request) {
            echo "[";
            $first = true;

            $query = \App\Models\Statistic::with('user')
                ->whereBetween('created_at', [$startDate, $endDate]);

            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }

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

    public function statisticsDetails(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $query = \App\Models\Statistic::with('user')->orderBy('created_at', 'desc');

        if ($request->has('user_id') && $request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('url') && $request->url) {
            $query->where('url', 'like', "%{$request->url}%");
        }

        $statistics = $query->paginate(50);
        $users = User::orderBy('name')->get();

        return view('admin.statistics-details', compact('statistics', 'users'));
    }

    // ========== DATABASE ==========

    public function database()
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        return view('admin.database');
    }

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

    // ========== TEST MAIL ==========

    public function testMail(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $email = $request->input('email', Auth::user()->email);

        try {
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

    // ========== GESTIONE GRUPPI DI ACCESSO ==========

    public function accessGroups()
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $groups = AccessGroup::with(['parent', 'children', 'campaign'])->withCount('users')->orderBy('name')->get();

        return view('admin.access_groups.index', compact('groups'));
    }

    public function createAccessGroup()
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $parents = AccessGroup::orderBy('name')->get();
        $campaigns = Campaign::orderBy('order')->get();

        return view('admin.access_groups.create', compact('parents', 'campaigns'));
    }

    public function storeAccessGroup(Request $request)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $campaignId = $request->input('campaign_id');

        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('access_groups')->where(fn ($query) => $query->where('campaign_id', $campaignId)),
                'regex:/^[a-z][a-zA-Z0-9]*$/',
            ],
            'description' => 'nullable|string',
            'color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'parent_id' => 'nullable|exists:access_groups,id',
        ], [
            'slug.regex' => 'Lo slug deve essere in formato camelCase (es. mioGruppo o cavalieriDelDrago) e non può contenere trattini o underscore.',
            'color.regex' => 'Il colore deve essere un codice esadecimale valido (es. #a83232).',
        ]);

        AccessGroup::create($validated);
        AccessControlService::clearCache();

        return redirect()->route('admin.access_groups')->with('success', 'Gruppo di accesso creato con successo');
    }

    public function editAccessGroup(AccessGroup $group)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $descendantIds = $this->getDescendantIds($group);
        $excludedIds = array_merge([$group->id], $descendantIds);

        $parents = AccessGroup::whereNotIn('id', $excludedIds)->orderBy('name')->get();
        $campaigns = Campaign::orderBy('order')->get();

        return view('admin.access_groups.edit', compact('group', 'parents', 'campaigns'));
    }

    public function updateAccessGroup(Request $request, AccessGroup $group)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $descendantIds = $this->getDescendantIds($group);
        $excludedIds = array_merge([$group->id], $descendantIds);
        $campaignId = $request->input('campaign_id', $group->campaign_id);

        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('access_groups')->where(fn ($query) => $query->where('campaign_id', $campaignId))->ignore($group->id),
                'regex:/^[a-z][a-zA-Z0-9]*$/',
            ],
            'description' => 'nullable|string',
            'color' => ['nullable', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'parent_id' => [
                'nullable',
                'exists:access_groups,id',
                Rule::notIn($excludedIds),
            ],
        ], [
            'slug.regex' => 'Lo slug deve essere in formato camelCase (es. mioGruppo o cavalieriDelDrago) e non può contenere trattini o underscore.',
            'color.regex' => 'Il colore deve essere un codice esadecimale valido (es. #a83232).',
            'parent_id.not_in' => 'Un gruppo non può avere come padre se stesso o uno dei suoi discendenti.',
        ]);

        $group->update($validated);
        AccessControlService::clearCache();

        return redirect()->route('admin.access_groups')->with('success', 'Gruppo di accesso aggiornato con successo');
    }

    public function deleteAccessGroup(AccessGroup $group)
    {
        if ($response = $this->checkAdmin()) {
            return $response;
        }

        $group->delete();
        AccessControlService::clearCache();

        return redirect()->route('admin.access_groups')->with('success', 'Gruppo di accesso eliminato con successo');
    }

    private function getDescendantIds(AccessGroup $group): array
    {
        $descendants = [];
        foreach ($group->children as $child) {
            $descendants[] = $child->id;
            $descendants = array_merge($descendants, $this->getDescendantIds($child));
        }
        return $descendants;
    }
}
