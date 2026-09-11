<?php
// ==============================================================================
// JOB HUB & RECRUITER ATS
// Path: public/portal/job_hub.php
// ==============================================================================

require_once __DIR__ . '/../config.php';

$persona = get_active_persona();
$jobs = $_SESSION['bounty_mock_db']['jobs'] ?? [];

render_header('Job Hub & Recruiter ATS', 'job_hub');
?>

<div class="space-y-6">

    <!-- Header / Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-briefcase text-brandIndigo"></i>
                Bounty Board & Recruiter ATS
            </h1>
            <p class="text-sm text-slate-400">
                Browse open tasks with locked escrow or manage applicant pipelines with 1-click payout release.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button 
                id="open-post-modal-btn"
                class="px-4 py-2.5 rounded-xl bg-brandMint hover:bg-emerald-400 text-slate-950 font-bold text-sm shadow-glow-mint transition flex items-center gap-2">
                <i class="fa-solid fa-vault"></i>
                <span>Post Bounty with Escrow</span>
            </button>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="glass-card p-4 rounded-2xl flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2 flex-1 min-w-[280px]">
            <div class="relative w-full">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input 
                    type="text" 
                    id="search-bounties" 
                    placeholder="Search by keywords, tags (e.g. PHP 8.2, Supabase, Tailwind)..." 
                    class="w-full bg-white/5 border border-white/10 rounded-xl pl-10 pr-4 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo focus:ring-1 focus:ring-brandIndigo transition">
            </div>
        </div>

        <div class="flex items-center gap-2">
            <select id="category-filter" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-brandIndigo">
                <option value="all">All Categories</option>
                <option value="Backend Architecture">Backend Architecture</option>
                <option value="Frontend UI/UX">Frontend UI/UX</option>
                <option value="Cybersecurity">Cybersecurity</option>
                <option value="DevOps">DevOps & Hostinger</option>
            </select>

            <select id="status-filter" class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-brandIndigo">
                <option value="all">All Statuses</option>
                <option value="open">Open (Escrow Locked)</option>
                <option value="in_review">In Review</option>
                <option value="awarded">Awarded</option>
            </select>
        </div>
    </div>

    <!-- Jobs Grid -->
    <div id="bounties-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($jobs as $job): ?>
            <?php 
                $isCreator = ($job['creator_id'] === $persona['id']);
                $escrowBadge = ($job['escrow_status'] === 'locked')
                    ? 'bg-brandMint/10 text-brandMint border-brandMint/20'
                    : 'bg-indigo-500/10 text-indigo-300 border-indigo-500/20';
            ?>
            <div class="glass-card rounded-2xl p-5 flex flex-col justify-between hover:border-brandIndigo/40 transition-all duration-200 group">
                <div>
                    <!-- Top row: Category & Bounty -->
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-mono font-medium px-2.5 py-0.5 rounded-lg bg-white/5 text-slate-300 border border-white/5">
                            <?= htmlspecialchars($job['category']) ?>
                        </span>
                        <div class="text-right">
                            <span class="text-lg font-black font-mono text-brandMint">$<?= number_format($job['bounty_amount'], 2) ?></span>
                        </div>
                    </div>

                    <!-- Title -->
                    <h3 class="text-base font-bold text-white mb-2 group-hover:text-brandIndigo transition">
                        <?= htmlspecialchars($job['title']) ?>
                    </h3>

                    <!-- Description snippet -->
                    <p class="text-xs text-slate-400 mb-4 line-clamp-3 leading-relaxed">
                        <?= htmlspecialchars($job['description']) ?>
                    </p>

                    <!-- Required Skills -->
                    <div class="flex flex-wrap gap-1.5 mb-4">
                        <?php foreach ($job['skills_required'] as $skill): ?>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-brandIndigo/10 text-brandIndigo border border-brandIndigo/20">
                                #<?= htmlspecialchars($skill) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Footer details -->
                <div class="pt-4 border-t border-white/5 space-y-3">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-user-tie text-[11px]"></i>
                            <span>@<?= htmlspecialchars($job['creator_handle'] ?? 'creator') ?></span>
                        </div>
                        <div class="flex items-center gap-1.5 text-slate-400 font-mono text-[11px]">
                            <i class="fa-solid fa-users text-[10px]"></i>
                            <span><?= $job['applicants_count'] ?? 10 ?> Candidates</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-mono px-2 py-1 rounded border uppercase font-bold <?= $escrowBadge ?> flex items-center gap-1">
                            <i class="fa-solid fa-shield-check text-[9px]"></i>
                            Escrow <?= htmlspecialchars($job['escrow_status']) ?>
                        </span>

                        <a 
                            href="<?= BASE_URL ?>/portal/candidate_review.php?job_id=<?= $job['id'] ?>" 
                            class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-brandIndigo text-white text-xs font-semibold transition flex items-center gap-1.5">
                            <span>Screen ATS</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<!-- Post Bounty with Escrow Modal -->
