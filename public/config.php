<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — config.php
// Supabase Project : ehswbdmizytpahosqkuj  (ap-southeast-2)
// Hostinger PHP 8.2+ / Apache
// ------------------------------------------------------------------------------
// SECURITY BOUNDARIES:
//   • Only 'admin' role profiles may ever activate impersonation.
//   • The true admin UUID is locked in $_SESSION['real_admin_id'] the moment
//     impersonation begins and CANNOT be overwritten by any subsequent request.
//   • All user-controlled inputs that touch session state are sanitised through
//     sanitise_uuid() before being written to session.
//   • Credentials (ANON_KEY, SERVICE_ROLE_KEY) are never echoed to the browser
//     except ANON_KEY (read-only, public-safe) injected into window.BOUNTY_CONFIG.
//   • Service-role cURL calls bypass PostgREST RLS — they must only be triggered
//     from server-side admin operations, never from un-authenticated paths.
// ==============================================================================

// ── 1. SESSION BOOT ──────────────────────────────────────────────────────────
// Configure all cookie and session hardening flags BEFORE session_start().
// SameSite=Strict prevents CSRF; HttpOnly blocks XSS cookie theft; Secure
// enforces HTTPS-only on Hostinger production. Adjust 'secure' to 0 only for
// local development over plain HTTP.

if (session_status() === PHP_SESSION_NONE) {
    // Block JS access to the session cookie (mitigates XSS session hijack).
    ini_set('session.cookie_httponly',   '1');
    // Reject any session ID that arrives in a URL query string.
    ini_set('session.use_only_cookies', '1');
    // Only accept session IDs that were created by this server.
    ini_set('session.use_strict_mode',  '1');
    // Regenerate the session ID periodically to prevent fixation attacks.
    // Actual regeneration is handled at login / privilege change points.
    ini_set('session.cookie_samesite',  'Strict');
    // Set to '1' when served over HTTPS (Hostinger production).
    ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');

    session_start();
}

// Send security-relevant HTTP response headers on every page load.
// These are advisory headers read by browsers; they do NOT replace server-side
// access control but provide an important defence-in-depth layer.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');


// ── 2. ENVIRONMENT LOADER ────────────────────────────────────────────────────
// Reads KEY=VALUE pairs from a .env file without relying on Composer or any
// external library. Values are stripped of surrounding quotes and whitespace.
// Keys already present in $_SERVER or $_ENV (e.g. set by Apache/nginx
// SetEnv directives) are left untouched so that server-level config wins.

/**
 * Load a .env file into the process environment.
 *
 * @param string $path Absolute path to the .env file.
 */
function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comment lines and empty lines.
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // Only process lines that contain an '=' assignment.
        if (!str_contains($line, '=')) {
            continue;
        }

        [$raw_key, $raw_val] = explode('=', $line, 2);
        $key = trim($raw_key);
        // Strip surrounding quotes (single or double) and whitespace.
        $val = trim(trim($raw_val), "\"'");

        // Do NOT overwrite values already present in the environment.
        if (getenv($key) === false) {
            putenv("{$key}={$val}");
            $_ENV[$key]    = $val;
            $_SERVER[$key] = $val;
        }
    }
}

// Attempt to load .env from public/ first, then from the project root.
load_env_file(__DIR__ . '/.env');
load_env_file(dirname(__DIR__) . '/.env');


// ── 3. PLATFORM CONSTANTS ────────────────────────────────────────────────────
// All constants are defined once; attempting to redefine them in any included
// file will cause a PHP notice (harmless but visible) — useful for detecting
// accidental double-inclusion.

define('SUPABASE_PROJECT_ID',   'ehswbdmizytpahosqkuj');
define('SUPABASE_URL',          getenv('SUPABASE_URL')           ?: 'https://ehswbdmizytpahosqkuj.supabase.co');
define('SUPABASE_ANON_KEY',     getenv('SUPABASE_ANON_KEY')      ?: '');
// WARNING: SERVICE_ROLE_KEY bypasses all RLS policies.
// It must NEVER be sent to the browser or logged in any access log.
define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: '');
define('COMMUNITY_NAME',        getenv('COMMUNITY_NAME')          ?: 'Bounty Community');
// Dynamic BASE_URL resolver: respects environment or auto-detects /public prefix for Hostinger deployment
function resolve_public_base_url(): string
{
    $env = getenv('BASE_URL');
    if ($env !== false && $env !== '') {
        return rtrim($env, '/');
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (str_contains($script, '/public/')) {
        $pos = strpos($script, '/public/');
        return rtrim(substr($script, 0, $pos + 7), '/');
    }

    $req = $_SERVER['REQUEST_URI'] ?? '';
    if (str_contains($req, '/public/')) {
        $pos = strpos($req, '/public/');
        return rtrim(substr($req, 0, $pos + 7), '/');
    }

    return '';
}

define('BASE_URL', resolve_public_base_url());

/**
 * Universal BDT Currency Formatter.
 * Formats numeric amounts into Bangladeshi Taka currency format (e.g. ৳50,000.00).
 *
 * @param float|int|string $amount Numeric or string amount.
 * @return string Formatted BDT currency string with '৳' prefix.
 */
function format_bdt($amount) {
    return '৳' . number_format((float)$amount, 2);
}

// Roles that are valid platform role values (single source of truth).
define('VALID_ROLES', ['admin', 'recruiter', 'hunter', 'mod', 'support']);

// Roles that an admin is permitted to teleport INTO via role-based impersonation
// (teleport_to_role). Admin cannot teleport into 'admin' (prevents confusion).
define('ADMIN_TELEPORT_ROLES', ['mod', 'support']);


// ── 4. SUPABASE CONNECTIVITY CHECK ──────────────────────────────────────────

/**
 * Returns true when a real Supabase URL and anon key are configured.
 * When false, the application falls back to in-session mock persistence so
 * that every UI feature works identically during local development.
 */
function is_supabase_configured(): bool
{
    return !empty(SUPABASE_URL)
        && !empty(SUPABASE_ANON_KEY)
        && str_contains(SUPABASE_URL, '.supabase.co');
}


// ── 5. UUID SANITISER ────────────────────────────────────────────────────────

/**
 * Validate and normalise a UUID-v4 string.
 *
 * SECURITY: All UUIDs received from user-controlled sources (POST body, GET
 * params, session values set during a previous request) MUST pass through this
 * function before being used in SQL, Supabase API paths, or session writes.
 * Returns null on failure so callers can reject invalid input cleanly.
 *
 * @param  mixed  $value Raw input to validate.
 * @return string|null   Lowercase UUID-v4 string, or null if invalid.
 */
function sanitise_uuid(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $v = strtolower(trim($value));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $v)) {
        return null;
    }
    return $v;
}

/**
 * Validate a role string against the platform's allowed role set.
 *
 * @param  mixed  $value Raw input.
 * @return string|null   Validated role string or null.
 */
function sanitise_role(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $v = strtolower(trim($value));
    return in_array($v, VALID_ROLES, true) ? $v : null;
}


// ── 6. SUPABASE REST/RPC CLIENT ──────────────────────────────────────────────
// Pure cURL implementation — no Composer, no external dependencies.
// Compatible with every Hostinger shared/private plan that has cURL+OpenSSL.

