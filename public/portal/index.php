<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — Live Community Lounge & Real-Time Event Feed
// Path: public/portal/index.php
// ==============================================================================

require_once __DIR__ . '/../config.php';

$ctx     = get_active_user_context();
$baseUrl = BASE_URL;
$jobs    = $_SESSION['bounty_mock_db']['jobs'] ?? [];

// Fetch chat & broadcast messages (Supabase or session mock fallback)
$chatMessages = [];
if (is_supabase_configured()) {
    $res = supabase_request('rest/v1/chat_messages?select=*,profiles:sender_id(display_name,handle,avatar_url,role)&order=created_at.desc&limit=50', 'GET', null, true);
    if (!empty($res['data']) && is_array($res['data'])) {
        $chatMessages = $res['data'];
    }
}
if (empty($chatMessages)) {
    $chatMessages = array_reverse($_SESSION['bounty_mock_db']['chat'] ?? []);
}

render_header('Live Community Lounge & Event Feed', 'portal');
?>

<!-- Outer Dark Canvas Container -->
<div class="portal-outer-wrapper w-full min-h-screen bg-[#0B0F17] text-slate-100">

<!-- Platform Stats Banner -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md p-4 rounded-2xl flex items-center gap-3 border border-white/10">
        <div class="w-10 h-10 rounded-xl bg-brandIndigo/10 border border-brandIndigo/20 flex items-center justify-center text-brandIndigo text-lg">
            <i class="fa-solid fa-vault"></i>
        </div>
        <div>
            <div class="text-[11px] uppercase tracking-wider font-mono text-slate-400">Total Escrow Vault</div>
            <div class="text-xl font-black text-white font-mono">৳74,270.00</div>
        </div>
    </div>

    <div class="glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md p-4 rounded-2xl flex items-center gap-3 border border-white/10">
        <div class="w-10 h-10 rounded-xl bg-brandMint/10 border border-brandMint/20 flex items-center justify-center text-brandMint text-lg">
            <i class="fa-solid fa-briefcase"></i>
        </div>
        <div>
            <div class="text-[11px] uppercase tracking-wider font-mono text-slate-400">Active Bounties</div>
            <div class="text-xl font-black text-white font-mono"><?= count($jobs) ?> Open</div>
        </div>
    </div>

    <div class="glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md p-4 rounded-2xl flex items-center gap-3 border border-white/10">
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-lg">
            <i class="fa-solid fa-users"></i>
        </div>
        <div>
            <div class="text-[11px] uppercase tracking-wider font-mono text-slate-400">Hunters In Lounge</div>
            <div class="text-xl font-black text-white font-mono">1,428</div>
        </div>
    </div>

    <div class="glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md p-4 rounded-2xl flex items-center gap-3 border border-white/10">
        <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-lg">
            <i class="fa-solid fa-server"></i>
        </div>
        <div>
            <div class="text-[11px] uppercase tracking-wider font-mono text-slate-400">Hostinger Engine</div>
            <div class="text-xl font-black text-emerald-400 font-mono flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                PHP 8.2+
            </div>
        </div>
    </div>
</div>

