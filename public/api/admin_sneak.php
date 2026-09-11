<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — api/admin_sneak.php
// Supabase Project : ehswbdmizytpahosqkuj  (ap-southeast-2)
// Hostinger PHP 8.2+ / Apache
// ------------------------------------------------------------------------------
// PURPOSE:
//   Handles admin-only persona teleportation (impersonation) actions:
//     • teleport_to_user  — Impersonate any specific user by UUID.
//     • teleport_to_role  — Adopt a platform role (mod / support) without
//                           targeting a specific user UUID.
//     • exit_persona      — Exit impersonation and restore admin identity.
//     • list_personas     — Return predefined mock personas (dev/demo only).
//     • switch_mock_role  — Switch the active_persona_role in mock mode.
//
// SECURITY BOUNDARIES:
//   1. CSRF Token Validation — Every state-changing POST is gated behind a
//      matching $_SESSION['_csrf_token'] check. The token is consumed on every
//      successful write so that replayed requests are rejected.
//   2. Admin-Only Gating — The request caller's profile is fetched FRESH from
//      Supabase (or mock DB) and its role must equal 'admin'. This check is
//      performed by assert_admin_or_die() BEFORE any session state is written.
//   3. UUID Sanitisation — All user-supplied UUIDs pass through sanitise_uuid()
//      from config.php before being stored in session or used in API calls.
//   4. Role Sanitisation — All role strings pass through sanitise_role() before
//      being stored in session.
//   5. No Credentials in URLs — After every action, a POST-Redirect-GET (PRG)
//      pattern is used via safe_redirect() to prevent tokens appearing in
//      browser history, referrer headers, or server access logs.
//   6. Stale Impersonation Guard — real_admin_id cannot be overwritten once set.
//      Any request that attempts to change it while a session is live is rejected.
//   7. Privilege Escalation Prevention — An admin cannot teleport INTO an admin
//      profile. Admins may only teleport into non-admin user UUIDs.
// ==============================================================================

require_once __DIR__ . '/../config.php';

// ── ONLY ACCEPT POST ─────────────────────────────────────────────────────────
// This handler changes session state; GET requests must never trigger mutations.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect quietly — do not reveal the handler exists to unauthenticated GETs.
    safe_redirect(BASE_URL . '/portal/index.php');
}


// ── CSRF VALIDATION ──────────────────────────────────────────────────────────
// Validate the CSRF token submitted with the form against the one stored in
// session. We use a constant-time comparison (hash_equals) to prevent timing
// attacks. If validation fails, we abort immediately and clear the form data
// from memory.
//
// SECURITY: The token in session is regenerated after each successful action
// (not consumed per-check, because the redirect loop would lose it for the
// flash). Rotation is done at the end of each action branch.

// Support application/json payloads (e.g. from sneak-bar.js or API fetch callers)
if (empty($_POST) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $raw_input = file_get_contents('php://input');
    $parsed_input = json_decode($raw_input, true);
    if (is_array($parsed_input)) {
        $_POST = $parsed_input;
    }
}

