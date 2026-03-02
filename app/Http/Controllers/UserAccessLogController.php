<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserAccessLog;
use App\Jobs\UserAccessLogJob;
use App\Services\ErrorDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserAccessLogController extends Controller
{
    /**
     * Display user access logs
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();

            $logs = UserAccessLog::with('user')
                ->when($request->user_id, function ($query, $userId) {
                    return $query->where('user_id', $userId);
                })
                ->when($request->action, function ($query, $action) {
                    return $query->where('action', $action);
                })
                ->when($request->date_from, function ($query, $dateFrom) {
                    return $query->whereDate('created_at', '>=', $dateFrom);
                })
                ->when($request->date_to, function ($query, $dateTo) {
                    return $query->whereDate('created_at', '<=', $dateTo);
                })
                ->orderBy('created_at', 'desc')
                ->paginate(50);

            return view('admin.user-access-logs', compact('logs'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading user access logs: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            abort(500, 'Failed to load user access logs');
        }
    }

    /**
     * Show user access logs for specific user
     */
    public function userLogs($userId)
    {
        try {
            $user = User::findOrFail($userId);

            $logs = UserAccessLog::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate(50);

            return view('admin.user-access-logs', compact('logs', 'user'));
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading user specific access logs: ' . $e->getMessage(), [
                'user_id' => $userId,
                'current_user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            abort(500, 'Failed to load user access logs');
        }
    }

    /**
     * Get user access statistics
     */
    public function statistics(Request $request)
    {
        try {
            $days = $request->get('days', 30);

            $stats = [
                'total_logins' => UserAccessLog::action('login')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->count(),

                'unique_users' => UserAccessLog::action('login')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->distinct('user_id')
                    ->count(),

                'top_countries' => UserAccessLog::selectRaw('country, COUNT(*) as count')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->whereNotNull('country')
                    ->groupBy('country')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get(),

                'top_browsers' => UserAccessLog::selectRaw('browser, COUNT(*) as count')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->whereNotNull('browser')
                    ->groupBy('browser')
                    ->orderBy('count', 'desc')
                    ->limit(10)
                    ->get(),

                'daily_logins' => UserAccessLog::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                    ->action('login')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
            ];

            return response()->json($stats);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading user access statistics: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'days' => $request->get('days', 30),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to load access statistics'], 500);
        }
    }

    /**
     * Test the logging functionality
     */
    public function test(Request $request)
    {
        try {
            if (!Auth::check()) {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            $user = Auth::user();

            // Get real IP address using the same logic as LoginController
            $realIp = $this->getRealIpAddress($request);

            // Dispatch a test login log
            UserAccessLogJob::dispatch(
                $user->id,
                'login',
                session()->getId(),
                $realIp,
                $request->userAgent(),
                [
                    'test' => true,
                    'timestamp' => now()->toDateTimeString()
                ]
            );

            return response()->json([
                'message' => 'Test access log job dispatched successfully',
                'user_id' => $user->id,
                'session_id' => session()->getId(),
                'detected_ip' => $realIp,
                'request_ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error in test access log: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to dispatch test access log'], 500);
        }
    }

    /**
     * Get the user's real IP address, handling proxies and load balancers.
     */
    private function getRealIpAddress(Request $request)
    {
        // Check for shared internet/proxy
        if (!empty($request->server('HTTP_CLIENT_IP'))) {
            $ip = $request->server('HTTP_CLIENT_IP');
        }
        // Check for remote address (could be proxy)
        elseif (!empty($request->server('HTTP_X_FORWARDED_FOR'))) {
            $ip = $request->server('HTTP_X_FORWARDED_FOR');
        }
        // Check for remote address from share via proxy
        elseif (!empty($request->server('HTTP_X_FORWARDED'))) {
            $ip = $request->server('HTTP_X_FORWARDED');
        }
        // Check for remote address from remote address
        elseif (!empty($request->server('HTTP_X_CLUSTER_CLIENT_IP'))) {
            $ip = $request->server('HTTP_X_CLUSTER_CLIENT_IP');
        } elseif (!empty($request->server('HTTP_FORWARDED_FOR'))) {
            $ip = $request->server('HTTP_FORWARDED_FOR');
        } elseif (!empty($request->server('HTTP_FORWARDED'))) {
            $ip = $request->server('HTTP_FORWARDED');
        }
        // Fall back to default
        else {
            $ip = $request->ip();
        }

        // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }

        // Validate the IP
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            // Check if it's a public IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        // If we only have a local/private IP, try to get the real public IP
        $publicIp = $this->getPublicIP();
        if ($publicIp) {
            return $publicIp;
        }

        // If all else fails, return the default IP
        return $request->ip();
    }

    /**
     * Get the real public IP address from external services.
     */
    private function getPublicIP()
    {
        try {
            // Try multiple services to get public IP
            $services = [
                'https://api.ipify.org',
                'https://ipinfo.io/ip',
                'https://icanhazip.com',
                'https://ident.me'
            ];

            foreach ($services as $service) {
                try {
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 3,
                            'method' => 'GET'
                        ]
                    ]);

                    $ip = @file_get_contents($service, false, $context);
                    if ($ip) {
                        $ip = trim($ip);
                        // Validate the IP
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            return $ip;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }

        return null;
    }

    /**
     * User Access Interface - Main view with tabs
     */
    public function userAccessInterface()
    {
        try {
            return view('user.access');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading user access interface: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            abort(500, 'Failed to load user access interface');
        }
    }

    /**
     * Get access summary - grouped by user
     */
    public function accessSummary(Request $request)
    {
        try {
            $query = UserAccessLog::with('user')
                ->selectRaw('
                    user_id,
                    MAX(CASE WHEN action = ? THEN login_at END) as last_login,
                    MAX(CASE WHEN action = ? THEN logout_at END) as last_logout,
                    COUNT(*) as total_sessions,
                    COUNT(CASE WHEN action = ? THEN 1 END) as login_count,
                    COUNT(CASE WHEN action = ? THEN 1 END) as logout_count,
                    MAX(ip_address) as last_ip,
                    MAX(country) as last_country,
                    MAX(city) as last_city
                ', ['login', 'logout', 'login', 'logout'])
                ->groupBy('user_id');

            // Apply filters
            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            $summary = $query->orderByDesc('last_login')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'data' => $summary->items(),
                'pagination' => [
                    'current_page' => $summary->currentPage(),
                    'last_page' => $summary->lastPage(),
                    'per_page' => $summary->perPage(),
                    'total' => $summary->total(),
                    'from' => $summary->firstItem(),
                    'to' => $summary->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading access summary: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to load summary: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get detailed access logs for a specific user
     */
    public function accessDetails(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            if (!$userId) {
                return response()->json(['error' => 'User ID is required'], 400);
            }

            $query = UserAccessLog::where('user_id', $userId)
                ->with('user');

            // Apply filters
            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            $details = $query->orderByDesc('created_at')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'data' => $details->items(),
                'pagination' => [
                    'current_page' => $details->currentPage(),
                    'last_page' => $details->lastPage(),
                    'per_page' => $details->perPage(),
                    'total' => $details->total(),
                    'from' => $details->firstItem(),
                    'to' => $details->lastItem()
                ],
                'user' => $details->first()?->user
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading access details: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'target_user_id' => $request->get('user_id'),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to load details: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all access logs
     */
    public function allAccess(Request $request)
    {
        try {
            $query = UserAccessLog::with('user');

            // Apply filters
            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }

            if ($request->filled('country')) {
                $query->where('country', 'like', '%' . $request->country . '%');
            }

            $logs = $query->orderByDesc('created_at')
                ->paginate($request->get('per_page', 10));

            return response()->json([
                'data' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading all access logs: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to load logs: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export access logs to Excel
     */
    public function exportAccess(Request $request)
    {
        try {
            $query = UserAccessLog::with('user');

            // Apply same filters as allAccess
            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }

            if ($request->filled('country')) {
                $query->where('country', 'like', '%' . $request->country . '%');
            }

            $logs = $query->orderByDesc('created_at')->get();

            $filename = 'user_access_logs_' . date('Y-m-d_H-i-s') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($logs) {
                $file = fopen('php://output', 'w');

                // CSV Header
                fputcsv($file, [
                    'ID',
                    'User',
                    'Email',
                    'Action',
                    'IP Address',
                    'Browser',
                    'Platform',
                    'Device',
                    'Country',
                    'City',
                    'Region',
                    'ISP',
                    'Timezone',
                    'Latitude',
                    'Longitude',
                    'Login Time',
                    'Logout Time',
                    'Session Duration',
                    'Created At'
                ]);

                // Data rows
                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log->id,
                        $log->user->username ?? $log->user->email ?? 'Unknown',
                        $log->user->email ?? 'Unknown',
                        ucfirst($log->action),
                        $log->ip_address,
                        $log->browser,
                        $log->platform,
                        $log->device,
                        $log->country,
                        $log->city,
                        $log->region,
                        $log->isp,
                        $log->timezone,
                        $log->latitude,
                        $log->longitude,
                        $log->login_at ? $log->login_at->format('Y-m-d H:i:s') : '',
                        $log->logout_at ? $log->logout_at->format('Y-m-d H:i:s') : '',
                        $log->formatted_session_duration ?? '',
                        $log->created_at->format('Y-m-d H:i:s')
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error exporting access logs: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to export: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Encrypt user ID for secure URL generation
     */
    public function encryptUserId(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            if (!$userId) {
                return response()->json(['error' => 'User ID is required'], 400);
            }

            // Verify user exists
            $user = User::find($userId);
            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            $encryptedId = encrypt($userId);

            return response()->json([
                'encrypted_id' => $encryptedId,
                'user_id' => $userId
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error encrypting user ID: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'target_user_id' => $request->get('user_id'),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to encrypt user ID'], 500);
        }
    }

    /**
     * Show user access details page
     */
    public function showUserDetails($encryptedUserId)
    {
        try {
            // Decrypt the user ID
            $userId = decrypt($encryptedUserId);

            $user = User::findOrFail($userId);

            // Get all access logs for this user, ordered by ID descending (latest first)
            $accessLogs = UserAccessLog::where('user_id', $userId)
                ->orderBy('id', 'desc')
                ->get();

            // Calculate statistics
            $totalSessions = $accessLogs->count();
            $loginSessions = $accessLogs->where('action', 'login')->count();
            $logoutSessions = $accessLogs->where('action', 'logout')->count();
            $uniqueIPs = $accessLogs->pluck('ip_address')->unique()->count();
            $lastLogin = $accessLogs->where('action', 'login')->first();
            $lastLoginTime = $lastLogin ? ($lastLogin->login_at ?? $lastLogin->created_at) : null;

            $stats = [
                'total_sessions' => $totalSessions,
                'login_sessions' => $loginSessions,
                'logout_sessions' => $logoutSessions,
                'unique_ips' => $uniqueIPs,
                'last_login' => $lastLoginTime
            ];

            return view('user.access-details', compact('userId', 'user', 'accessLogs', 'stats'));
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Invalid encrypted user ID: ' . $e->getMessage(), [
                'encrypted_id' => $encryptedUserId,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            abort(404, 'Invalid user identifier');
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading user access details page: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'encrypted_user_id' => $encryptedUserId,
                'trace' => $e->getTraceAsString()
            ]);
            abort(404, 'User not found');
        }
    }
}
