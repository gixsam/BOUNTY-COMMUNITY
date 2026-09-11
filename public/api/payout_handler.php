<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — api/payout_handler.php
// RPC Target : public.hire_and_release_payout(...)   (SECURITY DEFINER)
// Hostinger PHP 8.2+ / Apache
// ------------------------------------------------------------------------------
// SECURITY BOUNDARIES:
//   • Accepts POST only.
//   • Validates CSRF token before any processing.
//   • Resolves actor identity exclusively from session via get_active_user_context().
//   • NEVER patches coin_balance / total_escrow_locked / wallet_balance directly.
//     All balance mutations happen atomically inside the SECURITY DEFINER RPC
//     with a FOR UPDATE row lock on the jobs table.
//   • Enforces creator-or-admin/mod gate on the PHP side BEFORE calling the RPC
//     (defence-in-depth — the same check exists inside the RPC body).
//   • job_id and applicant_id are validated as UUID-v4 strings before use.
//   • Postgres exception messages are parsed server-side; only safe, user-facing
//     messages are forwarded to the client.
//   • Service-role key is used for the RPC (required for SECURITY DEFINER
//     execution via PostgREST /rpc/ endpoint from server-side PHP).
// ==============================================================================

require_once __DIR__ . '/../config.php';

// Always respond with JSON.
header('Content-Type: application/json; charset=utf-8');

// ── Method gate ───────────────────────────────────────────────────────────────
// Payout is a destructive operation; only POST is accepted.
if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
    json_response(['error' => 'Method not allowed. Use POST.'], 405);
}


// ── 1. Parse request body ─────────────────────────────────────────────────────
// Accept JSON body (AJAX path) or form-encoded fallback.
$raw   = file_get_contents('php://input');
$input = [];

if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    }
}
if (empty($input)) {
    $input = $_POST;
}


// ── 2. CSRF validation ────────────────────────────────────────────────────────
// Constant-time comparison prevents timing-based token forgery.
$submitted_csrf = trim($input['_csrf'] ?? '');
$session_csrf   = $_SESSION['_csrf_token'] ?? '';

