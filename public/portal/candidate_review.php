<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — Candidate Review & 100-to-2 Screening Protocol
// Path: public/portal/candidate_review.php
// ==============================================================================

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
    render_header('Authentication Required', 'screening');
    ?>
    <div class="max-w-md mx-auto my-16 px-4">
        <div class="glass-card bg-[#121826]/85 backdrop-blur-xl border border-white/10 rounded-3xl p-8 text-center shadow-2xl">
            <div class="w-14 h-14 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center mx-auto mb-4 text-xl">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h2 class="text-xl font-bold text-white mb-2">Authentication Required</h2>
            <p class="text-xs text-slate-400 mb-6">You must be logged in to inspect candidate screening pipelines.</p>
            <a href="<?= BASE_URL ?>/portal/index.php" class="btn-stitch-primary inline-flex items-center gap-2 text-xs font-bold px-4 py-2.5 rounded-xl">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Return to Lounge</span>
            </a>
        </div>
    </div>
    <?php
    render_footer();
    exit;
}

// 1. Get job_id from query string or auto-select first active bounty
$raw_job_id = trim($_GET['job_id'] ?? '');
$job_id = !empty($raw_job_id) ? sanitise_uuid($raw_job_id) : null;

// If job_id is not given or invalid, attempt to auto-select the latest open bounty
if (empty($job_id)) {
    if (is_supabase_configured()) {
        $first_job_res = supabase_request("rest/v1/jobs?status=eq.open&order=created_at.desc&limit=1&select=id", 'GET', null, true);
        if (!empty($first_job_res['data'][0]['id'])) {
            $job_id = $first_job_res['data'][0]['id'];
        }
    }
    if (empty($job_id) && !empty($_SESSION['bounty_mock_db']['jobs'])) {
        $first_mock = reset($_SESSION['bounty_mock_db']['jobs']);
        $job_id = $first_mock['id'] ?? null;
    }
}

// 2. Fetch job details from Supabase or mock database
$job = null;
if (is_supabase_configured() && $job_id) {
    $job_res = supabase_request(
        "rest/v1/jobs?id=eq.$job_id&select=id,title,category,bounty_amount,total_escrow_locked,openings,openings_filled,screening_questions,creator_id,status",
        'GET',
        null,
        true
    );
    if (!empty($job_res['data'][0])) {
        $job = $job_res['data'][0];
    }
}

// Fallback to in-session mock DB
if (!$job && !empty($_SESSION['bounty_mock_db']['jobs'])) {
    foreach ($_SESSION['bounty_mock_db']['jobs'] as $mJob) {
        if ($mJob['id'] === $job_id || empty($job_id)) {
            $job = $mJob;
            $job_id = $mJob['id'];
            break;
        }
    }
}

// 3. Elegant Google Stitch Empty State if no job exists or is selected
if (!$job) {
    render_header('Candidate Review & 100-to-2 Screening', 'screening');
    ?>
    <div class="bg-[#121826]/75 border border-slate-700/60 rounded-2xl p-10 max-w-xl mx-auto mt-12 text-center shadow-2xl relative overflow-hidden">
        <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        
        <div class="w-16 h-16 rounded-2xl bg-indigo-500/10 border border-indigo-500/25 text-indigo-400 flex items-center justify-center mx-auto mb-4 text-2xl shadow-glow-indigo">
            <i class="fa-solid fa-users"></i>
        </div>
        
        <h2 class="text-xl font-bold text-white mb-2 tracking-tight">No Bounty Selected for Screening</h2>
        <p class="text-xs sm:text-sm text-slate-400 mb-6 leading-relaxed max-w-md mx-auto">
            Select an active bounty from the Job Hub pipeline to review candidates, screen proposals, and disburse escrow payouts.
        </p>
        
        <div class="flex flex-wrap items-center justify-center gap-3">
            <a href="<?= BASE_URL ?>/portal/job_hub.php" class="btn-stitch-primary px-5 py-2.5 rounded-xl text-xs font-bold inline-flex items-center gap-2 shadow-glow-indigo">
                <i class="fa-solid fa-briefcase"></i>
                <span>Open Job Hub & ATS</span>
            </a>
            <a href="<?= BASE_URL ?>/portal/index.php" class="px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 text-xs font-semibold border border-white/10 transition inline-flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Lounge Feed</span>
            </a>
        </div>
    </div>
    <?php
    render_footer();
    exit;
}

