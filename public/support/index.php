<?php
// ==============================================================================
// SUPPORT TICKET SPLIT-PANE & STAFF DESK
// Path: public/support/index.php
// ==============================================================================

require_once __DIR__ . '/../config.php';

// Route Protection: Support, Founder & Admin only
$currentRole = $_SESSION['user_role'] ?? '';
if (!in_array($currentRole, ['support', 'founder', 'admin'], true)) {
    header('Location: ' . (empty(BASE_URL) ? '/support/login' : BASE_URL . '/support/login.php'));
    exit;
}

$persona = get_active_persona();
$tickets = &$_SESSION['bounty_mock_db']['tickets'];

$activeTicketId = $_GET['ticket_id'] ?? ($tickets[0]['id'] ?? '');

// Handle staff reply or resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'reply';
    $targetId = $_POST['ticket_id'] ?? $activeTicketId;
    $replyText = trim($_POST['reply_text'] ?? '');

    foreach ($tickets as &$t) {
        if ($t['id'] === $targetId) {
            if ($action === 'reply' && !empty($replyText)) {
                $t['messages'][] = [
                    'sender' => $persona['display_name'],
                    'is_staff' => true,
                    'text' => $replyText,
                    'time' => 'Just now'
                ];
                $t['status'] = 'in_progress';
            } elseif ($action === 'resolve') {
                $t['status'] = 'resolved';
            } elseif ($action === 'refund') {
                $t['status'] = 'resolved';
                $t['messages'][] = [
                    'sender' => 'System / Staff Desk',
                    'is_staff' => true,
                    'text' => 'Staff action: Full escrow amount refunded back to employer wallet.',
                    'time' => 'Just now'
                ];
            }
            break;
        }
    }
    header('Location: ' . BASE_URL . '/support/index.php?ticket_id=' . urlencode($targetId));
    exit;
}

// Find current active ticket
$activeTicket = null;
foreach ($tickets as $t) {
    if ($t['id'] === $activeTicketId) {
        $activeTicket = $t;
        break;
    }
}
if (!$activeTicket && !empty($tickets)) {
    $activeTicket = $tickets[0];
}