/**
 * Execute a raw REST request against the Supabase PostgREST API.
 *
 * SECURITY NOTE: When $use_service_role = true this call bypasses ALL Row Level
 * Security policies. Only call with true from server-side admin-verified paths.
 * Never expose the service-role key to the client.
 *
 * @param  string      $endpoint       Relative endpoint, e.g. 'rest/v1/profiles'.
 * @param  string      $method         HTTP verb (GET, POST, PATCH, DELETE).
 * @param  array|null  $data           JSON-serialisable body payload.
 * @param  bool        $use_service_role  Use service-role key (bypasses RLS).
 * @param  array       $extra_headers  Additional HTTP headers to merge.
 * @return array{status_code:int, data:mixed, error?:string}
 */
function supabase_request(
    string $endpoint,
    string $method         = 'GET',
    ?array $data           = null,
    bool   $use_service_role = false,
    array  $extra_headers  = []
): array {
    if (!is_supabase_configured()) {
        return ['status_code' => 0, 'data' => null, 'error' => 'Supabase not configured — running in local mock mode.'];
    }

    // Build the full endpoint URL.
    $url = rtrim(SUPABASE_URL, '/') . '/' . ltrim($endpoint, '/');

    // Select key: service-role bypasses RLS; use it only when explicitly needed
    // and when the key is actually set in the environment.
    $api_key = ($use_service_role && !empty(SUPABASE_SERVICE_ROLE_KEY))
        ? SUPABASE_SERVICE_ROLE_KEY
        : SUPABASE_ANON_KEY;

    $headers = array_merge([
        'Content-Type: application/json',
        'apikey: ' . $api_key,
        'Authorization: Bearer ' . $api_key,
        'Prefer: return=representation',
    ], $extra_headers);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        // Always verify the Supabase SSL certificate.
        // Never set to false on production — defeats the point of HTTPS.
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
    }

    $raw      = curl_exec($ch);
    $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($curl_err !== '') {
        error_log('[Bounty][Supabase] cURL error on ' . $endpoint . ': ' . $curl_err);
        return ['status_code' => 0, 'data' => null, 'error' => 'Network error: ' . $curl_err];
    }

    $decoded = json_decode($raw, true);
    return ['status_code' => $http, 'data' => $decoded];
}

/**
 * Convenience wrapper for Supabase Postgres RPC functions.
 *
 * @param  string  $fn_name         Stored-procedure name.
 * @param  array   $params          Named parameters for the RPC.
 * @param  bool    $use_service_role Use service-role key.
 * @return array                    Same shape as supabase_request().
 */
function supabase_rpc(string $fn_name, array $params = [], bool $use_service_role = false): array
{
    return supabase_request('rest/v1/rpc/' . $fn_name, 'POST', $params, $use_service_role);
}


// ── 7. PROFILE FETCHER ───────────────────────────────────────────────────────

/**
 * Fetch a single profile row from Supabase by UUID.
 *
 * Uses the service-role key so that the query is not restricted by RLS (admin
 * operations need to be able to look up any user profile).
 *
 * Returns null if the profile does not exist or if Supabase is not configured.
 *
 * @param  string      $uuid Validated UUID-v4.
 * @return array|null        Associative profile row or null.
 */
function fetch_profile_by_uuid(string $uuid): ?array
{
    if (!is_supabase_configured()) {
        // In mock mode, search the predefined persona table by UUID.
        foreach (get_predefined_personas() as $persona) {
            if ($persona['id'] === $uuid) {
                return $persona;
            }
        }
        return null;
    }

    $res = supabase_request(
        'rest/v1/profiles?id=eq.' . urlencode($uuid) . '&limit=1',
        'GET',
        null,
        true   // Use service-role to bypass RLS for admin lookup.
    );

    if (
        $res['status_code'] === 200
        && is_array($res['data'])
        && isset($res['data'][0])
    ) {
        return $res['data'][0];
    }

    return null;
}


// ── 8. ACTIVE USER CONTEXT & IMPERSONATION ENGINE ───────────────────────────
//
// DESIGN:
//   The "real" authenticated admin is tracked in $_SESSION['real_admin_id'].
//   While impersonation is active, $_SESSION['impersonated_user_id'] holds the
//   target's UUID and $_SESSION['impersonated_role'] holds an optional role
//   override (used by teleport_to_role).
//
// PRIVILEGE-ESCALATION PREVENTION:
//   1. The real admin's role is fetched from Supabase (or mock DB) every time
//      get_active_user_context() is called. It is never taken from session state.
//   2. If the profile whose UUID is in real_admin_id does NOT have role='admin'
//      in the database, impersonation is aborted, the session keys are cleared,
//      and the function returns the raw profile without any impersonation.
//   3. Non-admin users can never set impersonated_user_id because:
//      a. admin_sneak.php validates the real_admin_id profile role before
//         writing these keys.
//      b. Here, we double-validate the real admin role on every request.

/**
 * Resolve and return the active user context for the current request.
 *
 * The returned array ALWAYS contains:
 *   id            - UUID being used for this request's identity
 *   role          - Effective role string
 *   display_name  - Human-readable name
 *   handle        - @-handle
 *   email         - Email address
 *   avatar_url    - Profile image URL
 *   wallet_balance- Numeric wallet balance
 *   rating        - Numeric rating
 *   reputation_score - Integer score
 *   is_impersonating - bool: true when the admin is viewing as another user
 *   real_admin_id    - UUID of the true admin (null when not impersonating)
 *
 * @return array User context associative array.
 */
function get_active_user_context(): array
{
    // ------------------------------------------------------------------
    // A. Resolve the base authenticated user (real_admin_id when set,
    //    otherwise fall back to active_persona_role for the mock engine).
    // ------------------------------------------------------------------

    $real_admin_uuid = isset($_SESSION['real_admin_id'])
        ? sanitise_uuid($_SESSION['real_admin_id'])
        : null;

    $is_impersonating = false;

    // If a real_admin_id is stored in session, we are either currently
    // impersonating or we have just entered impersonation mode.
    if ($real_admin_uuid !== null) {

        // -- SECURITY CHECK: Re-verify the real admin's role from the DB --
        // We NEVER trust a role stored in the session; we always re-fetch it.
        $admin_profile = fetch_profile_by_uuid($real_admin_uuid);

        if ($admin_profile === null || ($admin_profile['role'] ?? '') !== 'admin') {
            // The UUID stored in session does not map to an active admin profile.
            // This could indicate session tampering or an account role change.
            // Abort ALL impersonation state immediately.
            unset(
                $_SESSION['real_admin_id'],
                $_SESSION['impersonated_user_id'],
                $_SESSION['impersonated_role']
            );
            // Log for audit trail (no sensitive data in the log line).
            error_log('[Bounty][Security] Impersonation aborted — real_admin_id no longer admin. Session cleared.');

            // Fall through and resolve the context from the mock persona engine.
            return get_active_persona();
        }

        // ------------------------------------------------------------------
        // B. A valid admin is confirmed. Check for an impersonation target.
        // ------------------------------------------------------------------

        $impersonated_uuid = isset($_SESSION['impersonated_user_id'])
            ? sanitise_uuid($_SESSION['impersonated_user_id'])
            : null;

        $impersonated_role_override = isset($_SESSION['impersonated_role'])
            ? sanitise_role($_SESSION['impersonated_role'])
            : null;

        if ($impersonated_uuid !== null) {
            // Attempt to load the target user's profile.
            $target_profile = fetch_profile_by_uuid($impersonated_uuid);

            if ($target_profile !== null) {
                $is_impersonating = true;

                // Build context from the target profile, but inject real_admin_id
                // so every caller knows who the actual operator is.
                $context = array_merge($target_profile, [
                    // If a role override was set (teleport_to_role), use it.
                    // Otherwise use the target user's actual database role.
                    'role'             => $impersonated_role_override ?? $target_profile['role'],
                    'is_impersonating' => true,
                    'real_admin_id'    => $real_admin_uuid,
                    'real_admin_name'  => $admin_profile['display_name'] ?? 'Admin',
                ]);

                return $context;
            }

            // Target profile not found — clear the stale impersonation state.
            unset(
                $_SESSION['impersonated_user_id'],
                $_SESSION['impersonated_role']
            );
            error_log('[Bounty][Security] Impersonation target UUID not found; cleared stale session keys.');
        }

        // No impersonation target — return the admin's own profile.
        $context = array_merge($admin_profile, [
            'is_impersonating' => false,
            'real_admin_id'    => null,
        ]);
        return $context;
    }

    // ------------------------------------------------------------------
    // C. No real_admin_id in session — use the mock persona engine.
    //    This path is active during local development or for non-admin users
    //    who have not yet gone through a live auth flow.
    // ------------------------------------------------------------------

    $persona = get_active_persona();
    $persona['is_impersonating'] = false;
    $persona['real_admin_id']    = null;
    return $persona;
}