// 4. Fetch applicants with profile details
$applications = [];
if (is_supabase_configured() && $job_id) {
    $app_res = supabase_request(
        "rest/v1/job_applications?job_id=eq.$job_id&select=*,candidate:candidate_id(id,display_name,handle,avatar_url,reputation_score,completed_tasks,portfolio_url,answers)",
        'GET',
        null,
        true
    );
    if (!empty($app_res['data']) && is_array($app_res['data'])) {
        $applications = $app_res['data'];
    }
}

// Seed mock applicants if none returned from Supabase
if (empty($applications)) {
    $applications = [
        [
            'id'         => 'app-1',
            'status'     => 'applied',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'candidate'  => [
                'id'               => 'c1',
                'display_name'     => 'Tariqul Islam',
                'handle'           => 'tariq_dev',
                'avatar_url'       => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150',
                'reputation_score' => 880,
                'completed_tasks'  => 14,
                'portfolio_url'    => 'https://github.com/tariq-dev',
                'answers'          => [
                    'Built 4 high-throughput Hostinger PHP & PostgREST integrations with strict JWT verification.',
                    'Production repo link: github.com/tariq-dev/supabase-php-engine with 99.8% test coverage.'
                ]
            ]
        ],
        [
            'id'         => 'app-2',
            'status'     => 'shortlisted',
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 hours')),
            'candidate'  => [
                'id'               => 'c2',
                'display_name'     => 'Nusrat Jahan',
                'handle'           => 'nusrat_arch',
                'avatar_url'       => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=150',
                'reputation_score' => 940,
                'completed_tasks'  => 22,
                'portfolio_url'    => 'https://github.com/nusrat-jahan',
                'answers'          => [
                    'Specialist in atomic PostgreSQL RPC locking and zero-trust escrow disbursement architectures.',
                    'Live demo available on staging cluster with Web Audio celebratory feedback.'
                ]
            ]
        ],
        [
            'id'         => 'app-3',
            'status'     => 'applied',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'candidate'  => [
                'id'               => 'c3',
                'display_name'     => 'Arif Chowdhury',
                'handle'           => 'arif_fullstack',
                'avatar_url'       => 'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?w=150',
                'reputation_score' => 720,
                'completed_tasks'  => 9,
                'portfolio_url'    => 'https://github.com/arif-chowdhury',
                'answers'          => [
                    '5 years of backend PHP & modern Tailwind CSS frontends.',
                    'Experienced in cURL session hardening and CSRF token protections.'
                ]
            ]
        ]
    ];
}

// 5. Sort applications: highest reputation descending, then earliest submission
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

// 6. Filter by application status
$filter_status = $_GET['status'] ?? '';
$valid_statuses = ['applied', 'shortlisted', 'hired', 'rejected'];
if ($filter_status && in_array($filter_status, $valid_statuses, true)) {
    $applications = array_filter($applications, function($app) use ($filter_status) {
        return ($app['status'] ?? 'applied') === $filter_status;
    });
}

$positions_filled = ((int)($job['openings_filled'] ?? 0)) >= ((int)($job['openings'] ?? 1));
$bountyReward = (float)($job['bounty_amount'] ?? $job['total_escrow_locked'] ?? 1500);

render_header('Candidate Review & 100-to-2 Screening', 'screening');
?>

