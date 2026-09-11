<?php
// ==============================================================================
// FOUNDER TELEMETRY & DIRECTORY INFILTRATION
// Path: public/admin/index.php
// ==============================================================================

require_once __DIR__ . '/../config.php';

$persona = get_active_persona();
$personas = get_predefined_personas();
$jobs = $_SESSION['bounty_mock_db']['jobs'] ?? [];

render_header('Founder Telemetry & Directory Infiltration', 'admin');
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono uppercase text-indigo-400 font-bold">Executive Suite</span>
                <span class="text-slate-600">•</span>
                <span class="text-xs font-mono text-emerald-400">Hostinger Live Metrics</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-chart-line text-brandIndigo"></i>
                Founder Telemetry & Directory Infiltration
            </h1>
            <p class="text-sm text-slate-400">
                Monitor platform liquidity, escrow custody balances, and infiltrate active personas with 1-click.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>/admin/branding.php" class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white text-xs font-semibold transition flex items-center gap-1.5 border border-white/10">
                <i class="fa-solid fa-sliders"></i>
                <span>Platform Console</span>
            </a>
        </div>
    </div>

    <!-- Core Telemetry Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="glass-card p-5 rounded-2xl">
            <div class="flex items-center justify-between text-slate-400 text-xs mb-2">
                <span class="uppercase font-mono">Escrow In Custody</span>
                <i class="fa-solid fa-vault text-brandMint text-base"></i>
            </div>
            <div class="text-2xl font-black text-white font-mono">৳74,270.00</div>
            <div class="text-xs text-brandMint mt-1 flex items-center gap-1 font-mono">
                <i class="fa-solid fa-arrow-trend-up text-[10px]"></i> +18.4% this week
            </div>
        </div>

        <div class="glass-card p-5 rounded-2xl">
            <div class="flex items-center justify-between text-slate-400 text-xs mb-2">
                <span class="uppercase font-mono">Platform Revenue (5%)</span>
                <i class="fa-solid fa-coins text-amber-400 text-base"></i>
            </div>
            <div class="text-2xl font-black text-amber-400 font-mono">৳3,713.50</div>
            <div class="text-xs text-slate-400 mt-1 font-mono">
                From completed payouts
            </div>
        </div>

        <div class="glass-card p-5 rounded-2xl">
            <div class="flex items-center justify-between text-slate-400 text-xs mb-2">
                <span class="uppercase font-mono">Hostinger Latency</span>
                <i class="fa-solid fa-gauge-high text-cyan-400 text-base"></i>
            </div>
            <div class="text-2xl font-black text-cyan-400 font-mono">34ms</div>
            <div class="text-xs text-slate-400 mt-1 font-mono">
                PHP 8.2 Native cURL RPC
            </div>
        </div>

        <div class="glass-card p-5 rounded-2xl">
            <div class="flex items-center justify-between text-slate-400 text-xs mb-2">
                <span class="uppercase font-mono">Security Threat Rate</span>
                <i class="fa-solid fa-shield-halved text-brandIndigo text-base"></i>
            </div>
            <div class="text-2xl font-black text-emerald-400 font-mono">0.00%</div>
            <div class="text-xs text-slate-400 mt-1 font-mono">
                Zero escrow breach incidents
            </div>
        </div>
    </div>

    <!-- Category Liquidity Breakdown -->
    <div class="glass-card p-6 rounded-2xl">
        <h3 class="text-base font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-pie-chart text-brandIndigo"></i>
            Escrow Liquidity Allocation by Category
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 rounded-xl bg-white/5 border border-white/5">
                <div class="text-xs text-slate-400 font-mono">Backend & PostgREST</div>
                <div class="text-lg font-bold text-white font-mono mt-1">৳38,500.00</div>
                <div class="w-full bg-slate-800 rounded-full h-1.5 mt-2 overflow-hidden">
                    <div class="bg-brandIndigo h-1.5 rounded-full" style="width: 52%"></div>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-white/5 border border-white/5">
                <div class="text-xs text-slate-400 font-mono">Frontend & Tailwind</div>
                <div class="text-lg font-bold text-white font-mono mt-1">৳19,200.00</div>
                <div class="w-full bg-slate-800 rounded-full h-1.5 mt-2 overflow-hidden">
                    <div class="bg-brandMint h-1.5 rounded-full" style="width: 26%"></div>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-white/5 border border-white/5">
                <div class="text-xs text-slate-400 font-mono">Threat Patrol & Security</div>
                <div class="text-lg font-bold text-white font-mono mt-1">৳11,400.00</div>
                <div class="w-full bg-slate-800 rounded-full h-1.5 mt-2 overflow-hidden">
                    <div class="bg-rose-500 h-1.5 rounded-full" style="width: 15%"></div>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-white/5 border border-white/5">
                <div class="text-xs text-slate-400 font-mono">DevOps & Hostinger</div>
                <div class="text-lg font-bold text-white font-mono mt-1">৳5,170.00</div>
                <div class="w-full bg-slate-800 rounded-full h-1.5 mt-2 overflow-hidden">
                    <div class="bg-amber-500 h-1.5 rounded-full" style="width: 7%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Directory Infiltration Console -->
    <div class="glass-card rounded-2xl overflow-hidden">
        <div class="p-5 border-b border-white/5 flex items-center justify-between">
            <div>
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-secret text-brandIndigo"></i>
                    Directory Infiltration Console
                </h3>
                <p class="text-xs text-slate-400">Instantly impersonate any registered persona to test flows from their perspective.</p>
            </div>

            <span class="text-xs font-mono px-2.5 py-1 rounded-lg bg-white/5 text-slate-400 border border-white/10">
                5 Built-in Personas
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-300">
                <thead class="bg-white/5 text-[11px] uppercase font-mono text-slate-400 border-b border-white/5">
                    <tr>
                        <th class="px-5 py-3.5">Persona & Identity</th>
                        <th class="px-5 py-3.5">Role</th>
                        <th class="px-5 py-3.5">Wallet Balance</th>
                        <th class="px-5 py-3.5">Reputation</th>
                        <th class="px-5 py-3.5">Primary Focus</th>
                        <th class="px-5 py-3.5 text-right">Infiltration Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 font-sans">
                    <?php foreach ($personas as $rKey => $p): ?>
                        <?php 
                            $isCurrent = ($persona['role'] === $rKey);
                            $roleColors = [
                                'admin' => 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
                                'recruiter' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                'hunter' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                'mod' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                'support' => 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30'
                            ];
                            $badge = $roleColors[$rKey] ?? 'bg-slate-700 text-slate-300';
                        ?>
                        <tr class="hover:bg-white/5 transition <?= $isCurrent ? 'bg-brandIndigo/5' : '' ?>">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <img src="<?= htmlspecialchars($p['avatar_url']) ?>" alt="Avatar" class="w-9 h-9 rounded-xl object-cover border border-white/10">
                                    <div>
                                        <div class="font-bold text-white flex items-center gap-2">
                                            <?= htmlspecialchars($p['display_name']) ?>
                                            <?php if ($isCurrent): ?>
                                                <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-brandMint/20 text-brandMint font-bold">CURRENT</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-xs font-mono text-slate-400">@<?= htmlspecialchars($p['handle']) ?></div>
                                    </div>
                                </div>
                            </td>

                            <td class="px-5 py-4">
                                <span class="text-xs font-mono uppercase px-2.5 py-1 rounded-lg border font-bold <?= $badge ?>">
                                    <?= htmlspecialchars($p['role_label']) ?>
                                </span>
                            </td>

                            <td class="px-5 py-4 font-mono font-bold text-brandMint">
                                <?= format_bdt($p['wallet_balance']) ?>
                            </td>

                            <td class="px-5 py-4 font-mono text-xs">
                                <span class="text-amber-400">★ <?= $p['rating'] ?></span>
                                <span class="text-slate-500 ml-1">(<?= $p['reputation_score'] ?> pts)</span>
                            </td>

                            <td class="px-5 py-4 text-xs text-slate-400 max-w-xs truncate">
                                <?= implode(', ', array_slice($p['skills'], 0, 3)) ?>
                            </td>

                            <td class="px-5 py-4 text-right">
                                <?php if ($isCurrent): ?>
                                    <span class="text-xs font-mono text-slate-500">Active In Session</span>
                                <?php else: ?>
                                    <button 
                                        onclick="infiltrateRole('<?= $rKey ?>')"
                                        class="px-3.5 py-1.5 rounded-xl bg-brandIndigo hover:bg-indigo-500 text-white font-bold text-xs transition shadow-glow-indigo flex items-center gap-1.5 ml-auto">
                                        <i class="fa-solid fa-mask"></i>
                                        <span>Infiltrate</span>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    async function infiltrateRole(roleKey) {
        try {
            const res = await fetch('<?= BASE_URL ?>/api/admin_sneak.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ role: roleKey })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || 'Failed to infiltrate persona.');
            }
        } catch (err) {
            console.error(err);
        }
    }
</script>

<?php
render_footer();
?>
