<?php
// public/portal/candidate_review.php
// Candidate review page for bounty community – displays applicants for a given job and allows admin/recruiter actions.

require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=utf-8');

// Generate CSRF token if not present
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['_csrf_token'];

// Resolve active user context
$ctx = get_active_user_context();
if (empty($ctx['id'])) {
    // Not logged in – redirect to login or show error
    echo '<p class="text-red-500">You must be logged in to view this page.</p>';
    exit;
}

// Get job_id from query string
$raw_job_id = trim($_GET['job_id'] ?? '');
$job_id = sanitise_uuid($raw_job_id);
if ($job_id === null) {
    echo '<p class="text-red-500">Invalid job identifier.</p>';
    exit;
}

// Fetch job details – admin can view any, otherwise ensure ownership
$job_res = supabase_request(
    "rest/v1/jobs?id=eq.$job_id&select=total_escrow_locked,openings,openings_filled,screening_questions,creator_id",
    'GET',
    null,
    true // service role for full data
);
if (!empty($job_res['error']) || $job_res['status_code'] !== 200) {
    echo '<p class="text-red-500">Failed to load job details.</p>';
    exit;
}
$job = $job_res['data'][0] ?? null;
if (!$job) {
    echo '<p class="text-red-500">Job not found.</p>';
    exit;
}

// Permission check – admin or creator of job
$is_admin = $ctx['role'] === 'admin';
$is_creator = $ctx['id'] === $job['creator_id'];
if (!($is_admin || $is_creator)) {
    echo '<p class="text-red-500">You do not have permission to review this job.</p>';
    exit;
}

// Fetch applicants with profile details (assuming a view/job_applications_with_profile exists)
$app_res = supabase_request(
    "rest/v1/job_applications?job_id=eq.$job_id&select=*,candidate:candidate_id(display_name,handle,avatar_url,reputation_score,completed_tasks,portfolio_url,answers)",
    'GET',
    null,
    true
);
if (!empty($app_res['error']) || $app_res['status_code'] !== 200) {
    echo '<p class="text-red-500">Failed to load applications.</p>';
    exit;
}
$applications = $app_res['data'];

// Helper: sort applications by reputation descending, then earliest submission
usort($applications, function($a, $b) {
    $repA = $a['candidate']['reputation_score'] ?? 0;
    $repB = $b['candidate']['reputation_score'] ?? 0;
    if ($repA !== $repB) {
        return $repB <=> $repA;
    }
    $dateA = strtotime($a['created_at'] ?? '');
    $dateB = strtotime($b['created_at'] ?? '');
    return $dateA <=> $dateB;
});

// Prepare filter options
$filter_status = $_GET['status'] ?? '';
$valid_statuses = ['applied', 'shortlisted', 'hired', 'rejected'];
if ($filter_status && !in_array($filter_status, $valid_statuses, true)) {
    $filter_status = '';
}
if ($filter_status) {
    $applications = array_filter($applications, function($app) use ($filter_status) {
        return ($app['status'] ?? 'applied') === $filter_status;
    });
}

$positions_filled = ($job['openings_filled'] ?? 0) >= ($job['openings'] ?? 0);

