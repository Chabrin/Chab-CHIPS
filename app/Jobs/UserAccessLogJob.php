<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserAccessLog;
use App\Services\ErrorDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;
use Torann\GeoIP\Facades\GeoIP;
use GuzzleHttp\Client;

class UserAccessLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $action;
    protected $sessionId;
    protected $ipAddress;
    protected $userAgent;
    protected $additionalData;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $action, $sessionId = null, $ipAddress = null, $userAgent = null, $additionalData = [])
    {
        $this->userId = $userId;
        $this->action = $action; // 'login' or 'logout'
        $this->sessionId = $sessionId;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->additionalData = $additionalData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $user = User::find($this->userId);
            if (!$user) {
                Log::warning('UserAccessLogJob: User not found', ['user_id' => $this->userId]);
                return;
            }

            // Parse user agent
            $agent = new Agent();
            $agent->setUserAgent($this->userAgent);

            // Get location data from IP
            $location = $this->getLocationFromIP($this->ipAddress);

            // Get MAC address (this is limited in web applications)
            $macAddress = $this->getMacAddress();

            // Prepare log data
            $logData = [
                'user_id' => $this->userId,
                'session_id' => $this->sessionId,
                'action' => $this->action,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
                'browser' => $agent->browser() . ' ' . $agent->version($agent->browser()),
                'platform' => $agent->platform() . ' ' . $agent->version($agent->platform()),
                'device' => $this->getDeviceType($agent),
                'country' => $location['country'] ?? null,
                'city' => $location['city'] ?? null,
                'region' => $location['state_name'] ?? null,
                'latitude' => $location['lat'] ?? null,
                'longitude' => $location['lon'] ?? null,
                'timezone' => $location['timezone'] ?? null,
                'isp' => $location['isp'] ?? null,
                'mac_address' => $macAddress,
                'additional_data' => $this->additionalData,
            ];

            // Set login/logout timestamp
            if ($this->action === 'login') {
                $logData['login_at'] = now();
            } elseif ($this->action === 'logout') {
                $logData['logout_at'] = now();

                // Calculate session duration if we can find the login record
                $loginLog = UserAccessLog::where('user_id', $this->userId)
                    ->where('session_id', $this->sessionId)
                    ->where('action', 'login')
                    ->whereNotNull('login_at')
                    ->orderBy('login_at', 'desc')
                    ->first();

                if ($loginLog) {
                    $sessionDuration = now()->diffInSeconds($loginLog->login_at);
                    $logData['session_duration'] = $sessionDuration;
                }
            }

            // Create the access log
            UserAccessLog::create($logData);

            Log::info('User access logged successfully', [
                'user_id' => $this->userId,
                'action' => $this->action,
                'ip_address' => $this->ipAddress,
                'location' => ($location['city'] ?? 'Unknown') . ', ' . ($location['country'] ?? 'Unknown')
            ]);
        } catch (\Exception $e) {
            Log::error('UserAccessLogJob failed: ' . $e->getMessage(), [
                'user_id' => $this->userId,
                'action' => $this->action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            ErrorDispatcher::dispatch($e);
            throw $e;
        }
    }

    /**
     * Get location information from IP address.
     */
    private function getLocationFromIP($ipAddress)
    {
        try {
            // For local/private IPs, try to get the real public IP
            if (
                !$ipAddress ||
                filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            ) {
                // Try to get real public IP if we're behind a local network
                $realIp = $this->getRealPublicIP();
                if ($realIp && filter_var($realIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ipAddress = $realIp;
                    Log::info("Using real public IP: {$realIp} instead of local IP");
                } else {
                    return [
                        'country' => 'Local',
                        'city' => 'Local',
                        'state_name' => 'Local',
                        'lat' => null,
                        'lon' => null,
                        'timezone' => config('app.timezone'),
                        'isp' => 'Local Network'
                    ];
                }
            }
            // Fallback to free IP geolocation service
            return $this->getLocationFromExternalAPI($ipAddress);
        } catch (\Exception $e) {
            Log::warning('Failed to get location from IP: ' . $e->getMessage(), [
                'ip_address' => $ipAddress
            ]);
            return [];
        }
    }

    /**
     * Get location from external API as fallback.
     */
    private function getLocationFromExternalAPI($ipAddress)
    {
        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get("http://ip-api.com/json/{$ipAddress}");
            $data = json_decode($response->getBody(), true);

            if ($data && $data['status'] === 'success') {
                return [
                    'country' => $data['country'] ?? null,
                    'city' => $data['city'] ?? null,
                    'state_name' => $data['regionName'] ?? null,
                    'lat' => $data['lat'] ?? null,
                    'lon' => $data['lon'] ?? null,
                    'timezone' => $data['timezone'] ?? null,
                    'isp' => $data['isp'] ?? null
                ];
            }
        } catch (\Exception $e) {
            Log::debug('External IP API failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Get device type from user agent.
     */
    private function getDeviceType(Agent $agent)
    {
        if ($agent->isMobile()) {
            return 'Mobile - ' . ($agent->device() ?: 'Unknown');
        } elseif ($agent->isTablet()) {
            return 'Tablet - ' . ($agent->device() ?: 'Unknown');
        } elseif ($agent->isDesktop()) {
            return 'Desktop';
        }

        return 'Unknown';
    }

    /**
     * Get MAC address (limited in web applications).
     * Note: This is not possible to get reliably in web applications
     * due to security restrictions. This is a placeholder.
     */
    private function getMacAddress()
    {
        return null;
    }

    /**
     * Try to get the real public IP address when behind local network/proxy.
     */
    private function getRealPublicIP()
    {
        try {

            $services = [
                'https://api.ipify.org',
                'https://ipinfo.io/ip',
                'https://icanhazip.com',
                'https://ident.me'
            ];

            foreach ($services as $service) {
                try {
                    $client = new Client(['timeout' => 3]);
                    $response = $client->get($service);
                    $ip = trim($response->getBody()->getContents());

                    // Validate the IP
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        //Log::info("Got real public IP from {$service}: {$ip}");
                        return $ip;
                    }
                } catch (\Exception $e) {
                    Log::debug("Failed to get IP from {$service}: " . $e->getMessage());
                    continue;
                }
            }
        } catch (\Exception $e) {
            Log::debug('Failed to get real public IP: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('UserAccessLogJob failed permanently', [
            'user_id' => $this->userId,
            'action' => $this->action,
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage()
        ]);
    }
}
