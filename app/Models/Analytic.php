<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'url',
        'page_title',
        'referrer',
        'ip_address',
        'user_agent',
        'device',
        'browser',
        'platform',
        'country',
        'city',
        'event_data',
    ];

    protected $casts = [
        'event_data' => 'array',
    ];

    // RELATIONSHIPS
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // SCOPES
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }

    public function scopeByPage($query, $url)
    {
        return $query->where('url', $url);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCountry($query, $country)
    {
        return $query->where('country', $country);
    }

    public function scopeByDevice($query, $device)
    {
        return $query->where('device', $device);
    }

    // HELPERS
    public static function trackPageView($request, $user = null, $pageTitle = null)
    {
        $userAgent = $request->userAgent();
        $ip = $request->ip();
        
        $analytic = self::create([
            'session_id' => session()->getId(),
            'user_id' => $user ? $user->id : null,
            'url' => $request->fullUrl(),
            'page_title' => $pageTitle,
            'referrer' => $request->header('referer'),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'device' => self::detectDevice($userAgent),
            'browser' => self::detectBrowser($userAgent),
            'platform' => self::detectPlatform($userAgent),
            'country' => self::getCountryFromIP($ip),
            'city' => self::getCityFromIP($ip),
        ]);
        
        return $analytic;
    }

    public static function trackEvent($eventName, $eventData = [], $request = null, $user = null)
    {
        $analytic = self::create([
            'session_id' => session()->getId(),
            'user_id' => $user ? $user->id : null,
            'url' => $request ? $request->fullUrl() : url()->current(),
            'event_data' => array_merge(['event' => $eventName], $eventData),
        ]);
        
        return $analytic;
    }

    private static function detectDevice($userAgent)
    {
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $userAgent)) {
            return 'tablet';
        }
        
        if (preg_match('/Mobile|iPhone|Android|BlackBerry|IEMobile|Opera Mini/i', $userAgent)) {
            return 'mobile';
        }
        
        return 'desktop';
    }

    private static function detectBrowser($userAgent)
    {
        $browsers = [
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'Opera' => 'Opera|OPR',
            'Edge' => 'Edge|Edg',
            'IE' => 'MSIE|Trident',
        ];
        
        foreach ($browsers as $browser => $pattern) {
            if (preg_match("/{$pattern}/i", $userAgent)) {
                return $browser;
            }
        }
        
        return 'Unknown';
    }

    private static function detectPlatform($userAgent)
    {
        $platforms = [
            'Windows' => 'Windows',
            'Mac' => 'Macintosh|Mac OS',
            'Linux' => 'Linux',
            'Android' => 'Android',
            'iOS' => 'iPhone|iPad|iPod',
        ];
        
        foreach ($platforms as $platform => $pattern) {
            if (preg_match("/{$pattern}/i", $userAgent)) {
                return $platform;
            }
        }
        
        return 'Unknown';
    }

    private static function getCountryFromIP($ip)
    {
        // In production, use a service like ipinfo.io or maxmind
        // For now, return unknown
        return 'ID'; // Default to Indonesia
    }

    private static function getCityFromIP($ip)
    {
        // In production, use a service like ipinfo.io or maxmind
        // For now, return unknown
        return 'Unknown';
    }

    public static function getStats($period = 'today')
    {
        $query = self::query();
        
        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->thisWeek();
                break;
            case 'month':
                $query->thisMonth();
                break;
        }
        
        $total = $query->count();
        $unique = $query->distinct('session_id')->count('session_id');
        $returning = $total - $unique;
        
        return [
            'total_visits' => $total,
            'unique_visitors' => $unique,
            'returning_visitors' => $returning,
            'bounce_rate' => $total > 0 ? round(($returning / $total) * 100, 2) : 0,
        ];
    }

    public static function getTopPages($limit = 10, $period = 'month')
    {
        $query = self::query();
        
        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->thisWeek();
                break;
            case 'month':
                $query->thisMonth();
                break;
        }
        
        return $query->select('url', 'page_title')
                     ->selectRaw('COUNT(*) as views')
                     ->selectRaw('COUNT(DISTINCT session_id) as unique_visitors')
                     ->groupBy('url', 'page_title')
                     ->orderBy('views', 'desc')
                     ->limit($limit)
                     ->get();
    }

    public static function getTopReferrers($limit = 10, $period = 'month')
    {
        $query = self::query();
        
        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->thisWeek();
                break;
            case 'month':
                $query->thisMonth();
                break;
        }
        
        return $query->select('referrer')
                     ->selectRaw('COUNT(*) as visits')
                     ->whereNotNull('referrer')
                     ->where('referrer', '!=', '')
                     ->groupBy('referrer')
                     ->orderBy('visits', 'desc')
                     ->limit($limit)
                     ->get();
    }

    public static function getDeviceStats($period = 'month')
    {
        $query = self::query();
        
        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->thisWeek();
                break;
            case 'month':
                $query->thisMonth();
                break;
        }
        
        return $query->select('device')
                     ->selectRaw('COUNT(*) as count')
                     ->selectRaw('ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM analytics), 2) as percentage')
                     ->groupBy('device')
                     ->orderBy('count', 'desc')
                     ->get();
    }

    public static function getBrowserStats($period = 'month')
    {
        $query = self::query();
        
        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->thisWeek();
                break;
            case 'month':
                $query->thisMonth();
                break;
        }
        
        return $query->select('browser')
                     ->selectRaw('COUNT(*) as count')
                     ->selectRaw('ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM analytics), 2) as percentage')
                     ->groupBy('browser')
                     ->orderBy('count', 'desc')
                     ->get();
    }

    public static function getCountryStats($period = 'month')
    {
        $query = self::query();
        
        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->thisWeek();
                break;
            case 'month':
                $query->thisMonth();
                break;
        }
        
        return $query->select('country')
                     ->selectRaw('COUNT(*) as count')
                     ->selectRaw('ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM analytics), 2) as percentage')
                     ->whereNotNull('country')
                     ->groupBy('country')
                     ->orderBy('count', 'desc')
                     ->limit(10)
                     ->get();
    }

    public static function getConversionRate($eventName, $period = 'month')
    {
        $totalVisits = self::where('created_at', '>=', now()->subMonth())->count();
        $conversions = self::where('created_at', '>=', now()->subMonth())
                            ->whereJsonContains('event_data->event', $eventName)
                            ->count();
        
        if ($totalVisits === 0) {
            return 0;
        }
        
        return round(($conversions / $totalVisits) * 100, 2);
    }
}