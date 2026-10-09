<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Health check (GET /api/health)
    |--------------------------------------------------------------------------
    |
    | Without a token the endpoint only returns ok/failed per check. With
    | ?token=<token> (or an X-Health-Token header) it also returns counts,
    | error messages and the container states from status_file. The same token
    | opens nginx's /_status. Empty token = details are never shown.
    | Generate with: openssl rand -hex 32
    |
    */

    'token' => env('HEALTH_TOKEN'),

    // Checked with a GET when set, e.g. http://frontend/ inside docker compose.
    'frontend_url' => env('HEALTH_FRONTEND_URL'),

    // A queue worker that has not looped for this many seconds counts as down.
    // Must exceed the longest job: the worker does not loop while a job runs.
    'queue_worker_max_silence' => (int) env('HEALTH_QUEUE_WORKER_MAX_SILENCE', 180),

    // Written by docker/php/status.php in each container.
    'status_file' => env('HEALTH_STATUS_FILE', storage_path('app/status/status.json')),

];