// ── 9. MOCK PERSONA ENGINE (offline / dev fallback) ─────────────────────────
// When Supabase is not configured or a user is not logged in via Supabase Auth,
// the application resolves identity from these static personas stored in session.

/**
 * Return all predefined test personas indexed by role slug.
 *
 * @return array<string, array>
 */
function get_predefined_personas(): array
{
    return [
        'admin' => [
            'id'               => '11111111-1111-1111-1111-111111111111',
            'role'             => 'admin',
            'role_label'       => 'Founder & Architect',
            'display_name'     => 'Elena Vance',
            'handle'           => 'founder_elena',
            'email'            => 'elena@bounty.community',
            'avatar_url'       => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
            'wallet_balance'   => 50000.00,
            'rating'           => 5.00,
            'reputation_score' => 1000,
            'bio'              => 'Founder & Principal Architect at Bounty Community.',
            'skills'           => ['Architecture', 'PostgreSQL RPC', 'Smart Escrow', 'System Auditing'],
        ],
        'recruiter' => [
            'id'               => '22222222-2222-2222-2222-222222222222',
            'role'             => 'recruiter',
            'role_label'       => 'Lead Recruiter',
            'display_name'     => 'Marcus Sterling',
            'handle'           => 'marcus_hire',
            'email'            => 'marcus@hypergrowth.vc',
            'avatar_url'       => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
            'wallet_balance'   => 12450.00,
            'rating'           => 4.92,
            'reputation_score' => 450,
            'bio'              => 'Talent Lead funding bounties across Web3, AI, and Full-Stack.',
            'skills'           => ['Technical Recruiting', 'ATS Operations', 'Talent Screening', 'Budget Escrow'],
        ],
        'hunter' => [
            'id'               => '33333333-3333-3333-3333-333333333333',
            'role'             => 'hunter',
            'role_label'       => 'Elite Bounty Hunter',
            'display_name'     => 'Alex Chen',
            'handle'           => 'alex_code',
            'email'            => 'alex@buildspace.dev',
            'avatar_url'       => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
            'wallet_balance'   => 3820.00,
            'rating'           => 4.98,
            'reputation_score' => 890,
            'bio'              => 'Elite Bounty Hunter specialized in PHP 8.2, Supabase RPCs, and UI design.',
            'skills'           => ['PHP 8.2', 'Supabase', 'Tailwind CSS', 'PostgreSQL', 'API Security'],
        ],
        'mod' => [
            'id'               => '44444444-4444-4444-4444-444444444444',
            'role'             => 'mod',
            'role_label'       => 'Threat Patrol Mod',
            'display_name'     => 'Sarah Jenkins',
            'handle'           => 'patrol_sarah',
            'email'            => 'mod@bounty.community',
            'avatar_url'       => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80',
            'wallet_balance'   => 1500.00,
            'rating'           => 4.95,
            'reputation_score' => 620,
            'bio'              => 'Threat patrol, anti-spam guardian, and task verification lead.',
            'skills'           => ['Task Verification', 'Dispute Resolution', 'Threat Analysis', 'Policy Enforcement'],
        ],
        'support' => [
            'id'               => '55555555-5555-5555-5555-555555555555',
            'role'             => 'support',
            'role_label'       => 'Support Desk Agent',
            'display_name'     => 'Devon Bailey',
            'handle'           => 'support_desk',
            'email'            => 'support@bounty.community',
            'avatar_url'       => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150&auto=format&fit=crop&q=80',
            'wallet_balance'   => 800.00,
            'rating'           => 4.88,
            'reputation_score' => 380,
            'bio'              => 'Senior Escrow Dispute Specialist & User Success Advocate.',
            'skills'           => ['Escrow Inquiries', 'Mediation', 'Customer Support', 'Payout Tracking'],
        ],
    ];
}

/**
 * Return the active mock persona (session-based role selection).
 *
 * @return array Persona data array.
 */
function get_active_persona(): array
{
    $personas = get_predefined_personas();

    // Default to admin if no valid role is stored.
    if (!isset($_SESSION['active_persona_role']) || !array_key_exists($_SESSION['active_persona_role'], $personas)) {
        $_SESSION['active_persona_role'] = 'admin';
    }

    $role    = $_SESSION['active_persona_role'];
    $persona = $personas[$role];

    // Apply any runtime balance/field overrides (e.g. after an escrow operation).
    if (!empty($_SESSION['persona_overrides'][$role])) {
        $persona = array_merge($persona, $_SESSION['persona_overrides'][$role]);
    }

    return $persona;
}

/**
 * Switch the active mock persona to a different role (dev / demo use only).
 *
 * @param  string     $role_key Role slug to switch to.
 * @return array|false  Persona array on success, false if role is invalid.
 */
function set_active_persona(string $role_key): array|false
{
    $personas = get_predefined_personas();
    if (!array_key_exists($role_key, $personas)) {
        return false;
    }
    $_SESSION['active_persona_role'] = $role_key;
    $_SESSION['user_role'] = $role_key;
    $_SESSION['user_id'] = $personas[$role_key]['id'];
    return $personas[$role_key];
}

/**
 * Apply a wallet delta to the currently active mock persona.
 *
 * @param  float  $delta Positive to credit, negative to debit.
 * @return float  New balance (floored at 0).
 */
function update_active_persona_balance(float $delta): float
{
    $role        = $_SESSION['active_persona_role'] ?? 'admin';
    $persona     = get_active_persona();
    $new_balance = max(0.0, ($persona['wallet_balance'] ?? 0.0) + $delta);

    $_SESSION['persona_overrides'][$role]['wallet_balance'] = $new_balance;
    return $new_balance;
}


// ── 10. MOCK DATABASE INITIALISER ────────────────────────────────────────────
// Bootstraps seed data in $_SESSION so every portal works in offline mode.