<!-- Two-Column Responsive Desktop Layout (Collapses to Single Column on Mobile) -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

    <!-- ── Center / Main Column: Status Update & Feed Stream (8 Cols) ────────── -->
    <div class="lg:col-span-8 space-y-6">

        <!-- Status Update Input Box & Modal Trigger -->
        <div class="glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md p-4 sm:p-5 rounded-2xl border border-white/10 shadow-xl">
            <div class="flex items-center gap-3 mb-3">
                <img src="<?= htmlspecialchars($ctx['avatar_url'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150') ?>" 
                     alt="<?= htmlspecialchars($ctx['display_name'] ?? 'User') ?>" 
                     class="w-10 h-10 rounded-xl object-cover border border-white/10">
                <div>
                    <div class="text-sm font-bold text-white flex items-center gap-1.5">
                        <?= htmlspecialchars($ctx['display_name'] ?? 'Anonymous') ?>
                        <span class="text-[10px] font-mono text-slate-400">@<?= htmlspecialchars($ctx['handle'] ?? 'user') ?></span>
                    </div>
                    <div class="text-xs text-slate-400 flex items-center gap-1.5 font-mono">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Active in Community Lounge
                    </div>
                </div>
            </div>

            <!-- Status Form -->
            <form id="lounge-status-form" class="space-y-3">
                <textarea 
                    id="lounge-status-input" 
                    rows="2" 
                    placeholder="Share an update, announce bounty milestones, or drop a note in the lounge..." 
                    class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo focus:ring-1 focus:ring-brandIndigo transition resize-none"></textarea>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <!-- [Create Paid Job / Bounty] Modal Trigger Button -->
                    <button 
                        id="btn-open-create-bounty-modal" 
                        type="button" 
                        class="btn-trigger-bounty-modal px-3.5 py-2 rounded-xl bg-gradient-to-r from-emerald-500/15 via-indigo-500/15 to-emerald-500/15 hover:from-emerald-500/25 hover:via-indigo-500/25 hover:to-emerald-500/25 text-emerald-300 hover:text-white border border-emerald-500/30 text-xs font-bold transition flex items-center gap-2 shadow-glow-mint cursor-pointer group">
                        <svg class="w-4 h-4 text-emerald-400 group-hover:scale-110 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="16"></line>
                            <line x1="8" y1="12" x2="16" y2="12"></line>
                        </svg>
                        <span>Create Paid Job / Bounty</span>
                    </button>

                    <!-- Send Update Button -->
                    <button 
                        type="submit" 
                        class="btn-stitch-primary px-4 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 cursor-pointer">
                        <span>Post Update</span>
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </form>
        </div>

        <!-- Feed Section Header -->
        <div class="flex items-center justify-between px-1">
            <div class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-400"></span>
                </span>
                <h2 class="text-sm font-bold text-white uppercase tracking-wider font-mono">Live Event Feed & Lounge</h2>
            </div>
            <span class="text-xs text-slate-400 font-mono">Supabase Realtime • WebSocket Active</span>
        </div>

        <!-- Dynamic Feed Stream Box (Receives Realtime Items & Initial Messages) -->
        <div id="feed-stream" class="space-y-4">
            <?php foreach ($chatMessages as $msg): ?>
                <?php
                $msgType = $msg['message_type'] ?? 'chat';
                $meta    = $msg['meta'] ?? $msg['meta_json'] ?? [];
                if (is_string($meta)) {
                    $meta = json_decode($meta, true) ?? [];
                }
                $isJobBroadcast = ($msgType === 'job_broadcast' || $msgType === 'job_alert' || !empty($meta['bounty']) || !empty($msg['bounty_amount']));
                $senderName     = $msg['sender_name'] ?? ($msg['profiles']['display_name'] ?? 'Community Member');
                $senderHandle   = $msg['sender_handle'] ?? ($msg['profiles']['handle'] ?? 'member');
                $senderRole     = $msg['sender_role'] ?? ($msg['profiles']['role'] ?? 'hunter');
                $senderAvatar   = $msg['sender_avatar'] ?? ($msg['profiles']['avatar_url'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150');
                $timeDisplay    = $msg['created_at'] ? date('H:i', strtotime($msg['created_at'])) : 'Recent';
                $msgId          = $msg['id'] ?? uniqid('msg-');
                ?>

                <?php if ($isJobBroadcast): ?>
                    <!-- Rich Job Alert Card -->
                    <?php
                    $title       = $meta['title'] ?? $msg['title'] ?? $msg['message'] ?? 'New Bounty';
                    $bountyVal   = (float)($meta['bounty'] ?? $meta['bounty_amount'] ?? $msg['bounty_amount'] ?? 2500.00);
                    $openings    = (int)($meta['openings'] ?? $msg['openings'] ?? 1);
                    $jobId       = $meta['job_id'] ?? $msg['job_id'] ?? $msg['id'] ?? '';
                    $category    = $meta['category'] ?? $msg['category'] ?? 'Backend Architecture';
                    ?>
                    <div id="feed-item-<?= htmlspecialchars($msgId) ?>" 
                         data-message-id="<?= htmlspecialchars($msgId) ?>" 
                         class="job-alert-card glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md rounded-2xl p-5 border border-white/10 border-l-4 border-l-[#10B981] relative overflow-hidden transition-all duration-300 hover:border-l-[#34D399]">
                        <div class="absolute -right-12 -bottom-12 w-44 h-44 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>

                        <!-- Top Header Row -->
                        <div class="flex flex-wrap items-center justify-between gap-2.5 mb-3 relative z-10">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-mono font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 uppercase flex items-center gap-1.5 shadow-sm">
                                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                    <span>🚀 Escrowed Bounty</span>
                                </span>
                                <span class="px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 text-xs font-mono text-slate-300 flex items-center gap-1.5" title="Available Vacancies">
                                    <svg class="w-3.5 h-3.5 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    <span><?= $openings ?> <?= $openings === 1 ? 'Opening' : 'Openings' ?></span>
                                </span>
                                <span class="text-[11px] font-mono text-slate-400 px-2 py-0.5 rounded bg-white/5">
                                    <?= htmlspecialchars($category) ?>
                                </span>
                            </div>

                            <!-- Coin Reward Pill -->
                            <div class="coin-pill">
                                <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="8" cy="8" r="6"/>
                                    <path d="M18.09 10.37A6 6 0 1 1 10.34 18"/>
                                    <path d="m7 6 2 2-2 2"/>
                                    <path d="m17 16 2 2-2 2"/>
                                </svg>
                                <span class="font-mono font-bold"><?= format_bdt($bountyVal) ?></span>
                                <span class="text-[10px] uppercase font-mono px-1 rounded bg-emerald-500/20 text-emerald-300">BDT</span>
                            </div>
                        </div>

                        <!-- Bounty Title -->
                        <h3 class="text-base sm:text-lg font-bold text-white mb-2 leading-snug tracking-tight">
                            <?= htmlspecialchars($title) ?>
                        </h3>

                        <!-- Excerpt / Description -->
                        <p class="text-xs sm:text-sm text-slate-300 mb-4 line-clamp-2 leading-relaxed">
                            <?= htmlspecialchars($msg['message'] ?? 'Task bounty with funds guaranteed in smart escrow. Verified candidates receive instant payout release upon milestone completion.') ?>
                        </p>

                        <!-- Action Bar: Poster Identity & Apply Button -->
                        <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-white/5 relative z-10">
                            <div class="flex items-center gap-2">
                                <img src="<?= htmlspecialchars($senderAvatar) ?>" alt="<?= htmlspecialchars($senderHandle) ?>" class="w-6 h-6 rounded-md object-cover border border-white/10">
                                <span class="text-xs text-slate-400">by <strong class="text-white">@<?= htmlspecialchars($senderHandle) ?></strong></span>
                                <span class="text-[10px] font-mono text-slate-500">• <?= htmlspecialchars($timeDisplay) ?></span>
                            </div>

                            <div class="flex items-center gap-2">
                                <a href="<?= $baseUrl ?>/portal/job_hub.php?job_id=<?= urlencode($jobId) ?>" class="px-3.5 py-1.5 rounded-xl bg-brandIndigo hover:bg-indigo-500 text-white font-bold text-xs shadow-glow-indigo transition flex items-center gap-1.5">
                                    <span>View Details & Apply</span>
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>

                <?php elseif ($msgType === 'system_alert'): ?>
                    <!-- System Escrow Notification Card -->
                    <div id="feed-item-<?= htmlspecialchars($msgId) ?>" 
                         data-message-id="<?= htmlspecialchars($msgId) ?>"
                         class="p-3.5 rounded-xl bg-indigo-500/10 border border-indigo-500/25 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-500/20 text-indigo-300 flex items-center justify-center text-sm flex-shrink-0">
                            <i class="fa-solid fa-sparkles"></i>
                        </div>
                        <div class="text-xs text-indigo-200 font-medium flex-1 leading-relaxed">
                            <?= htmlspecialchars($msg['message']) ?>
                        </div>
                        <span class="font-mono text-[10px] text-slate-400 flex-shrink-0"><?= htmlspecialchars($timeDisplay) ?></span>
                    </div>

                <?php else: ?>
                    <!-- Standard Chat Message -->
                    <?php
                    $roleBadgeClass = match($senderRole) {
                        'admin'     => 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
                        'recruiter' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                        'hunter'    => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                        'mod'       => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                        'support'   => 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30',
                        default     => 'bg-white/10 text-slate-300 border-white/10',
                    };
                    ?>
                    <div id="feed-item-<?= htmlspecialchars($msgId) ?>" 
                         data-message-id="<?= htmlspecialchars($msgId) ?>"
                         class="chat-message-item glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md rounded-2xl p-4 border border-white/10 transition-all duration-200 hover:border-white/20">
                        <div class="flex items-start gap-3">
                            <img src="<?= htmlspecialchars($senderAvatar) ?>" alt="<?= htmlspecialchars($senderName) ?>" class="w-9 h-9 rounded-xl object-cover border border-white/10 mt-0.5">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="text-xs font-bold text-white"><?= htmlspecialchars($senderName) ?></span>
                                    <span class="text-[10px] font-mono text-slate-400">@<?= htmlspecialchars($senderHandle) ?></span>
                                    <span class="text-[9px] font-mono uppercase px-1.5 py-0.2 rounded border <?= $roleBadgeClass ?>">
                                        <?= htmlspecialchars($senderRole) ?>
                                    </span>
                                    <span class="text-[10px] font-mono text-slate-500 ml-auto"><?= htmlspecialchars($timeDisplay) ?></span>
                                </div>
                                <div class="text-sm text-slate-200 leading-relaxed break-words">
                                    <?= htmlspecialchars($msg['message'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- ── Right Column: Active Persona, Top Bounties & Infiltration (4 Cols) ── -->
    <div class="lg:col-span-4 space-y-6">

        <!-- Active Persona & Escrow Ready Card -->
        <div class="glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md rounded-2xl p-5 border border-white/10 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-mono uppercase text-slate-400">Your Identity</span>
                <span class="text-[10px] uppercase font-mono px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 font-bold">
                    <?= htmlspecialchars($ctx['role'] ?? 'user') ?>
                </span>
            </div>

            <div class="flex items-center gap-3.5 mb-4">
                <img src="<?= htmlspecialchars($ctx['avatar_url'] ?? '') ?>" alt="Avatar" class="w-12 h-12 rounded-xl object-cover border border-white/10">
                <div>
                    <div class="text-base font-bold text-white"><?= htmlspecialchars($ctx['display_name'] ?? 'User') ?></div>
                    <div class="text-xs font-mono text-slate-400">@<?= htmlspecialchars($ctx['handle'] ?? 'handle') ?></div>
                    <div class="text-xs text-emerald-400 font-mono mt-0.5 font-bold flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        <?= format_bdt($ctx['wallet_balance'] ?? 0) ?> Escrow Ready
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-white/5">
                <button 
                    type="button" 
                    class="btn-trigger-bounty-modal w-full text-center px-3 py-2.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-300 border border-emerald-500/30 text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shadow-glow-mint">
                    <i class="fa-solid fa-plus-circle"></i>
                    <span>Post Bounty with Escrow</span>
                </button>
            </div>
        </div>

        <!-- Open Bounties Quick Widget -->
        <div class="glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md rounded-2xl p-5 border border-white/10 shadow-lg">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-fire text-amber-400"></i> Open Bounties
                </h3>
                <a href="<?= $baseUrl ?>/portal/job_hub.php" class="text-xs text-brandIndigo hover:underline font-mono">View All &rarr;</a>
            </div>

            <div class="space-y-3">
                <?php foreach (array_slice($jobs, 0, 3) as $jb): ?>
                    <div class="p-3 rounded-xl bg-white/5 border border-white/5 hover:border-brandIndigo/30 transition">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-white/5 text-slate-300"><?= htmlspecialchars($jb['category']) ?></span>
                            <span class="text-xs font-black text-emerald-400 font-mono"><?= format_bdt($jb['bounty_amount']) ?></span>
                        </div>
                        <h4 class="text-xs font-bold text-white line-clamp-1 mb-1.5"><?= htmlspecialchars($jb['title']) ?></h4>
                        <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-white/5">
                            <span><?= $jb['applicants_count'] ?? 12 ?> applicants</span>
                            <a href="<?= $baseUrl ?>/portal/job_hub.php?job_id=<?= urlencode($jb['id']) ?>" class="text-brandIndigo hover:underline font-semibold">Apply &rarr;</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Infiltration Roster & Switcher -->
        <div class="glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md rounded-2xl p-5 border border-white/10 shadow-lg">
            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <i class="fa-solid fa-user-shield text-slate-400"></i> Infiltration Roster
            </h3>
            <div class="space-y-2">
                <?php foreach (get_predefined_personas() as $rKey => $p): ?>
                    <div class="flex items-center justify-between p-2 rounded-xl bg-white/5 border border-white/5 hover:border-white/10 transition">
                        <div class="flex items-center gap-2.5">
                            <img src="<?= htmlspecialchars($p['avatar_url']) ?>" alt="Avatar" class="w-7 h-7 rounded-lg object-cover">
                            <div>
                                <div class="text-xs font-semibold text-white"><?= htmlspecialchars($p['display_name']) ?></div>
                                <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($p['role_label']) ?></div>
                            </div>
                        </div>
                        <button 
                            data-sneak="<?= $rKey ?>"
                            class="quick-sneak-btn text-[10px] font-mono px-2.5 py-1 rounded-lg bg-indigo-500/20 text-indigo-300 hover:bg-brandIndigo hover:text-white transition cursor-pointer">
                            Switch
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<!-- ── [Create Paid Job / Bounty] Modal Window ────────────────────────────── -->
<div id="create-bounty-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/75 backdrop-blur-md transition-all">
    <div class="glass-panel bg-[rgba(18,24,38,0.9)] backdrop-blur-xl rounded-3xl p-6 sm:p-7 border border-white/15 max-w-lg w-full shadow-2xl relative">
        <!-- Close Button -->
        <button type="button" class="btn-close-bounty-modal absolute top-5 right-5 w-8 h-8 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white flex items-center justify-center text-sm transition cursor-pointer">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <!-- Modal Header -->
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-brandIndigo to-brandMint flex items-center justify-center text-slate-950 font-black shadow-glow-mint">
                <i class="fa-solid fa-vault"></i>
            </div>
            <div>
                <h3 class="text-lg font-bold text-white tracking-tight">Create Paid Job / Bounty</h3>
                <p class="text-xs text-slate-400 font-mono">Funds are atomically locked into escrow upon creation</p>
            </div>
        </div>

        <!-- Bounty Form -->
        <form id="modal-create-bounty-form" class="space-y-4">
            <div>
                <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5 font-semibold">Bounty Title *</label>
                <input 
                    type="text" 
                    name="title" 
                    required 
                    placeholder="e.g. Build Hostinger PHP 8.2 Supabase PostgREST Connector" 
                    class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo focus:ring-1 focus:ring-brandIndigo transition">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5 font-semibold">Category</label>
                    <select 
                        name="category" 
                        class="w-full bg-[#121826] border border-white/10 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-brandIndigo">
                        <option value="Backend Architecture">Backend Architecture</option>
                        <option value="Frontend UI/UX">Frontend UI/UX</option>
                        <option value="Fullstack Development">Fullstack Development</option>
                        <option value="Cybersecurity">Cybersecurity</option>
                        <option value="DevOps & Hostinger">DevOps & Hostinger</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5 font-semibold">Bounty Reward (৳ BDT) *</label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-emerald-400 font-bold">৳</span>
                        <input 
                            type="number" 
                            name="bounty_amount" 
                            min="50" 
                            step="10" 
                            value="1500" 
                            required 
                            class="w-full bg-white/5 border border-white/10 rounded-xl pl-8 pr-3 py-2.5 text-sm text-white font-mono font-bold focus:outline-none focus:border-brandMint focus:ring-1 focus:ring-brandMint transition">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5 font-semibold">Openings / Vacancies</label>
                    <input 
                        type="number" 
                        name="openings" 
                        min="1" 
                        max="10" 
                        value="1" 
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white font-mono focus:outline-none focus:border-brandIndigo transition">
                </div>

                <div>
                    <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5 font-semibold">Skills (Comma-Separated)</label>
                    <input 
                        type="text" 
                        name="skills" 
                        placeholder="PHP 8.2, Supabase, Tailwind" 
                        class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5 font-semibold">Task Requirements & Deliverables</label>
                <textarea 
                    name="description" 
                    rows="3" 
                    placeholder="Specify project scope, repository links, acceptance criteria, and expected milestones..." 
                    class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo transition resize-none"></textarea>
            </div>

            <!-- Escrow Notice -->
            <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-300 flex items-start gap-2.5">
                <i class="fa-solid fa-shield-halved text-sm mt-0.5 text-emerald-400"></i>
                <div class="leading-relaxed">
                    <strong>Atomic Escrow Custody:</strong> Reward is locked directly from your account balance. Candidates are paid upon approved milestone delivery.
                </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <button 
                    type="button" 
                    class="btn-close-bounty-modal px-4 py-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 text-xs font-semibold transition cursor-pointer">
                    Cancel
                </button>
                <button 
                    type="submit" 
                    class="btn-stitch-mint px-5 py-2.5 rounded-xl text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-glow-mint">
                    <span>Lock Escrow & Broadcast Bounty</span>
                    <i class="fa-solid fa-bolt text-xs ml-1"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Quick Sneak Switch Handler
    document.querySelectorAll('.quick-sneak-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const role = btn.getAttribute('data-sneak');
            btn.disabled = true;
            btn.innerHTML = '⏳';
            try {
                const res = await fetch('<?= $baseUrl ?>/api/admin_sneak.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': window.BOUNTY_CONFIG.csrfToken
                    },
                    body: JSON.stringify({ 
                        action: 'switch_mock_role',
                        role: role,
                        _csrf: window.BOUNTY_CONFIG.csrfToken
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to switch persona.');
                    window.location.reload();
                }
            } catch (err) {
                console.error(err);
                window.location.reload();
            }
        });
    });
</script>
</div> <!-- End portal-outer-wrapper -->

<?php
render_footer();
?>
