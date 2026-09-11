<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — api/jobs_handler.php
// RPC Target : public.post_job_with_escrow(...)   (SECURITY DEFINER)
// Hostinger PHP 8.2+ / Apache
// ------------------------------------------------------------------------------
// SECURITY BOUNDARIES:
//   • Rejects non-POST requests for state-changing operations.
//   • Validates CSRF token on every POST.
//   • Resolves caller identity via get_active_user_context() — never from
//     raw user input.
//   • Validates all required parameters (title, bounty_amount, openings) before
//     sending to Supabase. openings and reward must be > 0 per directive.
//   • NEVER executes raw SQL, PATCH/UPDATE on coin_balance or total_escrow_locked.
//     Balance mutation happens exclusively inside the SECURITY DEFINER RPC.
//   • Insufficient-balance exceptions from Postgres are parsed and returned
//     as structured JSON, never as raw database error strings.
//   • The service-role key is used for the RPC call (SECURITY DEFINER procedure
//     still needs the service-role header so PostgREST honours the call).
//   • GET requests for listing/fetching jobs use the anon key (RLS-filtered).
// ==============================================================================

require_once __DIR__ . '/../config.php';

// Always respond in JSON.
header('Content-Type: application/json; charset=utf-8');

// ── Routing ───────────────────────────────────────────────────────────────────

$method = strtoupper($_SERVER['REQUEST_METHOD']);

match ($method) {
    'GET'    => handle_get_jobs(),
    'POST'   => handle_post_job(),
    default  => json_response(['error' => 'Method not allowed. Use GET or POST.'], 405),
};


// ── GET: List / fetch jobs ────────────────────────────────────────────────────

/**
 * GET /api/jobs_handler.php
 * Optional params: ?id=<uuid>  or  ?status=<open|in_review|awarded|closed>
 *
 * Uses anon key — results are filtered by Supabase RLS based on the caller's
 * JWT. In mock mode, returns from in-session mock DB.
 */
function handle_get_jobs(): never
{
    if (is_supabase_configured()) {

        $raw_id     = trim($_GET['id']     ?? '');
        $raw_status = trim($_GET['status'] ?? '');

        // Validate an individual job UUID if provided.
        $job_id = $raw_id !== '' ? sanitise_uuid($raw_id) : null;
        if ($raw_id !== '' && $job_id === null) {
            json_response(['error' => 'Invalid job ID format.'], 400);
        }

        // Validate status filter against the allowed enum values.
        $allowed_statuses = ['open', 'in_review', 'awarded', 'closed'];
        $status = ($raw_status !== '' && in_array($raw_status, $allowed_statuses, true))
            ? $raw_status
            : null;

        if ($job_id !== null) {
            // Fetch a single job — include creator profile join.
            $endpoint = 'rest/v1/jobs'
                . '?id=eq.' . urlencode($job_id)
                . '&select=*,creator:creator_id(display_name,handle,avatar_url)'
                . '&limit=1';
        } else {
            $endpoint = 'rest/v1/jobs'
                . '?select=*,creator:creator_id(display_name,handle,avatar_url)'
                . '&order=created_at.desc'
                . '&limit=100';
            if ($status !== null) {
                $endpoint .= '&status=eq.' . urlencode($status);
            }
        }

        $res = supabase_request($endpoint, 'GET', null, false);

        if (!empty($res['error'])) {
            json_response(['error' => $res['error']], 502);
        }

        if ($res['status_code'] !== 200) {
            json_response([
                'error' => 'Failed to fetch jobs.',
                'detail' => $res['data']['message'] ?? null,
            ], $res['status_code']);
        }

        json_response([
            'success' => true,
            'count'   => is_array($res['data']) ? count($res['data']) : 0,
            'jobs'    => $res['data'] ?? [],
        ]);

    } else {
        // ── Mock fallback ────────────────────────────────────────────────────
        $jobs  = $_SESSION['bounty_mock_db']['jobs'] ?? [];
        $raw_id = trim($_GET['id'] ?? '');

        if ($raw_id !== '') {
            foreach ($jobs as $job) {
                if ($job['id'] === $raw_id) {
                    json_response(['success' => true, 'job' => $job]);
                }
            }
            json_response(['error' => 'Job not found.'], 404);
        }

        $status_filter = trim($_GET['status'] ?? '');
        if ($status_filter !== '') {
            $jobs = array_values(array_filter($jobs, fn($j) => ($j['status'] ?? '') === $status_filter));
        }

        json_response(['success' => true, 'count' => count($jobs), 'jobs' => $jobs]);
    }
}


