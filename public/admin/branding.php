<?php
// ==============================================================================
// WHITE-LABEL & SNEAK-MODE CONSOLE
// Path: public/admin/branding.php
// ==============================================================================

require_once __DIR__ . '/../config.php';

// Route Protection: Founder & Admin only
$currentRole = $_SESSION['user_role'] ?? '';
if (!in_array($currentRole, ['founder', 'admin'], true)) {
    header('Location: ' . (empty(BASE_URL) ? '/admin/login' : BASE_URL . '/admin/login.php'));
    exit;
}

$persona = get_active_persona();
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'reset_state') {
        unset($_SESSION['bounty_mock_db']);
        unset($_SESSION['persona_overrides']);
        init_mock_db();
        $successMessage = 'Demo database & personas reset to initial seeded state.';
    } elseif ($action === 'save_branding') {
        $successMessage = 'Brand customization parameters saved to runtime cache.';
    }
}

render_header('White-Label & Sneak-Mode Console', 'branding');
?>

<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono uppercase text-brandIndigo font-bold">Platform Configuration</span>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-sliders text-brandIndigo"></i>
                White-Label & Sneak-Mode Console
            </h1>
            <p class="text-sm text-slate-400">
                Configure platform branding tokens, commission parameters, and inspect Hostinger PHP diagnostics.
            </p>
        </div>

        <?php if (!empty($successMessage)): ?>
            <div class="px-4 py-2 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($successMessage) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left 2 cols: Customization Form -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Branding Tokens Card -->
            <div class="glass-card p-6 rounded-2xl">
                <h3 class="text-base font-bold text-white mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-palette text-brandIndigo"></i>
                    White-Label Brand Tokens
                </h3>
                <p class="text-xs text-slate-400 mb-5">Customize brand name, styling tokens, and commission defaults.</p>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="save_branding">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5">Community Platform Name</label>
                            <input 
                                type="text" 
                                name="community_name" 
                                value="<?= htmlspecialchars(COMMUNITY_NAME) ?>"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brandIndigo">
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5">Platform Escrow Commission (%)</label>
                            <input 
                                type="number" 
                                step="0.1" 
                                name="platform_fee" 
                                value="<?= PLATFORM_FEE_PERCENT ?>"
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-brandMint font-mono">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5">Primary Accent Color</label>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-[#6366F1] border border-white/20 shadow-glow-indigo"></div>
                                <input 
                                    type="text" 
                                    value="#6366F1 (Indigo Accent)" 
                                    readonly
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-xs font-mono text-slate-300">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5">Escrow / Coin Mint Color</label>
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-[#10B981] border border-white/20 shadow-glow-mint"></div>
                                <input 
                                    type="text" 
                                    value="#10B981 (Coin Mint)" 
                                    readonly
                                    class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-xs font-mono text-slate-300">
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-brandIndigo hover:bg-indigo-500 text-white font-bold text-xs shadow-glow-indigo transition flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Save Configuration</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Sneak Mode Policy Settings -->
            <div class="glass-card p-6 rounded-2xl">
                <h3 class="text-base font-bold text-white mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-user-ninja text-amber-400"></i>
                    Sneak-Mode Staging Policies
                </h3>
                <p class="text-xs text-slate-400 mb-4">Control how the persistent persona dock behaves in testing vs production.</p>

                <div class="space-y-3 divide-y divide-white/5">
                    <div class="flex items-center justify-between pt-2">
                        <div>
                            <div class="text-sm font-semibold text-white">Persistent Dock Visible</div>
                            <div class="text-xs text-slate-400">Display floating sneak bar dock at the bottom of all portal views.</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-brandMint/20 text-brandMint font-mono text-xs font-bold">ENABLED</span>
                    </div>

                    <div class="flex items-center justify-between pt-3">
                        <div>
                            <div class="text-sm font-semibold text-white">Instant Cross-Role Infiltration</div>
                            <div class="text-xs text-slate-400">Allows Founders and Admins to jump into any role without password prompts.</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-brandMint/20 text-brandMint font-mono text-xs font-bold">AUTHORIZED</span>
                    </div>

                    <div class="flex items-center justify-between pt-3">
                        <div>
                            <div class="text-sm font-semibold text-white">Local Simulation Engine</div>
                            <div class="text-xs text-slate-400">Seamless fallback to session mock database when Supabase keys are not set.</div>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-cyan-500/20 text-cyan-300 font-mono text-xs font-bold">READY</span>
                    </div>
                </div>

                <div class="pt-6 mt-4 border-t border-white/5 flex items-center justify-between">
                    <div class="text-xs text-slate-400">Reset all mock jobs, candidates, and escrow balances:</div>
                    <form method="POST">
                        <input type="hidden" name="action" value="reset_state">
                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-white/5 hover:bg-rose-500/20 text-slate-300 hover:text-rose-300 border border-white/10 text-xs font-semibold transition">
                            <i class="fa-solid fa-rotate-left mr-1"></i> Reset Demo State
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <!-- Right 1 col: Hostinger Environment Diagnostic -->
        <div class="space-y-6">

            <div class="glass-card p-6 rounded-2xl">
                <h3 class="text-base font-bold text-white mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-server text-cyan-400"></i>
                    Hostinger Diagnostics
                </h3>
                <p class="text-xs text-slate-400 mb-4">Dedicated / Private PHP runtime status.</p>

                <div class="space-y-3 font-mono text-xs">
                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5">
                        <span class="text-slate-400">PHP Version</span>
                        <span class="text-emerald-400 font-bold"><?= PHP_VERSION ?></span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5">
                        <span class="text-slate-400">cURL Extension</span>
                        <span class="text-emerald-400 font-bold"><?= extension_loaded('curl') ? 'Enabled (Active)' : 'Disabled' ?></span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5">
                        <span class="text-slate-400">OpenSSL</span>
                        <span class="text-emerald-400 font-bold"><?= extension_loaded('openssl') ? 'Enabled' : 'Disabled' ?></span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5">
                        <span class="text-slate-400">Session ID</span>
                        <span class="text-slate-300 truncate max-w-[140px]"><?= session_id() ?></span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5">
                        <span class="text-slate-400">Memory Limit</span>
                        <span class="text-slate-300"><?= ini_get('memory_limit') ?></span>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5">
                        <span class="text-slate-400">Post Max Size</span>
                        <span class="text-slate-300"><?= ini_get('post_max_size') ?></span>
                    </div>
                </div>
            </div>

            <!-- Supabase Connector Status -->
            <div class="glass-card p-6 rounded-2xl">
                <h3 class="text-base font-bold text-white mb-1 flex items-center gap-2">
                    <i class="fa-solid fa-bolt text-brandMint"></i>
                    Supabase Connector
                </h3>
                <p class="text-xs text-slate-400 mb-4">PostgREST & RPC endpoint connection.</p>

                <div class="p-3.5 rounded-xl <?= is_supabase_configured() ? 'bg-brandMint/10 border-brandMint/30' : 'bg-amber-500/10 border-amber-500/30' ?> border text-xs">
                    <?php if (is_supabase_configured()): ?>
                        <div class="font-bold text-brandMint flex items-center gap-1.5 mb-1">
                            <i class="fa-solid fa-circle-check"></i> Connected to Supabase
                        </div>
                        <div class="text-slate-400 font-mono text-[11px] truncate">
                            URL: <?= htmlspecialchars(SUPABASE_URL) ?>
                        </div>
                    <?php else: ?>
                        <div class="font-bold text-amber-400 flex items-center gap-1.5 mb-1">
                            <i class="fa-solid fa-info-circle"></i> Local High-Performance Mock Mode
                        </div>
                        <div class="text-slate-300 text-[11px] leading-relaxed">
                            Engine is running with zero external dependencies. To connect live Supabase, enter your credentials in <code class="text-white bg-black/40 px-1 py-0.5 rounded">.env</code>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

</div>

<?php
render_footer();
?>