render_header('Support Ticket Split-Pane & Staff Desk', 'support');
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono uppercase text-cyan-400 font-bold">Staff Mediation Desk</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-headset text-cyan-400"></i>
                Support Ticket Split-Pane & Staff Desk
            </h1>
            <p class="text-sm text-slate-400">
                Arbitrate escrow disputes, investigate payment queries, and provide rapid staff resolution.
            </p>
        </div>

        <div class="flex items-center gap-2 text-xs font-mono">
            <span class="px-3 py-1.5 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-300">
                <i class="fa-solid fa-clock-rotate-left mr-1"></i> Avg Response: 4.2 mins
            </span>
        </div>
    </div>

    <!-- Split-Pane Container -->
    <div class="glass-card rounded-2xl overflow-hidden border border-white/10 grid grid-cols-1 lg:grid-cols-12 min-h-[620px]">
        
        <!-- Left Pane: Ticket Queue (5 cols) -->
        <div class="lg:col-span-5 border-r border-white/5 flex flex-col bg-black/10">
            <!-- Search & Filter -->
            <div class="p-4 border-b border-white/5 space-y-3">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input 
                        type="text" 
                        placeholder="Search tickets by user, ID..." 
                        class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400">
                </div>
            </div>

            <!-- Queue List -->
            <div class="flex-1 overflow-y-auto divide-y divide-white/5">
                <?php foreach ($tickets as $ticket): ?>
                    <?php 
                        $isSelected = ($ticket['id'] === $activeTicket['id']);
                        $isHigh = ($ticket['priority'] === 'high');
                    ?>
                    <a 
                        href="?ticket_id=<?= urlencode($ticket['id']) ?>" 
                        class="block p-4 hover:bg-white/5 transition <?= $isSelected ? 'bg-white/10 border-l-4 border-l-cyan-400' : '' ?>">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-mono font-bold text-white"><?= htmlspecialchars($ticket['id']) ?></span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded uppercase font-bold <?= $ticket['status'] === 'open' ? 'bg-amber-500/20 text-amber-300' : ($ticket['status'] === 'resolved' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-cyan-500/20 text-cyan-300') ?>">
                                <?= htmlspecialchars($ticket['status']) ?>
                            </span>
                        </div>

                        <h4 class="text-sm font-semibold text-white line-clamp-1 mb-1"><?= htmlspecialchars($ticket['subject']) ?></h4>

                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span class="flex items-center gap-1 font-mono">
                                <i class="fa-solid fa-user text-[10px]"></i> <?= htmlspecialchars($ticket['user_name']) ?>
                            </span>
                            <span class="text-[10px] font-mono text-slate-500"><?= htmlspecialchars($ticket['created_at']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right Pane: Active Ticket Conversation (7 cols) -->
        <div class="lg:col-span-7 flex flex-col justify-between">
            <!-- Ticket Header Info -->
            <div class="p-5 border-b border-white/5 bg-white/5 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-mono text-cyan-400 font-bold"><?= htmlspecialchars($activeTicket['id']) ?></span>
                        <span class="text-slate-500">•</span>
                        <span class="text-xs font-mono uppercase text-slate-300"><?= htmlspecialchars($activeTicket['category']) ?></span>
                    </div>
                    <h2 class="text-base font-bold text-white"><?= htmlspecialchars($activeTicket['subject']) ?></h2>
                    <div class="text-xs text-slate-400 mt-1">
                        Requester: <strong class="text-slate-200"><?= htmlspecialchars($activeTicket['user_name']) ?></strong> (@<?= htmlspecialchars($activeTicket['user_handle']) ?>)
                    </div>
                </div>

                <!-- Fast Actions -->
                <div class="flex items-center gap-2">
                    <form method="POST">
                        <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">
                        <input type="hidden" name="action" value="resolve">
                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/30 text-xs font-bold transition">
                            <i class="fa-solid fa-check mr-1"></i> Resolve
                        </button>
                    </form>

                    <form method="POST">
                        <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">
                        <input type="hidden" name="action" value="refund">
                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 text-xs font-bold transition">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Refund Escrow
                        </button>
                    </form>
                </div>
            </div>

            <!-- Messages Log -->
            <div class="flex-1 p-5 overflow-y-auto space-y-4">
                <?php foreach ($activeTicket['messages'] as $m): ?>
                    <div class="flex flex-col <?= $m['is_staff'] ? 'items-end' : 'items-start' ?>">
                        <div class="flex items-center gap-2 mb-1 text-xs">
                            <span class="font-bold <?= $m['is_staff'] ? 'text-cyan-400' : 'text-slate-300' ?>">
                                <?= htmlspecialchars($m['sender']) ?> <?= $m['is_staff'] ? '(Staff)' : '' ?>
                            </span>
                            <span class="font-mono text-[10px] text-slate-500"><?= htmlspecialchars($m['time']) ?></span>
                        </div>
                        <div class="max-w-md p-3.5 rounded-2xl text-sm leading-relaxed <?= $m['is_staff'] ? 'bg-cyan-950/40 border border-cyan-500/30 text-cyan-100' : 'bg-white/5 border border-white/10 text-slate-200' ?>">
                            <?= nl2br(htmlspecialchars($m['text'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Staff Reply Form -->
            <div class="p-4 border-t border-white/5 bg-black/20">
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="ticket_id" value="<?= $activeTicket['id'] ?>">
                    <input type="hidden" name="action" value="reply">
                    <textarea 
                        name="reply_text" 
                        rows="2" 
                        required 
                        placeholder="Type official staff mediation reply or arbitration decision..." 
                        class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-cyan-400"></textarea>
                    
                    <div class="flex items-center justify-between">
                        <div class="text-[11px] text-slate-400">
                            Responding as: <strong class="text-cyan-400"><?= htmlspecialchars($persona['display_name']) ?></strong> (<?= htmlspecialchars($persona['role_label']) ?>)
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs transition flex items-center gap-1.5">
                            <i class="fa-solid fa-paper-plane text-xs"></i>
                            <span>Send Reply</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

</div>

<?php
render_footer();
?>
