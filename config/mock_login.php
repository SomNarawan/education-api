<?php

$frontendUrls = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env(
        'FRONTEND_URL',
        'https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd'
    ))
)));

return [

    /*
    |--------------------------------------------------------------------------
    | Mock login
    |--------------------------------------------------------------------------
    |
    | Lets the API mint its own JWT (/api/mock-login -> MockLoginController)
    | for local development without the portal. Mock tokens carry no session
    | id, so AuthenticateJwt accepts them only while this is on.
    |
    | Production keeps it off: staff sign in through the portal there
    | (POST /api/auth/portal-login). Turning it on in production is the
    | fallback when the portal is down, and lets anyone who can reach the API
    | sign in as admin, so turn it off again once the portal is back.
    |
    */

    'enabled' => filter_var(env('MOCK_LOGIN_ENABLED', false), FILTER_VALIDATE_BOOL),

    'frontend_url' => trim((string) env(
        'MOCK_LOGIN_FRONTEND_URL',
        $frontendUrls[0] ?? 'https://office.eng.kps.ku.ac.th/kukps-eng-education-ssd'
    )),

    'student_frontend_url' => trim((string) env(
        'MOCK_LOGIN_STUDENT_FRONTEND_URL',
        $frontendUrls[1] ?? $frontendUrls[0] ?? 'http://localhost:3002'
    )),

    /*
    |--------------------------------------------------------------------------
    | Dual-role system teachers
    |--------------------------------------------------------------------------
    |
    | nontri_id list (comma separated in .env) that should always be issued
    | both "teacher" and "admin" roles when logging in through mock-login.
    |
    */

    'admin_nontri_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MOCK_LOGIN_ADMIN_NONTRI_IDS', ''))
    ))),

];