function init_mock_db(): void
{
    if (isset($_SESSION['bounty_mock_db'])) {
        return;
    }

    $_SESSION['bounty_mock_db'] = [
        'jobs' => [
            [
                'id'               => 'job-101',
                'creator_id'       => '22222222-2222-2222-2222-222222222222',
                'creator_name'     => 'Marcus Sterling',
                'creator_handle'   => 'marcus_hire',
                'title'            => 'Build Hostinger PHP 8.2 Supabase PostgREST Connector',
                'description'      => 'Develop a high-performance cURL-backed PHP wrapper for Supabase PostgREST with atomic RPC transaction support and zero external composer dependencies.',
                'category'         => 'Backend Architecture',
                'bounty_amount'    => 2500.00,
                'escrow_status'    => 'locked',
                'status'           => 'open',
                'skills_required'  => ['PHP 8.2', 'Supabase', 'PostgreSQL', 'cURL'],
                'deadline'         => date('Y-m-d H:i', strtotime('+5 days')),
                'created_at'       => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'applicants_count' => 14,
            ],
            [
                'id'               => 'job-102',
                'creator_id'       => '22222222-2222-2222-2222-222222222222',
                'creator_name'     => 'Marcus Sterling',
                'creator_handle'   => 'marcus_hire',
                'title'            => 'Tailwind Glassmorphism Dashboard for Bounty Telemetry',
                'description'      => 'Construct a modern dark-mode responsive dashboard utilizing Google Stitch tokens (#0B0F17, #121826, #6366F1, #10B981) with dynamic metric charts.',
                'category'         => 'Frontend UI/UX',
                'bounty_amount'    => 1800.00,
                'escrow_status'    => 'locked',
                'status'           => 'in_review',
                'skills_required'  => ['Tailwind CSS', 'Vanilla JS', 'SVG Charts', 'Glassmorphism'],
                'deadline'         => date('Y-m-d H:i', strtotime('+3 days')),
                'created_at'       => date('Y-m-d H:i:s', strtotime('-1 day')),
                'applicants_count' => 28,
            ],
            [
                'id'               => 'job-103',
                'creator_id'       => '11111111-1111-1111-1111-111111111111',
                'creator_name'     => 'Elena Vance',
                'creator_handle'   => 'founder_elena',
                'title'            => 'Automated Threat Patrol & Spam Detector for Proof Submissions',
                'description'      => 'Implement heuristics and regex detection to flag suspicious GitHub repositories and malicious file upload proof links in the moderation queue.',
                'category'         => 'Cybersecurity',
                'bounty_amount'    => 3200.00,
                'escrow_status'    => 'locked',
                'status'           => 'open',
                'skills_required'  => ['Security', 'Regex', 'PHP', 'Threat Modeling'],
                'deadline'         => date('Y-m-d H:i', strtotime('+7 days')),
                'created_at'       => date('Y-m-d H:i:s', strtotime('-3 days')),
                'applicants_count' => 9,
            ],
        ],

        'applications' => [
            [
                'id'               => 'app-1',
                'job_id'           => 'job-101',
                'candidate_id'     => '33333333-3333-3333-3333-333333333333',
                'candidate_name'   => 'Alex Chen',
                'candidate_handle' => 'alex_code',
                'candidate_avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
                'pitch'            => 'I have built multiple zero-dependency cURL connectors for PostgREST on Hostinger servers with sub-40ms latency.',
                'portfolio_url'    => 'https://alexchen.dev/showcase',
                'github_url'       => 'https://github.com/alexchen/supabase-php-native',
                'status'           => 'shortlisted',
                'match_score'      => 98,
                'created_at'       => date('Y-m-d H:i:s', strtotime('-1 hour')),
            ],
            [
                'id'               => 'app-2',
                'job_id'           => 'job-101',
                'candidate_id'     => 'c-user-88-0000-0000-000000000000',
                'candidate_name'   => 'Dmitri Rostov',
                'candidate_handle' => 'dmitri_tech',
                'candidate_avatar' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150&auto=format&fit=crop&q=80',
                'pitch'            => 'Senior Systems Engineer with 8 years of PHP and PostgreSQL optimisation. Ready to deliver within 48 hours.',
                'portfolio_url'    => 'https://dmitri-eng.io',
                'github_url'       => 'https://github.com/dmitri-rostov/pg-connector',
                'status'           => 'screening',
                'match_score'      => 91,
                'created_at'       => date('Y-m-d H:i:s', strtotime('-3 hours')),
            ],
            [
                'id'               => 'app-3',
                'job_id'           => 'job-101',
                'candidate_id'     => 'c-user-94-0000-0000-000000000000',
                'candidate_name'   => 'Kavita Rao',
                'candidate_handle' => 'kavita_fullstack',
                'candidate_avatar' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=150&auto=format&fit=crop&q=80',
                'pitch'            => 'Full-stack builder familiar with Hostinger shared setups and cURL timeout handling. Clean typed code.',
                'portfolio_url'    => 'https://kavitarao.work',
                'github_url'       => 'https://github.com/kavita-dev',
                'status'           => 'applied',
                'match_score'      => 84,
                'created_at'       => date('Y-m-d H:i:s', strtotime('-4 hours')),
            ],
        ],

        'chat' => [
            [
                'id'           => 'chat-1',
                'sender_name'  => 'Elena Vance',
                'sender_handle' => 'founder_elena',
                'sender_role'  => 'admin',
                'sender_avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
                'message'      => 'Welcome architects! The Bounty Community Engine is live on Hostinger PHP 8.2 with atomic escrow protection.',
                'message_type' => 'chat',
                'created_at'   => date('H:i', strtotime('-15 minutes')),
            ],
            [
                'id'           => 'chat-2',
                'sender_name'  => 'Marcus Sterling',
                'sender_handle' => 'marcus_hire',
                'sender_role'  => 'recruiter',
                'sender_avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
                'message'      => '🚀 New Bounty Broadcast: Build Hostinger PHP 8.2 Supabase PostgREST Connector (৳2,500.00) locked in Escrow!',
                'message_type' => 'job_broadcast',
                'meta'         => ['job_id' => 'job-101', 'bounty' => 2500, 'title' => 'Build Hostinger PHP 8.2 Supabase PostgREST Connector'],
                'created_at'   => date('H:i', strtotime('-10 minutes')),
            ],
            [
                'id'           => 'chat-3',
                'sender_name'  => 'Alex Chen',
                'sender_handle' => 'alex_code',
                'sender_role'  => 'hunter',
                'sender_avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
                'message'      => 'Submitted my application and PR link for the PHP connector. Let\'s get this tested!',
                'message_type' => 'chat',
                'created_at'   => date('H:i', strtotime('-2 minutes')),
            ],
        ],

        'tickets' => [
            [
                'id'          => 'tick-801',
                'user_name'   => 'Alex Chen',
                'user_handle' => 'alex_code',
                'subject'     => 'Escrow Confirmation Query on Task #101',
                'category'    => 'escrow_dispute',
                'priority'    => 'high',
                'status'      => 'open',
                'created_at'  => '12 mins ago',
                'messages'    => [
                    ['sender' => 'Alex Chen',   'is_staff' => false, 'text' => 'Hi Support, recruiter Marcus agreed to release the bounty today. Can you confirm the escrow is ready for disbursement?', 'time' => '12 mins ago'],
                    ['sender' => 'Devon Bailey', 'is_staff' => true,  'text' => 'Hello Alex! Escrow is locked in vault (TransID: ESC-9821). As soon as Marcus approves the final milestone in the candidate accordion, payout is released immediately.', 'time' => '5 mins ago'],
                ],
            ],
            [
                'id'          => 'tick-802',
                'user_name'   => 'User',
                'user_handle' => 'user_test',
                'subject'     => 'Hostinger cURL SSL Certificate Verification Notice',
                'category'    => 'verification',
                'priority'    => 'medium',
                'status'      => 'in_progress',
                'created_at'  => '1 hour ago',
                'messages'    => [
                    ['sender' => 'User',         'is_staff' => false, 'text' => 'Getting a cURL error 60 SSL peer certificate on shared host when pinging external webhook.', 'time' => '1 hour ago'],
                    ['sender' => 'Devon Bailey', 'is_staff' => true,  'text' => 'Updating CA cert bundle in Hostinger php.ini solved this. Marked for QA.', 'time' => '30 mins ago'],
                ],
            ],
        ],

        'verifications' => [
            [
                'id'              => 'ver-501',
                'job_id'          => 'job-101',
                'job_title'       => 'Build Hostinger PHP 8.2 Supabase PostgREST Connector',
                'candidate_name'  => 'Alex Chen',
                'candidate_handle' => 'alex_code',
                'proof_url'       => 'https://github.com/alexchen/supabase-php-native/releases/tag/v1.0.0',
                'proof_notes'     => 'Complete single-file connector with retry logic, unit tests, and Hostinger cURL benchmarks (avg 36ms).',
                'threat_score'    => 2,
                'status'          => 'pending',
                'submitted_at'    => '45 mins ago',
            ],
            [
                'id'              => 'ver-502',
                'job_id'          => 'job-102',
                'job_title'       => 'Tailwind Glassmorphism Dashboard for Bounty Telemetry',
                'candidate_name'  => 'Kavita Rao',
                'candidate_handle' => 'kavita_fullstack',
                'proof_url'       => 'https://kavita-dash-preview.vercel.app',
                'proof_notes'     => 'Preview deployment with all Stitch tokens, interactive canvas metric charts, and responsive sneak bar.',
                'threat_score'    => 0,
                'status'          => 'approved',
                'submitted_at'    => '3 hours ago',
            ],
        ],

        'reactions' => [
            'job-101' => ['like' => 14, 'launch' => 22, 'bounty' => 18, 'fire' => 9],
            'job-102' => ['like' => 8,  'launch' => 11, 'bounty' => 15, 'fire' => 6],
            'job-103' => ['like' => 19, 'launch' => 7,  'bounty' => 24, 'fire' => 12],
            'chat-1'  => ['like' => 15, 'launch' => 18, 'bounty' => 5,  'fire' => 12],
            'chat-2'  => ['like' => 9,  'launch' => 14, 'bounty' => 21, 'fire' => 8],
            'chat-3'  => ['like' => 11, 'launch' => 8,  'bounty' => 4,  'fire' => 7],
        ],

        'user_reactions' => [],

        'job_comments' => [
            'job-101' => [
                [
                    'id'            => 'comm-101-1',
                    'job_id'        => 'job-101',
                    'sender_name'   => 'Alex Chen',
                    'sender_handle' => 'alex_code',
                    'sender_role'   => 'hunter',
                    'sender_avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
                    'message'       => 'Does this connector need to support cURL streaming for large PostgREST RPC responses?',
                    'created_at'    => '1 hour ago',
                ],
                [
                    'id'            => 'comm-101-2',
                    'job_id'        => 'job-101',
                    'sender_name'   => 'Marcus Sterling',
                    'sender_handle' => 'marcus_hire',
                    'sender_role'   => 'recruiter',
                    'sender_avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
                    'message'       => 'Streaming is optional for v1; standard JSON payloads are sufficient. Robust connection timeout and error recovery are high priority!',
                    'created_at'    => '45 mins ago',
                ],
            ],
            'job-102' => [
                [
                    'id'            => 'comm-102-1',
                    'job_id'        => 'job-102',
                    'sender_name'   => 'Dmitri Rostov',
                    'sender_handle' => 'dmitri_tech',
                    'sender_role'   => 'hunter',
                    'sender_avatar' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150&auto=format&fit=crop&q=80',
                    'message'       => 'Are Google Stitch CSS variables already compiled in stitch-tokens.css?',
                    'created_at'    => '2 hours ago',
                ],
                [
                    'id'            => 'comm-102-2',
                    'job_id'        => 'job-102',
                    'sender_name'   => 'Marcus Sterling',
                    'sender_handle' => 'marcus_hire',
                    'sender_role'   => 'recruiter',
                    'sender_avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
                    'message'       => 'Yes! All obsidian cards, glow borders, and font tokens are pre-loaded in the layout header.',
                    'created_at'    => '1 hour ago',
                ],
            ],
            'job-103' => [
                [
                    'id'            => 'comm-103-1',
                    'job_id'        => 'job-103',
                    'sender_name'   => 'Sarah Jenkins',
                    'sender_handle' => 'patrol_sarah',
                    'sender_role'   => 'mod',
                    'sender_avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80',
                    'message'       => 'Will this run synchronously during candidate submission or asynchronously via cron?',
                    'created_at'    => '3 hours ago',
                ],
                [
                    'id'            => 'comm-103-2',
                    'job_id'        => 'job-103',
                    'sender_name'   => 'Elena Vance',
                    'sender_handle' => 'founder_elena',
                    'sender_role'   => 'admin',
                    'sender_avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
                    'message'       => 'Synchronous pre-filter on submit, with an in-depth score calculated when loaded into Threat Patrol desk.',
                    'created_at'    => '2 hours ago',
                ],
            ],
        ],
    ];
}

