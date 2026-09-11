<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — Authentication & Role Gatekeeper Handler
// Path: public/api/auth_handler.php
// Hostinger PHP 8.2 / Apache
// ==============================================================================

require_once __DIR__ . '/../config.php';

// Parse incoming request (supports both JSON body and standard Form POST)
$isJsonRequest = false;
$inputData = [];

$contentType = $_SERVER['CONTENT-TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';

if (str_contains($contentType, 'application/json')) {
    $isJsonRequest = true;
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true) ?: [];
} else {
    $inputData = $_POST;
    if (str_contains($acceptHeader, 'application/json') || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        $isJsonRequest = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isJsonRequest) {
        json_response(['error' => 'Method not allowed. Use POST.'], 405);
    }
    header('Location: ' . BASE_URL . '/portal/auth.php');
    exit;
}

// CSRF validation
$csrfToken = $inputData['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    if ($isJsonRequest) {
        json_response(['error' => 'Invalid or expired CSRF token.'], 403);
    }
    set_flash('error', 'Session expired. Please try logging in again.');
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/portal/auth.php')));
    exit;
}

$action = trim($inputData['action'] ?? 'login');
$portal = strtolower(trim($inputData['portal'] ?? 'portal')); // portal, admin, support, mod/staff
$email = strtolower(trim($inputData['email'] ?? ''));
$password = trim($inputData['password'] ?? '');

$personas = get_predefined_personas();

// ── 1. LOGIN WORKFLOW ────────────────────────────────────────────────────────
if ($action === 'login') {
    if (empty($email)) {
        $msg = 'Email address or handle is required.';
        if ($isJsonRequest) { json_response(['error' => $msg], 422); }
        set_flash('error', $msg);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/portal/auth.php')));
        exit;
    }

    $matchedUser = null;

    // Search in predefined personas first (supports email or handle)
    foreach ($personas as $rKey => $p) {
        if ($p['email'] === $email || $p['handle'] === $email || $rKey === $email) {
            $matchedUser = $p;
            $matchedUser['persona_key'] = $rKey;
            break;
        }
    }

    // Check custom registered users in mock DB
    if (!$matchedUser && !empty($_SESSION['bounty_mock_db']['registered_users'])) {
        foreach ($_SESSION['bounty_mock_db']['registered_users'] as $u) {
            if ($u['email'] === $email || $u['handle'] === $email) {
                $matchedUser = $u;
                break;
            }
        }
    }

    // Check Supabase if configured and not found in mock personas
    if (!$matchedUser && is_supabase_configured()) {
        $cleanHandle = ltrim($email, '@');
        $res = supabase_request(
            'rest/v1/profiles?or=(email.eq.' . urlencode($email) . ',handle.eq.' . urlencode($cleanHandle) . ')&limit=1',
            'GET',
            null,
            true
        );
        if ($res['status_code'] === 200 && !empty($res['data'][0])) {
            $matchedUser = $res['data'][0];
        }
    }

    if (!$matchedUser) {
        $msg = 'Account not found. Please check your credentials or register.';
        if ($isJsonRequest) { json_response(['error' => $msg], 401); }
        set_flash('error', $msg);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (empty(BASE_URL) ? '/login' : BASE_URL . '/portal/auth.php')));
        exit;
    }

    $userRole = $matchedUser['role'] ?? 'hunter';

    // ── CROSS-PORTAL ACCESS RESTRICTIONS ─────────────────────────────────────
    // Strict boundary gates: Hunter/Recruiter personas are rejected from administrative and staff terminals
    if ($portal === 'admin') {
        if (!in_array($userRole, ['admin', 'founder'], true)) {
            $msg = 'Unauthorized: Admin privileges required. Hunter/Recruiter personas cannot access Founder Terminal.';
            if ($isJsonRequest) { json_response(['error' => $msg], 403); }
            set_flash('error', $msg);
            header('Location: ' . (empty(BASE_URL) ? '/admin/login' : BASE_URL . '/admin/login.php'));
            exit;
        }
    } elseif ($portal === 'support') {
        if (!in_array($userRole, ['support', 'admin', 'founder'], true)) {
            $msg = 'Unauthorized: Support desk access required. Hunter/Recruiter personas cannot access Customer Support.';
            if ($isJsonRequest) { json_response(['error' => $msg], 403); }
            set_flash('error', $msg);
            header('Location: ' . (empty(BASE_URL) ? '/support/login' : BASE_URL . '/support/login.php'));
            exit;
        }
    } elseif (in_array($portal, ['mod', 'staff', 'stuff'], true)) {
        if (!in_array($userRole, ['mod', 'admin', 'founder'], true)) {
            $msg = 'Unauthorized: Staff threat patrol access required. Hunter/Recruiter personas cannot access Threat Patrol.';
            if ($isJsonRequest) { json_response(['error' => $msg], 403); }
            set_flash('error', $msg);
            header('Location: ' . (empty(BASE_URL) ? '/staff/login' : BASE_URL . '/mod/login.php'));
            exit;
        }
    }

    // Set authenticated session state
    $_SESSION['user_id'] = $matchedUser['id'];
    $_SESSION['user_role'] = $userRole;
    $_SESSION['user_email'] = $matchedUser['email'];
    $_SESSION['user_name'] = $matchedUser['display_name'] ?? 'User';
    $_SESSION['active_persona_role'] = $matchedUser['persona_key'] ?? $userRole;

    if (in_array($userRole, ['admin', 'founder'], true)) {
        $_SESSION['real_admin_id'] = $matchedUser['id'];
    } else {
        unset($_SESSION['real_admin_id'], $_SESSION['impersonated_user_id'], $_SESSION['impersonated_role']);
    }

    // Determine target redirect
    $redirectUrl = match($portal) {
        'admin'           => empty(BASE_URL) ? '/admin' : BASE_URL . '/admin/index.php',
        'support'         => empty(BASE_URL) ? '/support' : BASE_URL . '/support/index.php',
        'mod', 'staff', 'stuff' => empty(BASE_URL) ? '/staff' : BASE_URL . '/mod/index.php',
        default           => empty(BASE_URL) ? '/' : BASE_URL . '/portal/index.php'
    };

    if ($isJsonRequest) {
        json_response([
            'success'  => true,
            'redirect' => $redirectUrl,
            'role'     => $userRole,
            'name'     => $matchedUser['display_name'],
        ]);
    }

    header('Location: ' . $redirectUrl);
    exit;
}

