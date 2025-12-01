<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Admin - System Management")]
class AdminSystemController extends Controller
{
    /**
     * Get system health status
     */
    #[OA\Get(
        path: "/admin/system/health",
        summary: "Get system health status (Admin only)",
        description: "Check the health status of database, cache, and queue systems",
        tags: ["Admin - System Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "System is healthy"),
            new OA\Response(response: 503, description: "System is degraded"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function health(): JsonResponse
    {
        $health = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'timestamp' => now()->toISOString(),
        ];

        $allHealthy = collect($health)
            ->except('timestamp')
            ->every(function ($check) {
                return is_array($check) && ($check['status'] ?? '') === 'healthy';
            });

        return response()->json([
            'success' => true,
            'data' => $health,
            'overall_status' => $allHealthy ? 'healthy' : 'degraded',
        ], $allHealthy ? 200 : 503);
    }

    /**
     * Get system statistics
     */
    #[OA\Get(
        path: "/admin/system/stats",
        summary: "Get system statistics (Admin only)",
        description: "Get system statistics including database table counts, cache status, and queue status",
        tags: ["Admin - System Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "System statistics retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function stats(): JsonResponse
    {
        $stats = [
            'database' => [
                'connection' => config('database.default'),
                'tables' => $this->getTableCounts(),
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'status' => $this->checkCache()['status'],
            ],
            'queue' => [
                'connection' => config('queue.default'),
                'status' => $this->checkQueue()['status'],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get recent error logs
     */
    #[OA\Get(
        path: "/admin/system/logs",
        summary: "Get recent error logs (Admin only)",
        description: "Get the last 50 lines from the Laravel error log",
        tags: ["Admin - System Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Logs retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function logs(): JsonResponse
    {
        // Read last 50 lines of Laravel log
        $logPath = storage_path('logs/laravel.log');
        $logs = [];

        if (file_exists($logPath)) {
            $lines = file($logPath);
            $logs = array_slice($lines, -50);
            $logs = array_reverse($logs); // Most recent first
        }

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $logs,
                'count' => count($logs),
            ],
        ]);
    }

    /**
     * Get queue statistics
     */
    #[OA\Get(
        path: "/admin/system/queue",
        summary: "Get queue statistics (Admin only)",
        description: "Get queue statistics including pending and failed job counts",
        tags: ["Admin - System Management"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Queue statistics retrieved successfully"),
            new OA\Response(response: 403, description: "Forbidden - Admin access required"),
        ]
    )]
    public function queue(): JsonResponse
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            $pending = 0;
            $failed = 0;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => $pending,
                'failed' => $failed,
                'status' => $pending > 100 ? 'high' : ($pending > 0 ? 'normal' : 'empty'),
            ],
        ]);
    }

    /**
     * Check database connection
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connection
     */
    protected function checkCache(): array
    {
        try {
            $driver = config('cache.default');
            
            if ($driver === 'redis') {
                Redis::connection()->ping();
            }
            
            // Test cache write/read
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value === 'test') {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache is working',
                    'driver' => $driver,
                ];
            }

            return [
                'status' => 'unhealthy',
                'message' => 'Cache read/write test failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue connection
     */
    protected function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            
            if ($connection === 'redis') {
                Redis::connection()->ping();
            }

            return [
                'status' => 'healthy',
                'message' => 'Queue connection successful',
                'driver' => $connection,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get table counts
     */
    protected function getTableCounts(): array
    {
        try {
            $tables = [
                'users' => DB::table('users')->count(),
                'properties' => DB::table('properties')->count(),
                'messages' => DB::table('messages')->count(),
                'conversations' => DB::table('conversations')->count(),
                'analytics' => DB::table('analytics')->count(),
                'subscriptions' => DB::table('subscriptions')->count(),
            ];

            return $tables;
        } catch (\Exception $e) {
            return [];
        }
    }
}