init_mock_db();

/**
 * Retrieve reaction metrics for an item (job or chat message).
 */
function get_item_reactions(string $itemId): array
{
    $userId = $_SESSION['user_id'] ?? ($_SESSION['active_persona_role'] ?? 'guest');
    $counts = $_SESSION['bounty_mock_db']['reactions'][$itemId] ?? [
        'like'   => 0,
        'launch' => 0,
        'bounty' => 0,
        'fire'   => 0,
    ];
    $userActive = $_SESSION['bounty_mock_db']['user_reactions'][$itemId][$userId] ?? [];
    return [
        'counts'      => $counts,
        'user_active' => $userActive,
    ];
}

/**
 * Retrieve discussion questions/replies for a specific job post.
 */
function get_job_comments(string $jobId): array
{
    return $_SESSION['bounty_mock_db']['job_comments'][$jobId] ?? [];
}

/**
 * Retrieve the weekly top earners leaderboard.
 */
function get_community_leaderboard(): array
{
    return [
        [
            'rank'         => 1,
            'medal'        => '🥇',
            'name'         => 'Alex Chen',
            'handle'       => 'alex_code',
            'role'         => 'Elite Bounty Hunter',
            'avatar_url'   => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
            'earnings'     => 48500.00,
            'bounties_won' => 12,
            'badge'        => 'Top Earner',
            'badge_color'  => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        ],
        [
            'rank'         => 2,
            'medal'        => '🥈',
            'name'         => 'Dmitri Rostov',
            'handle'       => 'dmitri_tech',
            'role'         => 'Systems Architect',
            'avatar_url'   => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150&auto=format&fit=crop&q=80',
            'earnings'     => 34200.00,
            'bounties_won' => 8,
            'badge'        => 'Master Hunter',
            'badge_color'  => 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30',
        ],
        [
            'rank'         => 3,
            'medal'        => '🥉',
            'name'         => 'Kavita Rao',
            'handle'       => 'kavita_fullstack',
            'role'         => 'Full-Stack Craftsman',
            'avatar_url'   => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=150&auto=format&fit=crop&q=80',
            'earnings'     => 26800.00,
            'bounties_won' => 6,
            'badge'        => 'UI Artisan',
            'badge_color'  => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        ],
        [
            'rank'         => 4,
            'medal'        => '⭐',
            'name'         => 'Sarah Jenkins',
            'handle'       => 'patrol_sarah',
            'role'         => 'Threat Patrol Mod',
            'avatar_url'   => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80',
            'earnings'     => 18500.00,
            'bounties_won' => 4,
            'badge'        => 'Guardian',
            'badge_color'  => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        ],
    ];
}


