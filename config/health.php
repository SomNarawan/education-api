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

];