// ── 2. SIGNUP WORKFLOW ───────────────────────────────────────────────────────
if ($action === 'signup') {
    // Registrations only allowed for general user portal
    if (in_array($portal, ['admin', 'support', 'mod', 'staff', 'stuff'], true)) {
        $msg = 'Direct registration is disabled for administrative and staff portals.';
        if ($isJsonRequest) { json_response(['error' => $msg], 403); }
        set_flash('error', $msg);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? (empty(BASE_URL) ? '/login' : BASE_URL . '/portal/auth.php')));
        exit;
    }

    $displayName = trim($inputData['display_name'] ?? '');
    $handle      = trim(ltrim($inputData['handle'] ?? '', '@'));
    $role        = strtolower(trim($inputData['role'] ?? 'hunter'));

    // Allowed registration roles: 'hunter' or 'recruiter'
    if (!in_array($role, ['hunter', 'recruiter'], true)) {
        $msg = 'Invalid role selected. Allowed roles are Bounty Hunter or Lead Recruiter.';
        if ($isJsonRequest) { json_response(['error' => $msg], 422); }
        set_flash('error', $msg);
        header('Location: ' . (empty(BASE_URL) ? '/signup' : BASE_URL . '/portal/auth.php?tab=signup'));
        exit;
    }

    if (empty($email) || empty($displayName) || empty($handle)) {
        $msg = 'All required fields (Name, Handle, Email) must be provided.';
        if ($isJsonRequest) { json_response(['error' => $msg], 422); }
        set_flash('error', $msg);
        header('Location: ' . (empty(BASE_URL) ? '/signup' : BASE_URL . '/portal/auth.php?tab=signup'));
        exit;
    }

    $newUserId = 'usr-' . bin2hex(random_bytes(8));
    $newUser = [
        'id'               => $newUserId,
        'display_name'     => $displayName,
        'handle'           => $handle,
        'email'            => $email,
        'role'             => $role,
        'role_label'       => ($role === 'recruiter') ? 'Lead Recruiter' : 'Elite Bounty Hunter',
        'avatar_url'       => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150&auto=format&fit=crop&q=80',
        'wallet_balance'   => ($role === 'recruiter') ? 10000.00 : 2500.00,
        'rating'           => 5.00,
        'reputation_score' => 100,
        'created_at'       => date('Y-m-d H:i:s'),
    ];

    // Store in mock session DB
    if (!isset($_SESSION['bounty_mock_db']['registered_users'])) {
        $_SESSION['bounty_mock_db']['registered_users'] = [];
    }
    $_SESSION['bounty_mock_db']['registered_users'][$newUserId] = $newUser;

    // Persist to Supabase profiles table if available
    if (is_supabase_configured()) {
        supabase_request('rest/v1/profiles', 'POST', [
            'id'               => sanitise_uuid(bin2hex(random_bytes(16))),
            'email'            => $email,
            'display_name'     => $displayName,
            'handle'           => $handle,
            'role'             => $role,
            'wallet_balance'   => $newUser['wallet_balance'],
            'reputation_score' => $newUser['reputation_score'],
        ], true);
    }

    // Set active session
    $_SESSION['user_id'] = $newUserId;
    $_SESSION['user_role'] = $role;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name'] = $displayName;
    $_SESSION['active_persona_role'] = $role;

    $redirectUrl = empty(BASE_URL) ? '/' : BASE_URL . '/portal/index.php';

    if ($isJsonRequest) {
        json_response([
            'success'  => true,
            'redirect' => $redirectUrl,
            'role'     => $role,
            'name'     => $displayName,
        ], 201);
    }

    header('Location: ' . $redirectUrl);
    exit;
}

// Default fallback for invalid action
json_response(['error' => 'Invalid action.'], 400);
