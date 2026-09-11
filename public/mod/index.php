<?php
// ==============================================================================
// TASK VERIFICATION QUEUE & THREAT PATROL
// Path: public/mod/index.php
// ==============================================================================

require_once __DIR__ . '/../config.php';

// Route Protection: Mod, Founder & Admin only
$currentRole = $_SESSION['user_role'] ?? '';
if (!in_array($currentRole, ['mod', 'founder', 'admin'], true)) {
    header('Location: ' . (empty(BASE_URL) ? '/staff/login' : BASE_URL . '/mod/login.php'));
    exit;
}

$persona = get_active_persona();
$verifications = &$_SESSION['bounty_mock_db']['verifications'];

// Handle quick mod actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $verId = $_POST['verification_id'] ?? '';

    foreach ($verifications as &$ver) {
        if ($ver['id'] === $verId) {
            if ($action === 'approve') {
                $ver['status'] = 'approved';
            } elseif ($action === 'flag') {
                $ver['status'] = 'flagged';
                $ver['threat_score'] = max(75, $ver['threat_score']);
            } elseif ($action === 'reject') {
                $ver['status'] = 'rejected';
            }
            break;
        }
    }
    header('Location: ' . BASE_URL . '/mod/index.php');
    exit;
}

render_header('Threat Patrol & Task Verification Queue', 'mod');
?>

<div class="space-y-6">

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                <span class="text-xs font-mono uppercase text-rose-400 font-bold">Threat Patrol Active</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-shield-halved text-rose-500"></i>
                Task Verification Queue & Threat Patrol
            </h1>
            <p class="text-sm text-slate-400">
                Inspect deliverable proofs, run automated link threat heuristics, and protect escrow disbursements.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <div class="px-3.5 py-2 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 font-mono text-xs flex items-center gap-2">
                <i class="fa-solid fa-radar"></i>
                <span>Zero Trust Active</span>
            </div>
        </div>
    </div>

    <!-- Threat Radar Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="glass-card p-4 rounded-2xl">
            <div class="text-[11px] font-mono uppercase text-slate-400">Awaiting Verification</div>
            <div class="text-2xl font-black text-amber-400 font-mono">
                <?= count(array_filter($verifications, fn($v) => $v['status'] === 'pending')) ?> Proofs
            </div>
        </div>
        <div class="glass-card p-4 rounded-2xl">
            <div class="text-[11px] font-mono uppercase text-slate-400">Threat Flags (Spam / Phish)</div>
            <div class="text-2xl font-black text-rose-400 font-mono">0 Critical</div>
        </div>
        <div class="glass-card p-4 rounded-2xl">
            <div class="text-[11px] font-mono uppercase text-slate-400">Verified & Released</div>
            <div class="text-2xl font-black text-emerald-400 font-mono">
                <?= count(array_filter($verifications, fn($v) => $v['status'] === 'approved')) ?> Verified
            </div>
        </div>
    </div>

    <!-- Verification Queue List -->
    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="p-4 border-b border-white/5 flex items-center justify-between">
            <h2 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-slate-400"></i>
                Proof Submissions Queue
            </h2>
            <span class="text-xs font-mono text-slate-400">Hostinger Sandbox Protected</span>
        </div>

        <div class="divide-y divide-white/5">
            <?php foreach ($verifications as $ver): ?>
                <?php 
                    $isPending = ($ver['status'] === 'pending');
                    $isApproved = ($ver['status'] === 'approved');
                    $isFlagged = ($ver['status'] === 'flagged');
                ?>
                <div class="p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-6 hover:bg-white/5 transition">
                    <div class="space-y-2 flex-1">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-mono font-bold text-white px-2 py-0.5 rounded bg-white/5 border border-white/10">
                                #<?= htmlspecialchars($ver['id']) ?>
                            </span>
                            <span class="text-xs text-slate-400 font-mono">
                                Task: <strong class="text-slate-200"><?= htmlspecialchars($ver['job_title']) ?></strong>
                            </span>
                            <span class="text-xs text-slate-500">•</span>
                            <span class="text-xs text-slate-400 font-mono">Submitted <?= htmlspecialchars($ver['submitted_at']) ?></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-white"><?= htmlspecialchars($ver['candidate_name']) ?></span>
                            <span class="text-xs font-mono text-slate-400">(@<?= htmlspecialchars($ver['candidate_handle']) ?>)</span>
                        </div>

                        <div class="text-xs text-slate-300 leading-relaxed bg-white/5 p-3 rounded-xl border border-white/5">
                            <?= htmlspecialchars($ver['proof_notes']) ?>
                        </div>

                        <!-- Proof Link & Threat Score Heuristic -->
                        <div class="flex flex-wrap items-center gap-4 text-xs pt-1">
                            <div class="flex items-center gap-1.5 font-mono">
                                <span class="text-slate-400">Deliverable URL:</span>
                                <a href="<?= htmlspecialchars($ver['proof_url']) ?>" target="_blank" class="text-brandIndigo hover:underline flex items-center gap-1">
                                    <span><?= htmlspecialchars($ver['proof_url']) ?></span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                                </a>
                            </div>

                            <div class="flex items-center gap-1 font-mono">
                                <span class="text-slate-400">Threat Score:</span>
                                <span class="px-2 py-0.2 rounded font-bold <?= $ver['threat_score'] > 50 ? 'bg-rose-500/20 text-rose-400' : 'bg-emerald-500/20 text-emerald-400' ?>">
                                    <?= $ver['threat_score'] ?> / 100 <?= $ver['threat_score'] > 50 ? '(High Risk)' : '(Clean)' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Mod Action Controls -->
                    <div class="flex items-center gap-2 shrink-0">
                        <?php if ($isPending): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="verification_id" value="<?= $ver['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="px-4 py-2 rounded-xl bg-brandMint hover:bg-emerald-400 text-slate-950 font-bold text-xs shadow-glow-mint transition flex items-center gap-1.5">
                                    <i class="fa-solid fa-check-circle"></i>
                                    <span>Approve Deliverable</span>
                                </button>
                            </form>

                            <form method="POST" class="inline">
                                <input type="hidden" name="verification_id" value="<?= $ver['id'] ?>">
                                <input type="hidden" name="action" value="flag">
                                <button type="submit" class="px-3.5 py-2 rounded-xl bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 font-bold text-xs border border-rose-500/30 transition flex items-center gap-1.5">
                                    <i class="fa-solid fa-flag"></i>
                                    <span>Flag Dispute</span>
                                </button>
                            </form>
                        <?php elseif ($isApproved): ?>
                            <span class="px-3 py-1.5 rounded-xl bg-brandMint/20 text-brandMint font-mono text-xs font-bold border border-brandMint/30 flex items-center gap-1.5">
                                <i class="fa-solid fa-circle-check"></i> Approved & Cleared
                            </span>
                        <?php elseif ($isFlagged): ?>
                            <span class="px-3 py-1.5 rounded-xl bg-rose-500/20 text-rose-300 font-mono text-xs font-bold border border-rose-500/30 flex items-center gap-1.5">
                                <i class="fa-solid fa-triangle-exclamation"></i> Flagged for Escrow Hold
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php
render_footer();
?>