if ($session_csrf === '' || $submitted_csrf === '' || !hash_equals($session_csrf, $submitted_csrf)) {
    error_log('[Bounty][Security] payout_handler.php: CSRF mismatch from IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    json_response(['error' => 'Security token invalid or expired. Please refresh and try again.'], 403);
}

// Rotate token to prevent replay.
$_SESSION['_csrf_token'] = bin2hex(random_bytes(32));


// ── 3. Resolve active user context ────────────────────────────────────────────
// actor_id is ALWAYS taken from the server-side session — NEVER from the
// request body. This prevents caller impersonation via the API.
$ctx = get_active_user_context();

if (empty($ctx['id'])) {
    json_response(['error' => 'You must be logged in to release a payout.'], 401);
}

$actor_id   = $ctx['id'];
$actor_role = $ctx['role'] ?? '';


// ── 4. Parameter extraction & UUID sanitisation ───────────────────────────────

$raw_job_id       = trim($input['job_id']       ?? '');
$raw_applicant_id = trim($input['applicant_id'] ?? '');

if ($raw_job_id === '') {
    json_response(['error' => 'job_id is required.'], 422);
}
if ($raw_applicant_id === '') {
    json_response(['error' => 'applicant_id is required.'], 422);
}

// Validate UUID format for both IDs.
$job_id       = sanitise_uuid($raw_job_id);
$applicant_id = sanitise_uuid($raw_applicant_id);

if ($job_id === null) {
    json_response(['error' => 'Invalid job_id format. Expected a UUID-v4 string.'], 422);
}
if ($applicant_id === null) {
    json_response(['error' => 'Invalid applicant_id format. Expected a UUID-v4 string.'], 422);
}

// Sanity: an applicant cannot pay themselves.
if ($applicant_id === $actor_id) {
    json_response(['error' => 'Actor and applicant cannot be the same user.'], 422);
}


// ── 5. PHP-side authorisation gate ───────────────────────────────────────────
// Roles permitted to trigger a payout:
//   • The job creator (verified by fetching the job from Supabase/mock DB).
//   • Admin or Mod (platform oversight roles).
//
// DEFENCE-IN-DEPTH: The SECURITY DEFINER RPC performs the same check inside
// Postgres. Both layers must pass for the payout to succeed.
//
// We skip the creator-check in mock mode because mock jobs don't have live
// Supabase rows to fetch — the mock payout function handles auth internally.

if (is_supabase_configured()) {
    // Fetch the job to check creator_id.
    $job_res = supabase_request(
        'rest/v1/jobs?id=eq.' . urlencode($job_id) . '&select=id,creator_id,escrow_status,status&limit=1',
        'GET',
        null,
        true  // Use service-role for server-side admin reads.
    );

    if (!empty($job_res['error'])) {
        error_log('[Bounty][RPC] payout_handler: job fetch cURL error: ' . $job_res['error']);
        json_response(['error' => 'Service temporarily unavailable.'], 503);
    }

    if (($job_res['status_code'] ?? 0) !== 200 || empty($job_res['data'][0])) {
        json_response(['error' => 'Job not found.'], 404);
    }

    $job = $job_res['data'][0];

    // ── PHP-level permission check ────────────────────────────────────────
    $is_creator           = ($job['creator_id'] === $actor_id);
    $is_privileged_role   = in_array($actor_role, ['admin', 'mod'], true);

    if (!$is_creator && !$is_privileged_role) {
        error_log(sprintf(
            '[Bounty][Security] Unauthorised payout attempt. actor=%s role=%s job_id=%s',
            $actor_id,
            $actor_role,
            $job_id
        ));
        json_response([
            'error' => 'Unauthorised: Only the job creator, an Admin, or a Moderator can release escrow.',
        ], 403);
    }

    // ── PHP-level escrow status check ─────────────────────────────────────
    // Fast-fail before the RPC round-trip if escrow is already released.
    if (($job['escrow_status'] ?? '') !== 'locked') {
        json_response([
            'error'   => 'Escrow cannot be released.',
            'detail'  => 'Current escrow status: ' . htmlspecialchars($job['escrow_status'] ?? 'unknown'),
        ], 400);
    }

    // ── Execute RPC ───────────────────────────────────────────────────────
    execute_payout_rpc($job_id, $applicant_id, $actor_id);

} else {
    // ── Mock fallback ─────────────────────────────────────────────────────
    execute_payout_mock($job_id, $applicant_id, $actor_id, $actor_role, $ctx);
}


// ── RPC execution (live Supabase) ─────────────────────────────────────────────

/**
 * Call public.hire_and_release_payout(...) via PostgREST RPC.
 *
 * SECURITY NOTE:
 *   • actor_id comes from server-side session — never from user input.
 *   • p_platform_fee_percent is a server constant (PLATFORM_FEE_PERCENT) —
 *     the client cannot influence the fee rate.
 *   • Service-role key is used so PostgREST honours the SECURITY DEFINER call.
 *   • Postgres performs the definitive auth + escrow-status check with a
 *     FOR UPDATE row lock, making concurrent double-release impossible.
 */
function execute_payout_rpc(string $job_id, string $applicant_id, string $actor_id): never
{
    $rpc_params = [
        'p_job_id'                => $job_id,
        'p_candidate_id'          => $applicant_id,
        'p_actor_id'              => $actor_id,
        'p_platform_fee_percent'  => PLATFORM_FEE_PERCENT,
    ];

    $res = supabase_rpc('hire_and_release_payout', $rpc_params, true); // true = service-role

    if (!empty($res['error'])) {
        error_log('[Bounty][RPC] hire_and_release_payout cURL error: ' . $res['error']);
        json_response(['error' => 'Service temporarily unavailable. Please try again.'], 503);
    }

    $http_code = $res['status_code'] ?? 0;
    $data      = $res['data']        ?? null;

    if ($http_code >= 400) {
        $pg_message = is_array($data) ? ($data['message'] ?? '') : (string)$data;

        // ── Classify Postgres exception messages ──────────────────────────
        // Map known RPC exception strings to user-safe responses. All unknown
        // exceptions are logged server-side and returned as a generic 500.

        if (stripos($pg_message, 'Unauthorized') !== false || stripos($pg_message, 'Unauthorised') !== false) {
            json_response(['error' => 'Unauthorised: Only the creator or an Admin/Mod can release payout.'], 403);
        }

        if (stripos($pg_message, 'not found') !== false) {
            json_response(['error' => 'Job not found or has been removed.'], 404);
        }

        if (stripos($pg_message, 'Escrow cannot be released') !== false || stripos($pg_message, 'status:') !== false) {
            // Extract status from: "Escrow cannot be released. Current status: released"
            preg_match('/status:\s*(\w+)/i', $pg_message, $m);
            json_response([
                'error'  => 'Escrow cannot be released.',
                'detail' => 'Current escrow status: ' . ($m[1] ?? 'unknown'),
            ], 400);
        }

        // Generic / unknown DB error — log full message, return safe response.
        error_log('[Bounty][RPC] hire_and_release_payout failed. HTTP ' . $http_code . ' — ' . $pg_message);
        json_response(['error' => 'Payout failed. Please contact support if the problem persists.'], 500);
    }

    // ── Success response ──────────────────────────────────────────────────
    // RPC returns JSONB:
    // { success, job_id, candidate_id, bounty_released, platform_fee, net_payout }
    json_response([
        'success'          => true,
        'job_id'           => $data['job_id']          ?? $job_id,
        'candidate_id'     => $data['candidate_id']    ?? $applicant_id,
        'bounty_released'  => $data['bounty_released'] ?? null,
        'platform_fee'     => $data['platform_fee']    ?? null,
        'net_payout'       => $data['net_payout']      ?? null,
        'platform_fee_pct' => PLATFORM_FEE_PERCENT,
        'message'          => 'Escrow released! ' . format_bdt((float)($data['net_payout'] ?? 0)) . ' transferred to the winner.',
    ]);
}


// ── Mock offline fallback ─────────────────────────────────────────────────────

/**
 * Simulate hire_and_release_payout() behaviour against the in-session mock DB.
 *
 * SECURITY NOTE: Balance updates here only affect session mock state.
 * No Supabase rows are modified. The same auth rules apply.
 */
function execute_payout_mock(
    string $job_id,
    string $applicant_id,
    string $actor_id,
    string $actor_role,
    array  $ctx
): never {
    // Reference to the mock jobs array for in-place mutation.
    $jobs = &$_SESSION['bounty_mock_db']['jobs'];

    // ── Find the job ──────────────────────────────────────────────────────
    $job_index = -1;
    foreach ($jobs as $idx => $job) {
        if ($job['id'] === $job_id) {
            $job_index = $idx;
            break;
        }
    }

    if ($job_index === -1) {
        json_response(['error' => 'Job not found.'], 404);
    }

    $job = &$jobs[$job_index];

    // ── Mock authorisation check ──────────────────────────────────────────
    $is_creator         = ($job['creator_id'] === $actor_id);
    $is_privileged_role = in_array($actor_role, ['admin', 'mod'], true);

    if (!$is_creator && !$is_privileged_role) {
        json_response([
            'error' => 'Unauthorised: Only the job creator, an Admin, or a Moderator can release escrow.',
        ], 403);
    }

    // ── Escrow status guard ───────────────────────────────────────────────
    if (($job['escrow_status'] ?? '') !== 'locked') {
        json_response([
            'error'  => 'Escrow cannot be released.',
            'detail' => 'Current escrow status: ' . ($job['escrow_status'] ?? 'unknown'),
        ], 400);
    }

    // ── Fee calculation (mirrors Postgres logic exactly) ──────────────────
    $bounty_amount = (float)($job['bounty_amount'] ?? 0);
    $fee           = round($bounty_amount * (PLATFORM_FEE_PERCENT / 100.0), 2);
    $net_payout    = round($bounty_amount - $fee, 2);

    // ── Mutate job record ─────────────────────────────────────────────────
    $job['escrow_status']      = 'released';
    $job['status']             = 'awarded';
    $job['hired_candidate_id'] = $applicant_id;

    // ── Resolve candidate display info from applications ──────────────────
    $candidate_name   = 'Hunter';
    $candidate_handle = $applicant_id; // fallback

    if (isset($_SESSION['bounty_mock_db']['applications'])) {
        foreach ($_SESSION['bounty_mock_db']['applications'] as &$app) {
            if ($app['job_id'] !== $job_id) {
                continue;
            }
            if ($app['candidate_id'] === $applicant_id) {
                $app['status']    = 'hired';
                $candidate_name   = $app['candidate_name']   ?? $candidate_name;
                $candidate_handle = $app['candidate_handle'] ?? $candidate_handle;
            } else {
                // Reject all other applicants for this job.
                if (($app['status'] ?? '') !== 'hired') {
                    $app['status'] = 'rejected';
                }
            }
        }
        unset($app); // break reference
    }

    // ── Credit candidate wallet in mock persona overrides ─────────────────
    // This only works for the mock persona whose UUID matches the applicant.
    // Maps hardcoded seed UUIDs to persona role keys.
    $uuid_to_mock_role = [
        '22222222-2222-2222-2222-222222222222' => 'recruiter',
        '33333333-3333-3333-3333-333333333333' => 'hunter',
        '44444444-4444-4444-4444-444444444444' => 'mod',
        '55555555-5555-5555-5555-555555555555' => 'support',
    ];

    if (isset($uuid_to_mock_role[$applicant_id])) {
        $role_key = $uuid_to_mock_role[$applicant_id];
        $personas = get_predefined_personas();
        $base_bal = $personas[$role_key]['wallet_balance'] ?? 0;
        $current  = $_SESSION['persona_overrides'][$role_key]['wallet_balance'] ?? $base_bal;
        $_SESSION['persona_overrides'][$role_key]['wallet_balance'] = round($current + $net_payout, 2);
    }

    // ── Lounge broadcast ──────────────────────────────────────────────────
    $_SESSION['bounty_mock_db']['chat'][] = [
        'id'            => 'chat-' . uniqid(),
        'sender_name'   => $ctx['display_name'] ?? 'System',
        'sender_handle' => $ctx['handle']       ?? 'system',
        'sender_role'   => $actor_role,
        'sender_avatar' => $ctx['avatar_url']   ?? '',
        'message'       => '🎉 Escrow Released! ' . format_bdt($bounty_amount)
            . ' awarded to @' . $candidate_handle
            . ' for "' . $job['title'] . '"'
            . ' (Net: ' . format_bdt($net_payout)
            . ', Platform Fee: ' . format_bdt($fee) . ')',
        'message_type'  => 'system_alert',
        'meta'          => [
            'job_id'     => $job_id,
            'winner'     => $candidate_handle,
            'net_payout' => $net_payout,
        ],
        'created_at' => date('H:i'),
    ];

    json_response([
        'success'          => true,
        'job_id'           => $job_id,
        'candidate_id'     => $applicant_id,
        'candidate_name'   => $candidate_name,
        'candidate_handle' => $candidate_handle,
        'bounty_released'  => $bounty_amount,
        'platform_fee'     => $fee,
        'net_payout'       => $net_payout,
        'platform_fee_pct' => PLATFORM_FEE_PERCENT,
        'message'          => 'Escrow released! ' . format_bdt($net_payout) . ' transferred to @' . $candidate_handle . '.',
    ]);
}
