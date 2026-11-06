<?php

// "admin:admin,staff:teacher" -> ['admin' => ['admin'], 'staff' => ['teacher']]
$roleMap = [];

foreach (explode(',', (string) env('PORTAL_ROLE_MAP', 'admin:admin,staff:teacher')) as $pair) {
    [$portalRole, $appRole] = array_pad(array_map('trim', explode(':', $pair, 2)), 2, '');

    if ($portalRole !== '' && $appRole !== '') {
        $roleMap[mb_strtolower($portalRole)][] = $appRole;
    }
}

// A relative path is taken from the project root, e.g. storage/certs/office-ca.pem.
$caFile = trim((string) env('PORTAL_CA_FILE', ''));

return [

    /*
    |--------------------------------------------------------------------------
    | Portal login (KU All Login through ENG Main)
    |--------------------------------------------------------------------------
    |
    | ENG Main redirects to the web app with an exchangeToken and an
    | intranetToken; POST /api/auth/portal-login checks them here and issues
    | this API's own JWT. The portal tokens never reach the browser again.
    |
    */

    // ENG Main API: /external/get-role and /external/exchange-token.
    'main_api_url' => rtrim((string) env('PORTAL_MAIN_API_URL', 'https://office.eng.kps.ku.ac.th/eng-main/api'), '/'),

    'system_id' => (string) env('PORTAL_SYSTEM_ID', '33'),

    // ENG Main role name (compared case-insensitively) => roles of this app.
    'role_map' => $roleMap,

    // std-eng-auth-service: /api/verify.php checks the university SSO session.
    'org_auth_api_url' => rtrim((string) env('ORG_AUTH_API_URL', 'https://eng.kps.ku.ac.th/std-eng-auth-service'), '/'),

    // A CA bundle is preferred over turning verification off.
    'verify_ssl' => filter_var(env('PORTAL_VERIFY_SSL', true), FILTER_VALIDATE_BOOL),
    'ca_file' => $caFile === '' ? null : (str_starts_with($caFile, '/') ? $caFile : base_path($caFile)),

    'timeout' => (int) env('PORTAL_TIMEOUT', 10),

    // How often a request re-checks get-role and verify.php for its session.
    'recheck_seconds' => (int) env('PORTAL_RECHECK_SECONDS', 120),

    // Upper bound of a login; the intranetToken's exp may end it sooner.
    'session_hours' => 8,

];