$submitted_csrf = trim($_POST['_csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$session_csrf   = $_SESSION['_csrf_token'] ?? '';

if (
    $session_csrf === ''
    || $submitted_csrf === ''
    || !hash_equals($session_csrf, $submitted_csrf)
) {
    // Possible CSRF attempt or stale form submission.
    error_log('[Bounty][Security] CSRF validation failed on admin_sneak.php from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        json_response(['error' => 'Security token mismatch. Please reload and try again.'], 403);
    }
    safe_redirect(BASE_URL . '/portal/index.php', ['error' => 'Security token mismatch. Please reload and try again.']);
}


// ── ADMIN GATE ───────────────────────────────────────────────────────────────
// Determine the "real" caller identity.
//
// CASE A: An active admin session already exists (real_admin_id is set).
//         This means an admin is currently impersonating someone. We use the
//         stored real_admin_id as the authoritative caller identity.
//
// CASE B: No impersonation is active. The caller is their own mock persona or
//         has a Supabase session. We use get_active_user_context() to resolve.
//
// In both cases, we re-fetch the profile from Supabase (or mock DB) to get a
// fresh role value — never trust the role stored in the session.

/**
 * Abort the request with an error redirect if the caller is not an admin.
 *
 * This function also returns the verified admin profile so callers can use it.
 *
 * @param  string|null  $real_admin_uuid  Already-validated UUID of the real admin,
 *                                        or null to derive it from the current context.
 * @return array  Verified admin profile row.
 */
function assert_admin_or_die(?string $real_admin_uuid = null): array
{
    // If a real_admin_id is stored in session, it must match what was passed in.
    $session_real_admin = isset($_SESSION['real_admin_id'])
        ? sanitise_uuid($_SESSION['real_admin_id'])
        : null;

    // If neither the argument nor the session has an admin UUID, fall back to
    // resolving through the mock persona engine.
    if ($real_admin_uuid === null && $session_real_admin === null) {
        $ctx = get_active_user_context();
        if (($ctx['role'] ?? '') !== 'admin') {
            error_log('[Bounty][Security] Non-admin attempted admin_sneak action. Context role: ' . ($ctx['role'] ?? 'unknown'));
            safe_redirect(BASE_URL . '/portal/index.php', ['error' => 'Access denied.']);
        }
        return $ctx;
    }

    // Prefer the argument UUID; fall back to session value.
    $uuid_to_verify = $real_admin_uuid ?? $session_real_admin;

    if ($uuid_to_verify === null) {
        safe_redirect(BASE_URL . '/portal/index.php', ['error' => 'Invalid admin session.']);
    }

    $profile = fetch_profile_by_uuid($uuid_to_verify);

    if ($profile === null || ($profile['role'] ?? '') !== 'admin') {
        // Admin profile not found or role has changed. Clear all impersonation.
        unset($_SESSION['real_admin_id'], $_SESSION['impersonated_user_id'], $_SESSION['impersonated_role']);
        error_log('[Bounty][Security] admin_sneak: caller UUID is not an admin. Cleared session.');
        safe_redirect(BASE_URL . '/portal/index.php', ['error' => 'Admin privileges required.']);
    }

    return $profile;
}

// Resolve the real admin UUID from session (may be null in mock-only mode).
$session_real_admin_uuid = isset($_SESSION['real_admin_id'])
    ? sanitise_uuid($_SESSION['real_admin_id'])
    : null;

// Verify caller is admin. If not, this call does not return.
$admin_profile = assert_admin_or_die($session_real_admin_uuid);
$admin_uuid    = $admin_profile['id'] ?? null;


// ── STALE IMPERSONATION GUARD ────────────────────────────────────────────────
// Once real_admin_id is locked in session, it CANNOT be changed to a different
// UUID within the same session. This prevents an impersonated-user payload from
// attempting to escalate by submitting a different real_admin_id.

if ($session_real_admin_uuid !== null && $admin_uuid !== $session_real_admin_uuid) {
    // real_admin_id mismatch — potential session manipulation attempt.
    unset($_SESSION['real_admin_id'], $_SESSION['impersonated_user_id'], $_SESSION['impersonated_role']);
    error_log('[Bounty][Security] admin_sneak: real_admin_id mismatch detected. Cleared session.');
    safe_redirect(BASE_URL . '/portal/index.php', ['error' => 'Session integrity error. Please log in again.']);
}


// ── ROUTE BY ACTION ──────────────────────────────────────────────────────────
$action      = trim($_POST['action'] ?? '');
$redirect_to = trim($_POST['redirect_to'] ?? '');

// Validate the optional redirect_to param: must be a relative path only.
// If it is absolute or protocol-relative, fall back to the portal index.
if ($redirect_to === '' || !str_starts_with($redirect_to, '/') || str_starts_with($redirect_to, '//')) {
    $redirect_to = BASE_URL . '/portal/index.php';
}


switch ($action) {

    // ─────────────────────────────────────────────────────────────────────────
    // ACTION: teleport_to_user
    // Impersonate a specific user identified by UUID.
    // ─────────────────────────────────────────────────────────────────────────
    case 'teleport_to_user':

        $raw_target_uuid = trim($_POST['target_user_id'] ?? '');
        $target_uuid     = sanitise_uuid($raw_target_uuid);

        // -- Validate target UUID --
        if ($target_uuid === null) {
            safe_redirect($redirect_to, ['error' => 'Invalid target user ID format.']);
        }

        // -- Prevent self-impersonation --
        if ($target_uuid === $admin_uuid) {
            safe_redirect($redirect_to, ['error' => 'Cannot impersonate yourself.']);
        }

        // -- Fetch and validate the target user profile --
        $target_profile = fetch_profile_by_uuid($target_uuid);

        if ($target_profile === null) {
            safe_redirect($redirect_to, ['error' => 'Target user not found.']);
        }

        // SECURITY: Prevent admin-to-admin escalation.
        // An admin impersonating another admin account would grant the operator
        // a second admin identity with potentially different data access patterns,
        // making audit trails ambiguous. This is explicitly blocked.
        if (($target_profile['role'] ?? '') === 'admin') {
            error_log('[Bounty][Security] Admin ' . $admin_uuid . ' attempted to impersonate another admin: ' . $target_uuid);
            safe_redirect($redirect_to, ['error' => 'Cannot impersonate another admin account.']);
        }

        // -- Lock in real_admin_id (write-once if not already set) --
        // This is the ONLY place real_admin_id is written to session.
        if (!isset($_SESSION['real_admin_id'])) {
            $_SESSION['real_admin_id'] = $admin_uuid;
        }

        // Write sanitised impersonation state.
        $_SESSION['impersonated_user_id'] = $target_uuid;
        // Clear any previous role override — use the target's actual role.
        unset($_SESSION['impersonated_role']);

        // Rotate CSRF token after state change.
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        // Audit log (no sensitive data — UUIDs only).
        error_log(sprintf(
            '[Bounty][Audit] Admin %s teleported to user %s (role: %s)',
            $admin_uuid,
            $target_uuid,
            $target_profile['role'] ?? 'unknown'
        ));

        safe_redirect($redirect_to, [
            'success' => 'Now acting as ' . htmlspecialchars($target_profile['display_name'] ?? 'Unknown') . ' (@' . htmlspecialchars($target_profile['handle'] ?? $target_uuid) . ').',
        ]);
        break; // unreachable — safe_redirect exits


    // ─────────────────────────────────────────────────────────────────────────
    // ACTION: teleport_to_role
    // Adopt a role identity WITHOUT targeting a specific user UUID.
    // Only allows roles in ADMIN_TELEPORT_ROLES (mod / support).
    // ─────────────────────────────────────────────────────────────────────────
    case 'teleport_to_role':

        $raw_role     = trim($_POST['target_role'] ?? '');
        $target_role  = sanitise_role($raw_role);

        // -- Validate the requested role --
        if ($target_role === null) {
            safe_redirect($redirect_to, ['error' => 'Invalid role value.']);
        }

        // Only allow teleporting into the restricted set of roles.
        if (!in_array($target_role, ADMIN_TELEPORT_ROLES, true)) {
            error_log(sprintf(
                '[Bounty][Security] Admin %s attempted to teleport to disallowed role: %s',
                $admin_uuid,
                $target_role
            ));
            safe_redirect($redirect_to, ['error' => 'You cannot teleport into the "' . htmlspecialchars($target_role) . '" role via this action.']);
        }

        // -- Lock real_admin_id --
        if (!isset($_SESSION['real_admin_id'])) {
            $_SESSION['real_admin_id'] = $admin_uuid;
        }

        // For role-based teleport, we use the mock persona's UUID as the
        // impersonated_user_id so that fetch_profile_by_uuid returns a valid
        // profile. In a live Supabase setup the mock UUID may not exist;
        // get_active_user_context handles the fallback gracefully.
        $role_persona_uuid = match($target_role) {
            'mod'     => '44444444-4444-4444-4444-444444444444',
            'support' => '55555555-5555-5555-5555-555555555555',
            default   => null,
        };

        if ($role_persona_uuid === null) {
            safe_redirect($redirect_to, ['error' => 'No persona UUID mapped for this role.']);
        }

        $_SESSION['impersonated_user_id'] = $role_persona_uuid;
        $_SESSION['impersonated_role']    = $target_role;  // Explicit role override.

        // Rotate CSRF token.
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        error_log(sprintf('[Bounty][Audit] Admin %s adopted role-persona: %s', $admin_uuid, $target_role));

        safe_redirect($redirect_to, [
            'success' => 'Now viewing the platform as the "' . htmlspecialchars($target_role) . '" role.',
        ]);
        break;


    // ─────────────────────────────────────────────────────────────────────────
    // ACTION: exit_persona
    // Exit impersonation and restore the admin's own identity.
    // ─────────────────────────────────────────────────────────────────────────
    case 'exit_persona':

        // Clear all impersonation state.
        // real_admin_id is also cleared so the session returns to a clean state.
        unset(
            $_SESSION['real_admin_id'],
            $_SESSION['impersonated_user_id'],
            $_SESSION['impersonated_role']
        );

        // Regenerate session ID to prevent fixation after privilege change.
        session_regenerate_id(true);

        // Rotate CSRF token.
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        error_log(sprintf('[Bounty][Audit] Admin %s exited impersonation persona.', $admin_uuid ?? 'unknown'));

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            json_response([
                'success'  => true,
                'message'  => 'Exited persona view. You are now back as yourself.',
                'redirect' => BASE_URL . '/admin/index.php',
            ]);
        }

        safe_redirect(BASE_URL . '/admin/index.php', [
            'success' => 'Exited persona view. You are now back as yourself.',
        ]);
        break;


    // ─────────────────────────────────────────────────────────────────────────
    // ACTION: list_personas   (JSON response — for sneak-bar.js AJAX)
    // Return predefined mock personas. Admin only.
    // ─────────────────────────────────────────────────────────────────────────
    case 'list_personas':

        $personas = get_predefined_personas();

        // Strip credentials from the response — return only display-safe fields.
        $safe = [];
        foreach ($personas as $role_key => $p) {
            $safe[$role_key] = [
                'id'           => $p['id'],
                'role'         => $p['role'],
                'role_label'   => $p['role_label'],
                'display_name' => $p['display_name'],
                'handle'       => $p['handle'],
                'avatar_url'   => $p['avatar_url'],
            ];
        }

        json_response([
            'success'        => true,
            'current_role'   => $admin_profile['role'],
            'personas'       => $safe,
        ]);
        break; // unreachable — json_response exits


    // ─────────────────────────────────────────────────────────────────────────
    // ACTION: switch_mock_role   (for mock/dev env only — sneak-bar.js)
    // Switches $_SESSION['active_persona_role'] in the mock persona engine.
    // This is a lighter-weight operation than teleport_to_user and does NOT
    // set real_admin_id or impersonated_user_id.
    // ─────────────────────────────────────────────────────────────────────────
    case 'switch_mock_role':

        $raw_role   = trim($_POST['role'] ?? '');
        $clean_role = sanitise_role($raw_role);

        if ($clean_role === null) {
            json_response(['error' => 'Invalid role.'], 400);
        }

        $switched = set_active_persona($clean_role);

        if ($switched === false) {
            json_response(['error' => 'Role not found in persona table.'], 404);
        }

        // Rotate CSRF token.
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        json_response([
            'success'        => true,
            'message'        => 'Switched to persona: ' . $switched['display_name'] . ' (' . $switched['role_label'] . ')',
            'active_persona' => [
                'id'           => $switched['id'],
                'role'         => $switched['role'],
                'display_name' => $switched['display_name'],
                'handle'       => $switched['handle'],
                'avatar_url'   => $switched['avatar_url'],
                'wallet_balance' => $switched['wallet_balance'],
            ],
            'new_csrf_token' => $_SESSION['_csrf_token'],
        ]);
        break;


    // ─────────────────────────────────────────────────────────────────────────
    // ACTION: reset_mock   (admin-only: reset the mock DB to seed state)
    // ─────────────────────────────────────────────────────────────────────────
    case 'reset_mock':

        unset(
            $_SESSION['bounty_mock_db'],
            $_SESSION['persona_overrides'],
            $_SESSION['active_persona_role']
        );
        init_mock_db();

        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

        safe_redirect(BASE_URL . '/admin/branding.php', [
            'success' => 'Demo database and personas reset to initial seed state.',
        ]);
        break;


    // ─────────────────────────────────────────────────────────────────────────
    // UNKNOWN ACTION
    // ─────────────────────────────────────────────────────────────────────────
    default:

        // Log unexpected action values — could indicate probing.
        error_log('[Bounty][Security] admin_sneak received unknown action: ' . substr($action, 0, 64));
        safe_redirect(BASE_URL . '/portal/index.php', ['error' => 'Unknown action.']);
        break;
}