render_header('Candidate Review & 100-to-2 Screening', 'screening');
?>
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= BASE_URL ?>/portal/job_hub.php" class="text-xs font-mono text-brandIndigo hover:underline flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Bounties
                </a>
                <span class="text-slate-600">•</span>
                <span class="text-xs font-mono text-slate-400">100-to-2 Screening Protocol</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-users-viewfinder text-brandIndigo"></i>
                Review Applicants: <?= htmlspecialchars($job['title'] ?? 'Task Bounty') ?>
            </h1>
        </div>
    </div>
    <div class="flex items-center mb-4 space-x-4">
        <label class="inline-flex items-center">
            <span class="mr-2">Filter status:</span>
            <select id="statusFilter" class="bg-gray-800 text-white rounded">
                <option value="">All</option>
                <?php foreach ($valid_statuses as $st): ?>
                    <option value="<?= $st ?>" <?= ($st === $filter_status) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button id="applyFilter" class="px-4 py-2 bg-indigo-600 rounded hover:bg-indigo-700">Apply</button>
    </div>
    <?php if ($positions_filled): ?>
        <div class="mb-4 p-3 bg-amber-800/50 rounded">⚠️ All positions for this job have been filled. Hiring actions are disabled.</div>
    <?php endif; ?>
    <div id="applicants" class="space-y-4">
        <?php if (empty($applications)): ?>
            <p>No applicants match the selected criteria.</p>
        <?php else: ?>
            <?php foreach ($applications as $app): ?>
                <?php
                $cand = $app['candidate'];
                $status = $app['status'] ?? 'applied';
                $avatar = $cand['avatar_url'] ?? 'https://via.placeholder.com/40';
                ?>
                <details class="glass-card rounded-2xl p-5">
                    <summary class="flex items-center cursor-pointer">
                        <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="w-10 h-10 rounded-full mr-3">
                        <div class="flex-1">
                            <span class="font-medium"><?= htmlspecialchars($cand['display_name'] ?? $cand['handle']) ?></span>
                            <span class="text-sm text-gray-400 ml-2">@<?= htmlspecialchars($cand['handle']) ?></span>
                        </div>
                        <span class="text-sm bg-gray-700 px-2 py-1 rounded">Status: <?= ucfirst($status) ?></span>
                    </summary>
                    <div class="mt-3 space-y-2">
                        <p><strong>Reputation:</strong> <?= htmlspecialchars($cand['reputation_score'] ?? 0) ?></p>
                        <p><strong>Completed Tasks:</strong> <?= htmlspecialchars($cand['completed_tasks'] ?? 0) ?></p>
                        <p><strong>Portfolio:</strong> <a href="<?= htmlspecialchars($cand['portfolio_url'] ?? '#') ?>" target="_blank" class="text-indigo-400 underline">View</a></p>
                        <p><strong>Answers:</strong></p>
                        <?php $answers = $cand['answers'] ?? [];
                        $q1 = $answers[0] ?? '(no answer)';
                        $q2 = $answers[1] ?? '(no answer)'; ?>
                        <ul class="list-disc list-inside ml-4">
                            <li>Q1: <?= htmlspecialchars($q1) ?></li>
                            <li>Q2: <?= htmlspecialchars($q2) ?></li>
                        </ul>
                        <div class="flex space-x-2 mt-2">
                            <?php if (!$positions_filled && $status !== 'hired' && $status !== 'rejected'): ?>
                                <button class="hire-btn btn px-3 py-1 bg-green-600 hover:bg-green-700 rounded" data-candidate-id="<?= $cand['id'] ?? '' ?>" data-action="hire">⭐ Hire & Disburse</button>
                            <?php endif; ?>
                            <?php if ($status !== 'shortlisted' && $status !== 'hired' && $status !== 'rejected'): ?>
                                <button class="shortlist-btn btn px-3 py-1 bg-indigo-600 hover:bg-indigo-700 rounded" data-candidate-id="<?= $cand['id'] ?? '' ?>" data-action="shortlist">Shortlist</button>
                            <?php endif; ?>
                            <?php if ($status !== 'rejected' && $status !== 'hired'): ?>
                                <button class="reject-btn btn px-3 py-1 bg-red-600 hover:bg-red-700 rounded" data-candidate-id="<?= $cand['id'] ?? '' ?>" data-action="reject">Reject</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    const csrfToken = "<?= $csrf_token ?>";
    document.getElementById('applyFilter').addEventListener('click', () => {
        const status = document.getElementById('statusFilter').value;
        const url = new URL(window.location.href);
        if (status) {
            url.searchParams.set('status', status);
        } else {
            url.searchParams.delete('status');
        }
        window.location = url.toString();
    });

    async function postAction(action, candidateId) {
        const payload = {
            _csrf: csrfToken,
            action: action,
            job_id: "<?= $job_id ?>",
            candidate_id: candidateId
        };
        const res = await fetch('/api/applications_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!res.ok) {
            alert(data.error || 'Action failed');
        } else {
            window.location.reload();
        }
    }
    document.querySelectorAll('.shortlist-btn').forEach(btn => {
        btn.addEventListener('click', () => postAction('shortlist', btn.dataset.candidateId));
    });
    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', () => postAction('reject', btn.dataset.candidateId));
    });
    document.querySelectorAll('.hire-btn').forEach(btn => {
        btn.addEventListener('click', () => postAction('hire', btn.dataset.candidateId));
    });
</script>
</div>
<?php
render_footer();