// ── 11. RESPONSE HELPERS ─────────────────────────────────────────────────────

/**
 * Emit a JSON response and terminate execution.
 *
 * @param  mixed  $data   JSON-serialisable payload.
 * @param  int    $status HTTP status code.
 */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Redirect to a URL and terminate.
 * Only allows relative paths to prevent open-redirect attacks.
 *
 * @param  string  $path   Relative path (must start with '/').
 * @param  array   $flash  Key-value pairs to store in session as flash messages.
 */
function safe_redirect(string $path, array $flash = []): never
{
    // SECURITY: Reject absolute URLs and protocol-relative URLs to prevent
    // open-redirect vulnerabilities.
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
        $path = BASE_URL . '/portal/index.php';
    }

    if (!empty($flash)) {
        $_SESSION['_flash'] = $flash;
    }

    header('Location: ' . $path, true, 303);
    exit;
}

/**
 * Retrieve and clear flash messages from session.
 *
 * @return array Flash message array (empty if none).
 */
function consume_flash(): array
{
    $flash = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flash;
}


// ── 12. HTML LAYOUT RENDERERS ────────────────────────────────────────────────

/**
 * Render the shared page <head>, navigation bar, and open <main> tag.
/**
 * Render the ambient visual FX background layer (Option A: CSS Nebula + Option B: Particle Canvas + Option C: Deep-Space Video).
 *
 * @param string|null $accent Optional theme accent ('purple', 'teal', 'rose', or null for default).
 */
function render_ambient_background(?string $accent = null): void
{
    $extraNebulaClass = match($accent) {
        'purple' => 'accent-purple-glow',
        'teal'   => 'accent-teal-glow',
        'rose'   => 'accent-rose-glow',
        default  => '',
    };
    ?>
    <!-- ==============================================================================
         AMBIENT VISUAL FX ENGINE
         Layer 0: Deep Space Looping Video (Option C)
         Layer 1: Ambient Floating Cyberpunk Nebula / Aurora Glows (Option A)
         Layer 2: Interactive Cyberpunk Particle Grid Canvas (Option B)
         Layer 3: Obsidian Contrast Scrim & Vignette
         ============================================================================== -->
    <div id="bounty-ambient-bg" class="fixed inset-0 pointer-events-none z-0 overflow-hidden select-none <?= $extraNebulaClass ?>" aria-hidden="true">
        <!-- Option C: Muted Deep-Space Looping Video Background -->
        <video id="ambient-cosmos-video" class="ambient-video-layer" autoplay loop muted playsinline preload="metadata">
            <source src="https://upload.wikimedia.org/wikipedia/commons/transcoded/3/37/A_superbubble_scene_%28potm2608a%29.webm/A_superbubble_scene_%28potm2608a%29.webm.480p.vp9.webm" type="video/webm">
            <source src="https://upload.wikimedia.org/wikipedia/commons/3/37/A_superbubble_scene_%28potm2608a%29.webm" type="video/webm">
        </video>

        <!-- Option A: Ambient Floating Cyberpunk Nebula Orbs -->
        <div class="ambient-nebula-orb nebula-1"></div>
        <div class="ambient-nebula-orb nebula-2"></div>
        <div class="ambient-nebula-orb nebula-3"></div>

        <!-- Option B: Interactive Cyberpunk Particle Grid Canvas -->
        <canvas id="bounty-particle-canvas" class="ambient-particle-canvas"></canvas>

        <!-- Deep Obsidian Contrast Scrim & Vignette (Guarantees 100% Readability) -->
        <div class="ambient-scrim"></div>
    </div>
    <?php
}

/**
 * Render the master HTML header, navigation, and brand elements.
 *
 * NOTE: Only the Supabase ANON key is injected into window.BOUNTY_CONFIG.
 * The service-role key is never exposed here or in any HTML output.
 *
 * @param  string  $page_title  Browser tab title prefix.
 * @param  string  $active_nav  Navigation item slug for active highlight.
 */