// ── POST: Create job with locked escrow ──────────────────────────────────────

/**
 * POST /api/jobs_handler.php
 *
 * Expects JSON body:
 * {
 *   "_csrf"        : "<token>",
 *   "title"        : "string (required)",
 *   "description"  : "string",
 *   "category"     : "string",
 *   "bounty_amount": number > 0,
 *   "openings"     : integer > 0,
 *   "skills"       : ["string", ...] or "comma,separated",
 *   "deadline"     : "Y-m-d H:i:s" or ISO8601 (optional, defaults to +7 days)
 * }
 *
 * SECURITY NOTE: wallet_balance is NEVER mutated directly from PHP.
 * The SECURITY DEFINER RPC post_job_with_escrow() handles all balance
 * deductions and escrow ledger entries atomically within Postgres.
 */
function handle_post_job(): never
{
    // ── 1. Parse request body ──────────────────────────────────────────────
    // Accept JSON body (preferred) or form-encoded fallback.
    $raw   = file_get_contents('php://input');
    $input = [];

    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $input = $decoded;
        }
    }

    // Fallback to $_POST for traditional form submissions.
    if (empty($input)) {
        $input = $_POST;
    }

    // ── 2. CSRF validation ─────────────────────────────────────────────────
    // SECURITY: Every state-changing POST must carry a valid CSRF token that
    // matches the one stored server-side. We compare using hash_equals() to
    // prevent timing-based token guessing.
    $submitted_csrf = trim($input['_csrf'] ?? '');
    $session_csrf   = $_SESSION['_csrf_token'] ?? '';

    if ($session_csrf === '' || $submitted_csrf === '' || !hash_equals($session_csrf, $submitted_csrf)) {
        error_log('[Bounty][Security] jobs_handler.php: CSRF token mismatch from IP ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        json_response(['error' => 'Security token invalid or expired. Please refresh and try again.'], 403);
    }

    // Rotate CSRF token to prevent replay.
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

    // ── 3. Resolve active user context ────────────────────────────────────
    // Never use user-supplied identity. Always derive from session.
    $ctx = get_active_user_context();

    if (empty($ctx['id'])) {
        json_response(['error' => 'You must be logged in to post a bounty.'], 401);
    }

    // Only recruiters and admins are allowed to create jobs.
    $allowed_poster_roles = ['admin', 'recruiter'];
    if (!in_array($ctx['role'] ?? '', $allowed_poster_roles, true)) {
        json_response([
            'error' => 'Only Recruiters and Admins can post new bounties.',
            'your_role' => $ctx['role'],
        ], 403);
    }

    // ── 4. Parameter extraction & sanitisation ────────────────────────────

    $title       = trim((string)($input['title'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $category    = trim((string)($input['category'] ?? 'Engineering'));

    // bounty_amount (renamed from reward in the directive — maps to same field)
    $bounty_amount = isset($input['bounty_amount']) ? (float)$input['bounty_amount'] : 0.0;

    // openings: directive requires > 0. Stored as metadata; the RPC doesn't
    // take openings as a separate param so we validate it and embed it in notes.
    $openings = isset($input['openings']) ? (int)$input['openings'] : 1;

    // skills: accept array or comma-separated string.
    $raw_skills = $input['skills'] ?? [];
    if (is_string($raw_skills)) {
        $skills = array_values(array_filter(array_map('trim', explode(',', $raw_skills))));
    } elseif (is_array($raw_skills)) {
        $skills = array_values(array_filter(array_map('trim', $raw_skills)));
    } else {
        $skills = [];
    }

    // Sanitise each skill string: strip tags, truncate to 80 chars.
    $skills = array_map(fn($s) => mb_substr(strip_tags($s), 0, 80), $skills);
    // Max 20 skills.
    $skills = array_slice($skills, 0, 20);

    // Deadline: validate and normalise to ISO-8601 for Postgres TIMESTAMPTZ.
    $raw_deadline = trim((string)($input['deadline'] ?? ''));
    if ($raw_deadline !== '') {
        $ts = strtotime($raw_deadline);
        if ($ts === false || $ts <= time()) {
            json_response(['error' => 'Deadline must be a valid future date/time.'], 422);
        }
        $deadline = date('c', $ts); // ISO-8601 with timezone offset.
    } else {
        $deadline = date('c', strtotime('+7 days'));
    }

    // Category whitelist.
    $allowed_categories = [
        'Engineering', 'Backend Architecture', 'Frontend UI/UX', 'Full-Stack',
        'Cybersecurity', 'Data Science', 'Design', 'DevOps', 'Mobile', 'AI/ML', 'Other',
    ];
    if (!in_array($category, $allowed_categories, true)) {
        $category = 'Engineering';
    }

    // Title: truncate to 200 chars, strip tags.
    $title = mb_substr(strip_tags($title), 0, 200);

    // ── 5. Business rule validation ────────────────────────────────────────

    if ($title === '') {
        json_response(['error' => 'Bounty title is required.'], 422);
    }

    // Directive: reward > 0.
    if ($bounty_amount <= 0) {
        json_response(['error' => 'Bounty amount must be greater than ৳0.00.'], 422);
    }

    // Directive: openings > 0.
    if ($openings < 1) {
        json_response(['error' => 'Number of openings must be at least 1.'], 422);
    }

    // Minimum bounty floor (৳1.00) to prevent spam.
    if ($bounty_amount < 1.00) {
        json_response(['error' => 'Minimum bounty amount is ৳1.00.'], 422);
    }

    // Client-side balance pre-check for immediate feedback.
    // SECURITY NOTE: This is a UX guard ONLY. The authoritative balance check
    // is performed inside the Postgres SECURITY DEFINER RPC with a FOR UPDATE
    // row lock. The PHP check uses the balance from the active user context
    // which may be slightly stale — Postgres is the final arbiter.
    $wallet = (float)($ctx['wallet_balance'] ?? 0);
    if ($wallet < $bounty_amount) {
        json_response([
            'error'            => 'Insufficient wallet balance to lock escrow.',
            'current_balance'  => round($wallet, 2),
            'required'         => round($bounty_amount, 2),
            'shortfall'        => round($bounty_amount - $wallet, 2),
        ], 400);
    }

    // ── 6. Execute: Supabase RPC or mock fallback ──────────────────────────

    if (is_supabase_configured()) {
        handle_post_job_supabase($ctx, $title, $description, $category, $bounty_amount, $skills, $deadline, $openings);
    } else {
        handle_post_job_mock($ctx, $title, $description, $category, $bounty_amount, $skills, $deadline, $openings);
    }
    // Unreachable — sub-functions call json_response() which exits.
    exit;
}


// ── Supabase live path ────────────────────────────────────────────────────────

/**
 * Invoke the post_job_with_escrow RPC on Supabase.
 *
 * SECURITY NOTE: We pass $ctx['id'] — derived server-side from the validated
 * session — as p_creator_id. The caller cannot substitute their own UUID via
 * the POST body; that field is not read from $input at all.
 *
 * The service-role key is required to execute SECURITY DEFINER functions via
 * PostgREST's /rpc/ endpoint when called from server-side PHP.
 */
function handle_post_job_supabase(
    array  $ctx,
    string $title,
    string $description,
    string $category,
    float  $bounty_amount,
    array  $skills,
    string $deadline,
    int    $openings
): never {
    $rpc_params = [
        'p_creator_id'      => $ctx['id'],
        'p_title'           => $title,
        'p_description'     => $description . ($openings > 1 ? "\n\n[Openings: {$openings}]" : ''),
        'p_category'        => $category,
        'p_bounty_amount'   => $bounty_amount,
        'p_skills_required' => $skills,
        'p_deadline'        => $deadline,
    ];

    $res = supabase_rpc('post_job_with_escrow', $rpc_params, true); // true = service-role

    // ── Parse Postgres exceptions ─────────────────────────────────────────
    // Postgres RAISE EXCEPTION surfaces as a 4xx/5xx HTTP status from PostgREST
    // with a JSON body: { "code": "P0001", "message": "...", "hint": null }
    // We intercept insufficient-balance messages and return a structured,
    // user-safe response. ALL other DB errors return a generic server error.
    if (!empty($res['error'])) {
        // Network / cURL level error.
        error_log('[Bounty][RPC] post_job_with_escrow cURL error: ' . $res['error']);
        json_response(['error' => 'Service temporarily unavailable. Please try again.'], 503);
    }

    $http_code = $res['status_code'] ?? 0;
    $data      = $res['data'] ?? null;

    if ($http_code >= 400) {
        $pg_message = is_array($data) ? ($data['message'] ?? '') : (string)$data;

        // Detect insufficient-balance exception from the RPC body.
        if (stripos($pg_message, 'Insufficient balance') !== false) {
            // Extract balances from the message if present.
            preg_match('/Balance: ([\d.]+)/', $pg_message, $bal_m);
            preg_match('/Required: ([\d.]+)/', $pg_message, $req_m);
            json_response([
                'error'           => 'Insufficient wallet balance to lock escrow.',
                'current_balance' => isset($bal_m[1]) ? (float)$bal_m[1] : null,
                'required'        => isset($req_m[1]) ? (float)$req_m[1] : $bounty_amount,
            ], 400);
        }

        if (stripos($pg_message, 'Creator profile not found') !== false) {
            json_response(['error' => 'Your account profile could not be found. Please log in again.'], 401);
        }

        // Generic RPC failure — log the full message server-side, return a safe message.
        error_log('[Bounty][RPC] post_job_with_escrow failed. HTTP ' . $http_code . ' — ' . $pg_message);
        json_response(['error' => 'Failed to create bounty. Please try again later.'], 500);
    }

    // Success — the RPC returns a JSONB object.
    // Shape: { success: true, job_id: uuid, escrow_amount: numeric, remaining_balance: numeric }
    json_response([
        'success'           => true,
        'job_id'            => $data['job_id']            ?? null,
        'escrow_amount'     => $data['escrow_amount']      ?? $bounty_amount,
        'remaining_balance' => $data['remaining_balance']  ?? null,
        'message'           => 'Bounty created and ' . format_bdt($bounty_amount) . ' locked in Escrow!',
    ], 201);
}


// ── Mock offline fallback ─────────────────────────────────────────────────────

/**
 * Persist a new job into the in-session mock database.
 *
 * Mirrors the exact same validation + balance deduction logic that
 * post_job_with_escrow() performs in Postgres, so the UX is identical
 * in both online and offline modes.
 *
 * SECURITY NOTE: Balance deduction uses update_active_persona_balance()
 * which only modifies the mock session state — NOT any real Supabase row.
 */
function handle_post_job_mock(
    array  $ctx,
    string $title,
    string $description,
    string $category,
    float  $bounty_amount,
    array  $skills,
    string $deadline,
    int    $openings
): never {
    // Re-validate balance in mock mode (same as Postgres check).
    $wallet = (float)($ctx['wallet_balance'] ?? 0);
    if ($wallet < $bounty_amount) {
        json_response([
            'error'           => 'Insufficient wallet balance to lock escrow.',
            'current_balance' => round($wallet, 2),
            'required'        => round($bounty_amount, 2),
        ], 400);
    }

    $new_job_id = 'job-' . (count($_SESSION['bounty_mock_db']['jobs'] ?? []) + 101);
    $now        = date('Y-m-d H:i:s');

    $new_job = [
        'id'               => $new_job_id,
        'creator_id'       => $ctx['id'],
        'creator_name'     => $ctx['display_name'],
        'creator_handle'   => $ctx['handle'],
        'title'            => $title,
        'description'      => $description . ($openings > 1 ? "\n\n[Openings: {$openings}]" : ''),
        'category'         => $category,
        'bounty_amount'    => $bounty_amount,
        'escrow_status'    => 'locked',
        'status'           => 'open',
        'skills_required'  => $skills,
        'deadline'         => date('Y-m-d H:i', strtotime($deadline)),
        'created_at'       => $now,
        'applicants_count' => 0,
    ];

    // Deduct escrow from mock persona balance (session-only; does NOT touch Supabase).
    $remaining = update_active_persona_balance(-$bounty_amount);

    // Prepend job to top of mock feed.
    array_unshift($_SESSION['bounty_mock_db']['jobs'], $new_job);

    // Broadcast to lounge chat in mock DB.
    $_SESSION['bounty_mock_db']['chat'][] = [
        'id'           => 'chat-' . uniqid(),
        'sender_name'  => $ctx['display_name'],
        'sender_handle' => $ctx['handle'],
        'sender_role'  => $ctx['role'],
        'sender_avatar' => $ctx['avatar_url'] ?? '',
        'message'      => '🚀 New Bounty Broadcast: ' . $title . ' (' . format_bdt($bounty_amount) . ') locked in Escrow!',
        'message_type' => 'job_broadcast',
        'meta'         => ['job_id' => $new_job_id, 'bounty' => $bounty_amount, 'title' => $title],
        'created_at'   => date('H:i'),
    ];

    json_response([
        'success'           => true,
        'job_id'            => $new_job_id,
        'escrow_amount'     => $bounty_amount,
        'remaining_balance' => $remaining,
        'message'           => 'Bounty created and ' . format_bdt($bounty_amount) . ' locked in Escrow!',
    ], 201);
}
