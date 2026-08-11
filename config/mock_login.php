<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mock login
    |--------------------------------------------------------------------------
    |
    | Lets the API mint its own JWT (routes/web.php -> MockLoginController) so
    | the app can be tested end-to-end without the real SSO. Must stay off in
    | production and be removed once the real SSO integration is verified.
    |
    */

    'enabled' => filter_var(env('MOCK_LOGIN_ENABLED', false), FILTER_VALIDATE_BOOL),

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    /*
    |--------------------------------------------------------------------------
    | Dual-role teachers
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