function render_header(string $page_title = 'Bounty Community Engine', string $active_nav = 'portal'): void
{
    // Resolve from the new context function (impersonation-aware).
    $ctx   = get_active_user_context();
    $flash = consume_flash();

    $role_badge_colors = [
        'admin'     => 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30',
        'recruiter' => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
        'hunter'    => 'bg-amber-500/20 text-amber-400 border-amber-500/30',
        'mod'       => 'bg-rose-500/20 text-rose-400 border-rose-500/30',
        'support'   => 'bg-cyan-500/20 text-cyan-400 border-cyan-500/30',
    ];
    $role_badge = $role_badge_colors[$ctx['role']] ?? 'bg-slate-700 text-slate-300';
    $baseUrl    = BASE_URL;
    if (empty($baseUrl) && str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/public')) {
        $baseUrl = '/public';
    }

    // Dynamic Level Badge calculation based on role and reputation
    $rep_score = (int)($ctx['reputation_score'] ?? 50);
    $user_level = match($ctx['role'] ?? 'hunter') {
        'admin'     => 10,
        'mod'       => 7,
        'support'   => 6,
        'recruiter' => 5,
        default     => max(1, min(9, (int)($rep_score / 15) + 1))
    };
    $level_title = match($ctx['role'] ?? 'hunter') {
        'admin'     => 'Founder',
        'mod'       => 'Guardian',
        'support'   => 'Staff',
        'recruiter' => 'Recruiter',
        default     => 'Hunter'
    };
    ?>
<!DOCTYPE html>
<html lang="en" class="dark" style="background-color: #0B0F17; color-scheme: dark;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | <?= htmlspecialchars(COMMUNITY_NAME) ?></title>
    <!-- Critical Dark Canvas & Glassmorphism Defaults (Prevents White FOUC) -->
    <style>
        :root { color-scheme: dark; }
        html, body {
            background-color: #0B0F17 !important;
            color: #F1F5F9 !important;
        }
        .glass-card, .bg-stitch-card {
            background: rgba(18, 24, 38, 0.75) !important;
            backdrop-filter: blur(16px) !important;
            -webkit-backdrop-filter: blur(16px) !important;
        }
    </style>
    <!-- Google Stitch Tokens & Cyberpunk Theme -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/css/stitch-tokens.css">
    <!-- Tailwind CSS with Stitch Palette -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brandDark:     '#0B0F17',
                        brandCard:     'rgba(18,24,38,0.75)',
                        brandIndigo:   '#6366F1',
                        brandMint:     '#10B981',
                        brandRed:      '#EF4444',
                        brandAmber:    '#F59E0B',
                        brandPurple:   '#A855F7',
                    },
                    boxShadow: {
                        'glow-indigo': '0 0 25px -5px rgba(99,102,241,0.35)',
                        'glow-mint':   '0 0 25px -5px rgba(16,185,129,0.35)',
                        'glow-purple': '0 0 25px -5px rgba(168,85,247,0.4)',
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <!-- Google Stitch Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-[#0B0F17] text-slate-100 min-h-screen flex flex-col antialiased selection:bg-indigo-500 selection:text-white pb-28 md:pb-12 has-bottom-dock relative">

    <?php render_ambient_background(); ?>

<?php /* ── Flash Messages ──────────────────────────────────────────────── */
if (!empty($flash)): ?>
    <div class="fixed top-20 right-5 z-50 flex flex-col gap-2">
        <?php foreach ($flash as $type => $msg): ?>
            <?php $cls = match($type) {
                'success' => 'bg-emerald-950/90 border-emerald-500/30 text-emerald-200',
                'error'   => 'bg-rose-950/90 border-rose-500/30 text-rose-200',
                default   => 'bg-slate-900/90 border-white/10 text-slate-200'
            }; ?>
            <div class="flex items-center gap-2 px-4 py-3 rounded-xl border backdrop-blur-xl text-sm font-medium <?= $cls ?>">
                <i class="fa-solid <?= $type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                <span><?= htmlspecialchars($msg) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

    <!-- Elevated Inset Floating Glass Navigation Bar (Google Stitch Prototype) -->
    <div class="w-full pt-4 px-4 sm:px-6 max-w-7xl mx-auto sticky top-3 z-40">
        <header id="master-header" class="bg-[#121826]/85 backdrop-blur-xl border border-slate-700/60 rounded-2xl shadow-2xl px-6 py-3.5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 lg:gap-6 min-w-0">
                <!-- Dynamic Brand Name -->
                <a href="<?= $baseUrl ?>/portal/index.php" class="flex items-center gap-2.5 sm:gap-3 group shrink-0">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-tr from-brandIndigo to-brandMint flex items-center justify-center shadow-glow-indigo group-hover:scale-105 transition-transform shrink-0">
                        <span class="material-symbols-outlined text-slate-950 font-black text-xl">bolt</span>
                    </div>
                    <div class="min-w-0">
                        <div class="font-bold text-sm sm:text-base tracking-tight text-white flex items-center gap-1.5 whitespace-nowrap">
                            <?= htmlspecialchars(COMMUNITY_NAME) ?>
                            <span class="text-[9px] sm:text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-brandIndigo/20 text-brandIndigo border border-brandIndigo/30">Engine</span>
                        </div>
                        <div class="hidden sm:block text-[10px] text-slate-400 font-mono -mt-0.5 truncate">Hostinger PHP 8.2 · Supabase</div>
                    </div>
                </a>

                <!-- Role-Aware Desktop Navigation Links -->
                <nav class="hidden lg:flex items-center gap-1">
                    <a href="<?= $baseUrl ?>/portal/index.php"
                       class="whitespace-nowrap text-xs font-semibold tracking-wide px-3 py-1.5 rounded-lg transition-all duration-200 flex items-center gap-1.5 <?= ($active_nav === 'portal') ? 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 shadow-sm' : 'text-slate-400 hover:text-indigo-400 hover:bg-slate-800/40' ?>">
                        <span class="material-symbols-outlined text-[18px]">forum</span>
                        <span>Lounge Feed</span>
                    </a>
                    <a href="<?= $baseUrl ?>/portal/job_hub.php"
                       class="whitespace-nowrap text-xs font-semibold tracking-wide px-3 py-1.5 rounded-lg transition-all duration-200 flex items-center gap-1.5 <?= ($active_nav === 'job_hub') ? 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 shadow-sm' : 'text-slate-400 hover:text-indigo-400 hover:bg-slate-800/40' ?>">
                        <span class="material-symbols-outlined text-[18px]">cases</span>
                        <span>Job Hub & ATS</span>
                    </a>
                    <a href="<?= $baseUrl ?>/portal/candidate_review.php"
                       class="whitespace-nowrap text-xs font-semibold tracking-wide px-3 py-1.5 rounded-lg transition-all duration-200 flex items-center gap-1.5 <?= ($active_nav === 'screening') ? 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 shadow-sm' : 'text-slate-400 hover:text-indigo-400 hover:bg-slate-800/40' ?>">
                        <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
                        <span>100-to-2 Screening</span>
                    </a>

                    <?php 
                    $user_role = $ctx['role'] ?? 'hunter';
                    $is_staff = in_array($user_role, ['admin', 'founder', 'mod', 'support'], true);
                    if ($is_staff): 
                    ?>
                    <!-- Staff Suite Dropdown for privileged roles -->
                    <div class="relative group">
                        <button type="button" class="whitespace-nowrap text-xs font-semibold tracking-wide px-3 py-1.5 rounded-lg transition-all duration-200 flex items-center gap-1.5 <?= in_array($active_nav, ['mod', 'support', 'admin', 'branding'], true) ? 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 shadow-sm' : 'text-slate-400 hover:text-indigo-400 hover:bg-slate-800/40' ?>">
                            <span class="material-symbols-outlined text-[18px]">shield</span>
                            <span>Staff Suite</span>
                            <span class="material-symbols-outlined text-[16px] opacity-70 group-hover:rotate-180 transition-transform duration-200">expand_more</span>
                        </button>
                        <div class="absolute left-0 top-full mt-2 w-48 rounded-xl bg-[#121826]/95 backdrop-blur-2xl border border-white/10 shadow-2xl py-1.5 hidden group-hover:block z-50 transition-all">
                            <?php if (in_array($user_role, ['admin', 'mod'], true)): ?>
                                <a href="<?= $baseUrl ?>/mod/index.php" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-semibold whitespace-nowrap <?= ($active_nav === 'mod') ? 'text-indigo-300 bg-indigo-500/15' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                                    <span class="material-symbols-outlined text-[18px] text-rose-400">shield</span>
                                    <span>Threat Patrol</span>
                                </a>
                            <?php endif; ?>
                            <?php if (in_array($user_role, ['admin', 'support'], true)): ?>
                                <a href="<?= $baseUrl ?>/support/index.php" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-semibold whitespace-nowrap <?= ($active_nav === 'support') ? 'text-indigo-300 bg-indigo-500/15' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                                    <span class="material-symbols-outlined text-[18px] text-cyan-400">support_agent</span>
                                    <span>Staff Desk</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($user_role === 'admin'): ?>
                                <a href="<?= $baseUrl ?>/admin/index.php" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-semibold whitespace-nowrap <?= ($active_nav === 'admin') ? 'text-indigo-300 bg-indigo-500/15' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                                    <span class="material-symbols-outlined text-[18px] text-indigo-400">monitoring</span>
                                    <span>Telemetry</span>
                                </a>
                                <a href="<?= $baseUrl ?>/admin/branding.php" class="flex items-center gap-2.5 px-3.5 py-2 text-xs font-semibold whitespace-nowrap <?= ($active_nav === 'branding') ? 'text-indigo-300 bg-indigo-500/15' : 'text-slate-300 hover:text-white hover:bg-white/5' ?>">
                                    <span class="material-symbols-outlined text-[18px] text-amber-400">tune</span>
                                    <span>Console</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Right: Level Badge, Live Coin Pill & Identity Chip -->
            <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
                <!-- Dynamic Level Badge (Visible on xl+ screens to prevent navbar squeeze) -->
                <div class="hidden xl:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-500/15 border border-indigo-500/30 text-indigo-300 text-xs font-mono font-bold" title="Player Reputation Level">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                    <span class="whitespace-nowrap">Lvl <?= $user_level ?> <?= $level_title ?></span>
                </div>

                <!-- Live Coin Pill (BDT Currency) -->
                <div class="inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 rounded-xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-400 text-xs sm:text-sm font-semibold shadow-glow-mint transition hover:border-emerald-500/40 shrink-0" title="Live Coin Balance & Escrow Custody (BDT)">
                    <span class="relative flex h-2 w-2 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
                    </span>
                    <span class="material-symbols-outlined text-[18px] text-emerald-400 shrink-0">monetization_on</span>
                    <span class="font-mono font-bold whitespace-nowrap"><?= format_bdt($ctx['wallet_balance'] ?? 0) ?></span>
                    <span class="hidden 2xl:inline text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-bold">Coins</span>
                </div>

                <!-- Identity Chip -->
                <div class="inline-flex items-center gap-2 px-2 sm:px-2.5 py-1 sm:py-1.5 rounded-xl glass-card shrink-0">
                    <img src="<?= htmlspecialchars($ctx['avatar_url'] ?? '') ?>"
                         alt="<?= htmlspecialchars($ctx['display_name'] ?? 'User') ?>"
                         class="w-7 h-7 rounded-lg object-cover border border-white/10 shrink-0">
                    <div class="hidden 2xl:block text-left max-w-[110px] truncate">
                        <div class="text-xs font-semibold text-white leading-tight truncate"><?= htmlspecialchars($ctx['display_name'] ?? '') ?></div>
                        <div class="text-[10px] font-mono text-slate-400 truncate">@<?= htmlspecialchars($ctx['handle'] ?? '') ?></div>
                    </div>
                    <span class="text-[10px] font-mono font-bold uppercase px-2 py-0.5 rounded-md border <?= $role_badge ?> shrink-0">
                        <?= htmlspecialchars($ctx['role'] ?? '') ?>
                    </span>
                </div>
            </div>
        </header>
    </div>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <?php
}

/**
 * Render the shared page footer, inject JS config, mobile bottom floating dock, and close tags.
 *
 * SECURITY: Only ANON_KEY is exposed to JS. Service-role key stays server-side.
 */
function render_footer(): void
{
    $ctx         = get_active_user_context();
    $baseUrl     = BASE_URL;
    if (empty($baseUrl) && str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/public')) {
        $baseUrl = '/public';
    }
    $supabaseUrl = SUPABASE_URL;
    $anonKey     = SUPABASE_ANON_KEY;

    // Generate a CSRF token for this session if not already present.
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    ?>
    </main>

    <!-- Mount points for floating tools -->
    <div id="sneak-bar-mount"></div>

    <!-- Responsive Bottom Floating Glass Dock for Mobile Viewports (Google Stitch Prototype) -->
    <nav id="mobile-floating-dock" class="md:hidden fixed bottom-3 left-1/2 -translate-x-1/2 w-[calc(100%-1.5rem)] max-w-md z-40 px-3 py-2 rounded-2xl glass-dock flex items-center justify-between border border-white/10 backdrop-blur-2xl">
        <!-- 1. Feed -->
        <a href="<?= $baseUrl ?>/portal/index.php" class="flex flex-col items-center gap-1 px-3 py-1 rounded-xl text-xs font-medium transition <?= (str_contains($_SERVER['REQUEST_URI'] ?? '', 'portal/index') || !str_contains($_SERVER['REQUEST_URI'] ?? '', 'php')) ? 'text-white' : 'text-slate-400 hover:text-white' ?>">
            <span class="material-symbols-outlined text-2xl <?= (str_contains($_SERVER['REQUEST_URI'] ?? '', 'portal/index') || !str_contains($_SERVER['REQUEST_URI'] ?? '', 'php')) ? 'text-brandIndigo' : '' ?>">forum</span>
            <span class="text-[10px]">Feed</span>
        </a>

        <!-- 2. Bounties -->
        <a href="<?= $baseUrl ?>/portal/job_hub.php" class="flex flex-col items-center gap-1 px-3 py-1 rounded-xl text-xs font-medium transition <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'job_hub') ? 'text-white' : 'text-slate-400 hover:text-white' ?>">
            <span class="material-symbols-outlined text-2xl <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'job_hub') ? 'text-brandIndigo' : '' ?>">cases</span>
            <span class="text-[10px]">Bounties</span>
        </a>

        <!-- 3. Post Task (Elevated Center Action Button) -->
        <a href="<?= $baseUrl ?>/portal/job_hub.php#new-bounty" class="flex flex-col items-center -mt-6 group" title="Post Bounty with Escrow">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brandIndigo to-brandMint flex items-center justify-center shadow-glow-indigo border border-white/20 group-hover:scale-105 active:scale-95 transition-transform">
                <span class="material-symbols-outlined text-2xl text-slate-950 font-bold">add</span>
            </div>
            <span class="text-[10px] font-bold text-white mt-1">Post Task</span>
        </a>

        <!-- 4. Leaderboard / Screening -->
        <a href="<?= $baseUrl ?>/portal/candidate_review.php" class="flex flex-col items-center gap-1 px-3 py-1 rounded-xl text-xs font-medium transition <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'candidate_review') ? 'text-white' : 'text-slate-400 hover:text-white' ?>">
            <span class="material-symbols-outlined text-2xl <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'candidate_review') ? 'text-brandIndigo' : '' ?>">how_to_reg</span>
            <span class="text-[10px]">Screening</span>
        </a>

        <!-- 5. Support -->
        <a href="<?= $baseUrl ?>/support/index.php" class="flex flex-col items-center gap-1 px-3 py-1 rounded-xl text-xs font-medium transition <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'support') ? 'text-white' : 'text-slate-400 hover:text-white' ?>">
            <span class="material-symbols-outlined text-2xl <?= str_contains($_SERVER['REQUEST_URI'] ?? '', 'support') ? 'text-brandIndigo' : '' ?>">support_agent</span>
            <span class="text-[10px]">Support</span>
        </a>
    </nav>

    <script>
        // NOTE: Only the public anon key is exposed here.
        // Service-role key is never included in any client-side output.
        window.BOUNTY_CONFIG = {
            baseUrl:              <?= json_encode($baseUrl, JSON_UNESCAPED_SLASHES) ?>,
            supabaseUrl:          <?= json_encode($supabaseUrl, JSON_UNESCAPED_SLASHES) ?>,
            supabaseAnonKey:      <?= json_encode($anonKey) ?>,
            isSupabaseConfigured: <?= json_encode(is_supabase_configured()) ?>,
            activeContext:        <?= json_encode([
                'id'               => $ctx['id']               ?? null,
                'role'             => $ctx['role']             ?? null,
                'display_name'     => $ctx['display_name']     ?? null,
                'handle'           => $ctx['handle']           ?? null,
                'avatar_url'       => $ctx['avatar_url']       ?? null,
                'wallet_balance'   => $ctx['wallet_balance']   ?? 0,
                'reputation_score' => $ctx['reputation_score'] ?? 50,
                'is_impersonating' => $ctx['is_impersonating'] ?? false,
                // real_admin_id deliberately excluded from JS context.
            ]) ?>,
            csrfToken: <?= json_encode($_SESSION['_csrf_token']) ?>,
        };

        // Initialize Lucide icons if loaded
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    </script>
    <script src="<?= $baseUrl ?>/js/ambient-visuals.js"></script>
    <script src="<?= $baseUrl ?>/js/app.js"></script>
    <script src="<?= $baseUrl ?>/js/sneak-bar.js"></script>
</body>
</html>
    <?php
}
