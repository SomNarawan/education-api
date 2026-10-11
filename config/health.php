<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Health check (GET /api/health)
    |--------------------------------------------------------------------------
    |
    | Without a token the endpoint only returns ok/failed per check. With
    | ?token=<token> (or an X-Health-Token header) it also returns counts and
    | error messages. Empty token = details are never shown.
    | Generate with: openssl rand -hex 32
    |
    */

    'token' => env('HEALTH_TOKEN'),

    // Checked with a GET when set, e.g. http://frontend/ inside docker compose.
    'frontend_url' => env('HEALTH_FRONTEND_URL'),

    // A queue worker that has not looped for this many seconds counts as down.
    // Must exceed the longest job: the worker does not loop while a job runs.
    'queue_worker_max_silence' => (int) env('HEALTH_QUEUE_WORKER_MAX_SILENCE', 180),

    // External services (CMIS, Personnel API) are non-critical: when they fail
    // the status is "degraded" (still 200), not "down" (503). Their results are
    // cached this many seconds so frequent probes don't load those services;
    // 0 = check on every request.
    'external_cache_seconds' => (int) env('HEALTH_EXTERNAL_CACHE_SECONDS', 60),
    'external_timeout' => (int) env('HEALTH_EXTERNAL_TIMEOUT', 3),

];