<div class="max-w-5xl mx-auto space-y-6">

    <!-- Top Breadcrumb & Title Row -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1.5">
                <a href="<?= BASE_URL ?>/portal/job_hub.php" class="text-xs font-mono text-brandIndigo hover:underline flex items-center gap-1">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i> Back to Job Hub
                </a>
                <span class="text-slate-600">•</span>
                <span class="text-xs font-mono text-slate-400">100-to-2 Smart Screening</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-users-viewfinder text-brandIndigo"></i>
                <span>Review Pipeline: <?= htmlspecialchars($job['title'] ?? 'Bounty Task') ?></span>
            </h1>
        </div>

        <!-- Bounty Escrow Badge (BDT Currency) -->
        <div class="flex items-center gap-3">
            <div class="coin-pill">
                <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="8" cy="8" r="6"/>
                    <path d="M18.09 10.37A6 6 0 1 1 10.34 18"/>
                    <path d="m7 6 2 2-2 2"/>
                    <path d="m17 16 2 2-2 2"/>
                </svg>
                <span class="font-mono font-bold"><?= format_bdt($bountyReward) ?></span>
                <span class="text-[10px] uppercase font-mono px-1 rounded bg-emerald-500/20 text-emerald-300 font-bold">Escrow</span>
            </div>
        </div>
    </div>

    <!-- Filter & ATS Controls Bar -->
    <div class="glass-card bg-[#121826]/75 backdrop-blur-md rounded-2xl p-4 border border-white/10 flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="text-xs font-mono text-slate-400 uppercase tracking-wider">Filter Pipeline:</span>
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="?job_id=<?= urlencode($job_id) ?>" 
                   class="px-3 py-1.5 rounded-xl text-xs font-mono transition <?= empty($filter_status) ? 'bg-brandIndigo text-white font-bold shadow-glow-indigo' : 'bg-white/5 text-slate-400 hover:text-white hover:bg-white/10' ?>">
                    All (<?= count($applications) ?>)
                </a>
                <?php foreach ($valid_statuses as $st): ?>
                    <a href="?job_id=<?= urlencode($job_id) ?>&status=<?= $st ?>" 
                       class="px-3 py-1.5 rounded-xl text-xs font-mono capitalize transition <?= ($filter_status === $st) ? 'bg-brandIndigo text-white font-bold shadow-glow-indigo' : 'bg-white/5 text-slate-400 hover:text-white hover:bg-white/10' ?>">
                        <?= $st ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="text-xs font-mono text-slate-400">
            Vacancies: <strong class="text-white"><?= (int)($job['openings_filled'] ?? 0) ?></strong> / <?= (int)($job['openings'] ?? 1) ?> Filled
        </div>
    </div>

    <?php if ($positions_filled): ?>
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-mono flex items-center gap-2.5">
            <i class="fa-solid fa-triangle-exclamation text-base text-amber-400"></i>
            <span>All openings for this bounty have been filled. Further hiring is locked.</span>
        </div>
    <?php endif; ?>

    <!-- Applicants Accordion List -->
    <div id="applicants-list" class="space-y-4">
        <?php if (empty($applications)): ?>
            <div class="glass-card bg-[#121826]/75 backdrop-blur-md rounded-2xl p-8 text-center border border-white/10">
                <p class="text-sm text-slate-400">No applicants found matching this filter criteria.</p>
            </div>
        <?php else: ?>
            <?php foreach ($applications as $app): ?>
                <?php
                $cand = $app['candidate'] ?? [];
                $status = $app['status'] ?? 'applied';
                $avatar = $cand['avatar_url'] ?? 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150';
                $statusBadgeClass = match($status) {
                    'hired'       => 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
                    'shortlisted' => 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30',
                    'rejected'    => 'bg-rose-500/20 text-rose-400 border-rose-500/30',
                    default       => 'bg-slate-700/50 text-slate-300 border-white/10',
                };
                ?>
                <details class="glass-card bg-[#121826]/75 backdrop-blur-md rounded-2xl p-5 border border-white/10 hover:border-indigo-500/30 transition-all group">
                    <summary class="flex flex-wrap items-center justify-between gap-4 cursor-pointer list-none select-none">
                        <div class="flex items-center gap-3.5">
                            <img src="<?= htmlspecialchars($avatar) ?>" alt="Avatar" class="w-11 h-11 rounded-xl object-cover border border-white/10">
                            <div>
                                <div class="font-bold text-white text-sm flex items-center gap-2">
                                    <?= htmlspecialchars($cand['display_name'] ?? 'Candidate') ?>
                                    <span class="text-xs font-mono text-slate-400">@<?= htmlspecialchars($cand['handle'] ?? 'user') ?></span>
                                </div>
                                <div class="text-xs text-slate-400 font-mono flex items-center gap-2 mt-0.5">
                                    <span class="text-amber-400 font-bold">★ <?= htmlspecialchars($cand['reputation_score'] ?? 0) ?> pts</span>
                                    <span>•</span>
                                    <span><?= htmlspecialchars($cand['completed_tasks'] ?? 0) ?> tasks completed</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="text-[10px] font-mono uppercase px-2.5 py-1 rounded-lg border font-bold <?= $statusBadgeClass ?>">
                                <?= ucfirst($status) ?>
                            </span>
                            <i class="fa-solid fa-chevron-down text-xs text-slate-400 group-open:rotate-180 transition-transform duration-200"></i>
                        </div>
                    </summary>

                    <!-- Accordion Body: Responses, Portfolio & Actions -->
                    <div class="mt-4 pt-4 border-t border-white/5 space-y-4">
                        <?php 
                        $answers = $cand['answers'] ?? [];
                        if (!is_array($answers)) {
                            $answers = [$answers];
                        }
                        ?>
                        <div class="bg-white/5 rounded-xl p-4 space-y-2 border border-white/5">
                            <div class="text-xs font-mono uppercase text-slate-400 font-bold">Screening Responses:</div>
                            <?php foreach ($answers as $idx => $ans): ?>
                                <div class="text-xs text-slate-300 leading-relaxed">
                                    <strong class="text-indigo-300 font-mono">Q<?= $idx + 1 ?>:</strong> <?= htmlspecialchars($ans) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-slate-400 font-mono">Portfolio / Proof:</span>
                                <a href="<?= htmlspecialchars($cand['portfolio_url'] ?? '#') ?>" target="_blank" class="text-brandIndigo hover:underline font-mono flex items-center gap-1">
                                    <span><?= htmlspecialchars($cand['portfolio_url'] ?? 'github.com/candidate') ?></span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                </a>
                            </div>

                            <!-- ATS Action Controls -->
                            <div class="flex items-center gap-2">
                                <?php if ($status !== 'shortlisted' && $status !== 'hired' && $status !== 'rejected'): ?>
                                    <button class="shortlist-btn px-3 py-1.5 rounded-xl bg-indigo-500/20 hover:bg-indigo-500/30 text-indigo-300 text-xs font-bold border border-indigo-500/30 transition cursor-pointer" 
                                            data-candidate-id="<?= htmlspecialchars($cand['id'] ?? '') ?>" data-action="shortlist">
                                        Shortlist
                                    </button>
                                <?php endif; ?>

                                <?php if ($status !== 'rejected' && $status !== 'hired'): ?>
                                    <button class="reject-btn px-3 py-1.5 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 text-xs font-bold border border-rose-500/30 transition cursor-pointer" 
                                            data-candidate-id="<?= htmlspecialchars($cand['id'] ?? '') ?>" data-action="reject">
                                        Reject
                                    </button>
                                <?php endif; ?>

                                <?php if (!$positions_filled && $status !== 'hired' && $status !== 'rejected'): ?>
                                    <button class="hire-btn px-4 py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 text-slate-950 text-xs font-bold shadow-glow-mint transition flex items-center gap-1.5 cursor-pointer" 
                                            data-candidate-id="<?= htmlspecialchars($cand['id'] ?? '') ?>" data-action="hire">
                                        <i class="fa-solid fa-star"></i>
                                        <span>Hire & Disburse</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
    const csrfToken = "<?= $csrf_token ?>";
    const currentJobId = "<?= htmlspecialchars($job_id) ?>";

    async function postCandidateAction(action, candidateId) {
        if (!candidateId) return;
        try {
            const payload = {
                _csrf: csrfToken,
                action: action,
                job_id: currentJobId,
                candidate_id: candidateId
            };
            const res = await fetch('<?= BASE_URL ?>/api/applications_handler.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Action failed');
                window.location.reload();
            }
        } catch (err) {
            console.error(err);
            window.location.reload();
        }
    }

    document.querySelectorAll('.shortlist-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            postCandidateAction('shortlist', btn.dataset.candidateId);
        });
    });

    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            postCandidateAction('reject', btn.dataset.candidateId);
        });
    });

    document.querySelectorAll('.hire-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            if (confirm('Confirm hiring and atomic disbursement of escrow reward?')) {
                postCandidateAction('hire', btn.dataset.candidateId);
            }
        });
    });
</script>

<?php
render_footer();