<div id="post-modal" class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="glass-card max-w-xl w-full rounded-2xl border border-white/10 p-6 relative shadow-2xl">
        <!-- Close Button -->
        <button id="close-post-modal-btn" class="absolute top-4 right-4 text-slate-400 hover:text-white p-2">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>

        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-10 h-10 rounded-xl bg-brandMint/10 text-brandMint flex items-center justify-center text-lg">
                <i class="fa-solid fa-vault"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-white">Post Bounty with Locked Escrow</h3>
                <p class="text-xs text-slate-400">Funds are locked upfront via PostgREST RPC. Zero counterparty risk.</p>
            </div>
        </div>

        <form id="post-bounty-form" class="space-y-4">
            <div>
                <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5">Bounty Title</label>
                <input 
                    type="text" 
                    name="title" 
                    required 
                    placeholder="e.g. Build Hostinger cURL Webhook Listener with HMAC-SHA256"
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5">Category</label>
                    <select name="category" class="w-full bg-[#121826] border border-white/10 rounded-xl px-3 py-2.5 text-sm text-slate-200 focus:outline-none focus:border-brandIndigo">
                        <option value="Backend Architecture">Backend Architecture</option>
                        <option value="Frontend UI/UX">Frontend UI/UX</option>
                        <option value="Cybersecurity">Cybersecurity</option>
                        <option value="DevOps & Hostinger">DevOps & Hostinger</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5">Bounty Amount ($ USD)</label>
                    <input 
                        type="number" 
                        id="bounty-amount-input"
                        name="bounty_amount" 
                        required 
                        min="50" 
                        step="10" 
                        value="1500" 
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brandMint">
                </div>
            </div>

            <div>
                <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5">Requirements & Proof Criteria</label>
                <textarea 
                    name="description" 
                    rows="3" 
                    required 
                    placeholder="Detail the scope of work, expected deliverables, and how proof of completion will be verified..." 
                    class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5">Skills (comma separated)</label>
                    <input 
                        type="text" 
                        name="skills" 
                        placeholder="PHP 8.2, Supabase, Tailwind" 
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-brandIndigo">
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5">Deadline</label>
                    <input 
                        type="date" 
                        name="deadline" 
                        value="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-brandIndigo">
                </div>
            </div>

            <!-- Escrow Lock Calculation Notice -->
            <div class="p-3 rounded-xl bg-brandMint/10 border border-brandMint/20 flex items-center justify-between text-xs">
                <div class="text-slate-300">
                    <span class="font-bold text-brandMint">Escrow Lock Amount:</span>
                    <span id="escrow-preview-text" class="font-mono font-bold text-white">$1,500.00</span>
                    <span class="text-slate-400 text-[10px] block">Deducted from active wallet upon creation</span>
                </div>
                <div class="text-right font-mono text-[11px] text-slate-400">
                    <div>Your Balance: <span class="text-emerald-400 font-bold">$<?= number_format($persona['wallet_balance'], 2) ?></span></div>
                </div>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3">
                <button type="button" id="cancel-modal-btn" class="px-4 py-2 rounded-xl text-xs font-medium text-slate-400 hover:text-white">Cancel</button>
                <button 
                    type="submit" 
                    id="submit-bounty-btn"
                    class="px-5 py-2.5 rounded-xl bg-brandMint hover:bg-emerald-400 text-slate-950 font-bold text-sm shadow-glow-mint transition flex items-center gap-2">
                    <i class="fa-solid fa-lock"></i>
                    <span>Lock Escrow & Publish</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Modal open/close
    const modal = document.getElementById('post-modal');
    document.getElementById('open-post-modal-btn').addEventListener('click', () => modal.classList.remove('hidden'));
    document.getElementById('close-post-modal-btn').addEventListener('click', () => modal.classList.add('hidden'));
    document.getElementById('cancel-modal-btn').addEventListener('click', () => modal.classList.add('hidden'));

    // Escrow amount live preview
    const amountInput = document.getElementById('bounty-amount-input');
    const previewText = document.getElementById('escrow-preview-text');
    amountInput.addEventListener('input', (e) => {
        const val = parseFloat(e.target.value) || 0;
        previewText.textContent = '$' + val.toLocaleString('en-US', { minimumFractionDigits: 2 });
    });

    // Form submission
    document.getElementById('post-bounty-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('submit-bounty-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Locking Escrow...`;

        const form = e.target;
        const payload = {
            title: form.title.value,
            category: form.category.value,
            bounty_amount: parseFloat(form.bounty_amount.value),
            description: form.description.value,
            skills: form.skills.value,
            deadline: form.deadline.value
        };

        try {
            await window.BountyApp.postBounty(payload);
            setTimeout(() => window.location.reload(), 800);
        } catch (err) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = `<i class="fa-solid fa-lock"></i> Lock Escrow & Publish`;
        }
    });
</script>

<?php
render_footer();
?>
