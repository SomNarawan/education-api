<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    /**
     * API: GET /api/health[?token=<HEALTH_TOKEN>]
     *
     * 200 when every check passes, 503 otherwise.
     */
    public function __invoke(Request $request, HealthCheckService $health): JsonResponse
    {
        $token = $request->query('token') ?? $request->header('X-Health-Token');
        $report = $health->report($health->isAuthorized(is_string($token) ? $token : null));

        return response()->json(
            $report,
            $report['status'] === 'ok' ? 200 : 503,
            ['Cache-Control' => 'no-store'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }
}
