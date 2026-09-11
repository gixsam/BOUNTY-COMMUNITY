<?php
// public/cron/task_expirations.php
// Hostinger cron script: expire open jobs older than 48 hours and refund escrow to creators.

require_once __DIR__ . '/../config.php';

// Only run via CLI
if (php_sapi_name() !== 'cli') {
    exit('This script must be run from the command line.');
}

// Define expiration window (seconds)
$expiration_seconds = 48 * 60 * 60; // 48 hours

// Current timestamp
$now = time();

// Build Supabase filter: status=open and created_at < (now - 48h)
$cutoff = date('Y-m-d\TH:i:s\Z', $now - $expiration_seconds);

$filter = "status=eq.open&created_at=lt.$cutoff";
$endpoint = "rest/v1/job_posts?$filter&select=id,creator_id,bounty_amount,created_at";

$res = supabase_request($endpoint, 'GET', null, true);
if (!empty($res['error']) || $res['status_code'] !== 200) {
    error_log('[Cron][task_expirations] Failed to fetch expired jobs: ' . json_encode($res));
    exit(1);
}

$jobs = $res['data'] ?? [];
foreach ($jobs as $job) {
    $job_id = $job['id'] ?? null;
    if (!$job_id) continue;
    // Call cancellation helper (assumed to exist in utils)
    try {
        // The function should handle escrow refund and status update.
        cancel_job_and_refund_escrow($job_id);
        // Optional: log success
        error_log("[Cron][task_expirations] Expired job $job_id refunded.");
    } catch (Throwable $e) {
        error_log('[Cron][task_expirations] Error refunding job ' . $job_id . ': ' . $e->getMessage());
    }
}

?>
