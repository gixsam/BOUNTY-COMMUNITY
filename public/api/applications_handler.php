<?php
// public/api/applications_handler.php
// Handles applicant actions: shortlist, reject, hire (release escrow).

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed. Use POST.'], 405);
}

// Parse JSON payload
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST; // fallback
}

// CSRF validation
$submitted_csrf = trim($input['_csrf'] ?? '');
$session_csrf = $_SESSION['_csrf_token'] ?? '';
if ($session_csrf === '' || $submitted_csrf === '' || !hash_equals($session_csrf, $submitted_csrf)) {
    json_response(['error' => 'Invalid CSRF token.'], 403);
}
// Rotate token
$_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

$ctx = get_active_user_context();
if (empty($ctx['id'])) {
    json_response(['error' => 'Authentication required.'], 401);
}

$action = trim($input['action'] ?? '');
$job_id = sanitise_uuid($input['job_id'] ?? '');
$candidate_id = sanitise_uuid($input['candidate_id'] ?? '');

if (!$action || $job_id === null || $candidate_id === null) {
    json_response(['error' => 'Missing required parameters.'], 400);
}

$valid_actions = ['shortlist', 'reject', 'hire'];
if (!in_array($action, $valid_actions, true)) {
    json_response(['error' => 'Invalid action.'], 400);
}

// Fetch job to verify permissions and current state
$job_res = supabase_request(
    "rest/v1/jobs?id=eq.$job_id&select=creator_id,openings,openings_filled",
    'GET',
    null,
    true
);
if (!empty($job_res['error']) || $job_res['status_code'] !== 200) {
    json_response(['error' => 'Failed to fetch job details.'], 500);
}
$job = $job_res['data'][0] ?? null;
if (!$job) {
    json_response(['error' => 'Job not found.'], 404);
}

$is_admin = $ctx['role'] === 'admin';
$is_creator = $ctx['id'] === $job['creator_id'];
if (!($is_admin || $is_creator)) {
    json_response(['error' => 'Permission denied.'], 403);
}

// Fetch the specific application record
$app_res = supabase_request(
    "rest/v1/job_applications?job_id=eq.$job_id&candidate_id=eq.$candidate_id&select=id,status",
    'GET',
    null,
    true
);
if (!empty($app_res['error']) || $app_res['status_code'] !== 200) {
    json_response(['error' => 'Failed to fetch application.'], 500);
}
$app = $app_res['data'][0] ?? null;
if (!$app) {
    json_response(['error' => 'Application not found.'], 404);
}

$current_status = $app['status'] ?? 'applied';

if ($action === 'shortlist') {
    if (in_array($current_status, ['shortlisted', 'hired', 'rejected'])) {
        json_response(['error' => 'Cannot shortlist a ' . $current_status . ' application.'], 400);
    }
    $rpc_res = supabase_rpc('update_application_status', [
        'p_job_id' => $job_id,
        'p_candidate_id' => $candidate_id,
        'p_status' => 'shortlisted'
    ], true);
    if (!empty($rpc_res['error'])) {
        json_response(['error' => 'Failed to update status.'], 500);
    }
    json_response(['success' => true, 'message' => 'Applicant shortlisted.']);
}

if ($action === 'reject') {
    if (in_array($current_status, ['rejected', 'hired'])) {
        json_response(['error' => 'Cannot reject a ' . $current_status . ' application.'], 400);
    }
    $rpc_res = supabase_rpc('update_application_status', [
        'p_job_id' => $job_id,
        'p_candidate_id' => $candidate_id,
        'p_status' => 'rejected'
    ], true);
    if (!empty($rpc_res['error'])) {
        json_response(['error' => 'Failed to update status.'], 500);
    }
    json_response(['success' => true, 'message' => 'Applicant rejected.']);
}

if ($action === 'hire') {
    if ($current_status === 'hired') {
        json_response(['error' => 'Applicant already hired.'], 400);
    }
    if ($current_status === 'rejected') {
        json_response(['error' => 'Cannot hire a rejected applicant.'], 400);
    }
    // Ensure openings are still available
    if (($job['openings_filled'] ?? 0) >= ($job['openings'] ?? 0)) {
        json_response(['error' => 'All openings for this job are already filled.'], 400);
    }
    // Call the hire_and_release_payout RPC (service role)
    $rpc_res = supabase_rpc('hire_and_release_payout', [
        'p_job_id' => $job_id,
        'p_candidate_id' => $candidate_id,
        'p_actor_id' => $ctx['id']
    ], true);
    if (!empty($rpc_res['error'])) {
        json_response(['error' => 'Payout failed: ' . $rpc_res['error']], 500);
    }
    // Increment openings_filled via another RPC (if exists)
    $inc_res = supabase_rpc('increment_job_openings_filled', [
        'p_job_id' => $job_id
    ], true);
    if (!empty($inc_res['error'])) {
        error_log('[Bounty][applications_handler] Failed to increment openings_filled: ' . $inc_res['error']);
    }
    json_response(['success' => true, 'message' => 'Applicant hired and escrow released.']);
}
?>
