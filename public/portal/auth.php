<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — User Portal Authentication (Login & Registration)
// Path: public/portal/auth.php
// Hostinger PHP 8.2 / Apache
// ==============================================================================

require_once __DIR__ . '/../config.php';

$activeTab = ($_GET['tab'] ?? 'login') === 'signup' ? 'signup' : 'login';
$csrfToken = $_SESSION['_csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['_csrf_token'] = $csrfToken;

$flash = consume_flash();
?>
<!DOCTYPE html>
<html lang="en" class="dark" style="background-color: #0B0F17; color-scheme: dark;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication | <?= htmlspecialchars(COMMUNITY_NAME) ?></title>
    <style>
        :root { color-scheme: dark; }
        html, body {
            background-color: #070A11 !important;
            color: #F1F5F9 !important;
        }
        .glass-card, .bg-stitch-card {
            background: rgba(15, 23, 42, 0.75) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
        }
    </style>
    <link rel="stylesheet" href="<?= BASE_URL ?>/css/stitch-tokens.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brandDark:   '#070A11',
                        brandCard:   'rgba(15,23,42,0.75)',
                        brandIndigo: '#6366F1',
                        brandMint:   '#10B981',
                        brandPurple: '#A855F7',
                        brandCyan:   '#06B6D4',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-[#070A11] text-slate-100 min-h-screen flex items-center justify-center p-4 selection:bg-indigo-500 selection:text-white relative overflow-x-hidden">

    <?php render_ambient_background(); ?>

    <div class="w-full max-w-md mx-auto mt-16 relative z-10 mb-12">

        <!-- Brand Icon & Header -->
        <div class="text-center mb-6">
            <a href="<?= empty(BASE_URL) ? '/' : BASE_URL . '/portal/index.php' ?>" class="inline-flex items-center gap-3 group mb-3">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-indigo-500 via-purple-500 to-cyan-400 flex items-center justify-center shadow-[0_0_25px_rgba(99,102,241,0.5)] group-hover:scale-105 transition-transform">
                    <i class="fa-solid fa-bolt text-slate-950 font-black text-xl"></i>
                </div>
                <div class="text-left">
                    <div class="font-extrabold text-lg tracking-tight text-white flex items-center gap-1.5">
                        <?= htmlspecialchars(COMMUNITY_NAME) ?>
                        <span class="badge-cyan text-[10px] uppercase font-mono px-2 py-0.5 rounded font-bold">Portal</span>
                    </div>
                    <div class="text-xs text-slate-400 font-mono -mt-0.5">Community Identity & Auth Gate</div>
                </div>
            </a>
            <p class="text-xs text-slate-400">Access your bounty wallet, manage escrow pipelines, and screen candidates.</p>
        </div>

        <!-- Flash Notice -->
        <?php if (!empty($flash)): ?>
            <div class="mb-4 space-y-2">
                <?php foreach ($flash as $type => $msg): ?>
                    <div class="p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= $type === 'success' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-300 border border-rose-500/30' ?>">
                        <i class="fa-solid <?= $type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                        <span><?= htmlspecialchars($msg) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Main Auth Card -->
        <div class="glass-card bg-[#0F172A]/80 backdrop-blur-2xl border border-white/10 border-t-white/20 rounded-3xl max-w-md mx-auto p-8 shadow-[0_25px_60px_-15px_rgba(0,0,0,0.9),0_0_30px_rgba(99,102,241,0.15)] relative">

            <!-- Tab Switcher -->
            <div class="flex items-center p-1 rounded-xl bg-white/5 border border-white/10 mb-6 text-xs font-semibold">
                <button 
                    type="button" 
                    id="tab-login-btn"
                    onclick="switchTab('login')" 
                    class="flex-1 py-2 rounded-lg transition-all text-center <?= $activeTab === 'login' ? 'bg-brandIndigo text-white shadow-glow-indigo font-bold' : 'text-slate-400 hover:text-white' ?>">
                    <i class="fa-solid fa-arrow-right-to-bracket mr-1.5"></i>Login
                </button>
                <button 
                    type="button" 
                    id="tab-signup-btn"
                    onclick="switchTab('signup')" 
                    class="flex-1 py-2 rounded-lg transition-all text-center <?= $activeTab === 'signup' ? 'bg-brandIndigo text-white shadow-glow-indigo font-bold' : 'text-slate-400 hover:text-white' ?>">
                    <i class="fa-solid fa-user-plus mr-1.5"></i>Sign Up
                </button>
            </div>

            <!-- LOGIN PANE -->
            <div id="pane-login" class="<?= $activeTab === 'login' ? '' : 'hidden' ?>">
                <form action="<?= BASE_URL ?>/api/auth_handler.php" method="POST" class="space-y-4">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="portal" value="portal">

                    <div>
                        <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5 font-semibold">Email or Handle</label>
                        <div class="relative">
                            <i class="fa-solid fa-at absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input 
                                type="text" 
                                name="email" 
                                id="login-email"
                                required 
                                placeholder="alex@buildspace.dev or alex_code"
                                class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo focus:ring-1 focus:ring-brandIndigo transition">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-mono uppercase text-slate-300 font-semibold">Password</label>
                            <span class="text-[11px] text-slate-500 font-mono">Demo: Any string</span>
                        </div>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input 
                                type="password" 
                                name="password" 
                                value="password123"
                                required 
                                placeholder="••••••••••••"
                                class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo focus:ring-1 focus:ring-brandIndigo transition">
                        </div>
                    </div>

                    <button 
                        type="submit" 
                        class="btn-luxury-primary w-full py-2.5 rounded-xl text-white font-bold text-sm shadow-glow-indigo transition flex items-center justify-center gap-2 cursor-pointer mt-2 hover:scale-[1.01]">
                        <span>Sign In to Community</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </form>

                <!-- 1-Click Demo Accounts -->
                <div class="mt-6 pt-5 border-t border-white/10">
                    <div class="text-[11px] font-mono uppercase text-slate-400 mb-2.5 text-center font-semibold">Instant 1-Click Demo Accounts</div>
                    <div class="grid grid-cols-2 gap-2">
                        <button 
                            type="button" 
                            onclick="quickFill('alex@buildspace.dev')"
                            class="p-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 text-left transition cursor-pointer">
                            <div class="text-xs font-bold text-amber-400 flex items-center gap-1">
                                <i class="fa-solid fa-crosshairs text-[10px]"></i> Alex Chen
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono">Hunter · ৳3,820</div>
                        </button>
                        <button 
                            type="button" 
                            onclick="quickFill('marcus@hypergrowth.vc')"
                            class="p-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 text-left transition cursor-pointer">
                            <div class="text-xs font-bold text-emerald-400 flex items-center gap-1">
                                <i class="fa-solid fa-user-tie text-[10px]"></i> Marcus S.
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono">Recruiter · ৳12,450</div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SIGNUP PANE -->
            <div id="pane-signup" class="<?= $activeTab === 'signup' ? '' : 'hidden' ?>">
                <form action="<?= BASE_URL ?>/api/auth_handler.php" method="POST" class="space-y-3.5">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="signup">
                    <input type="hidden" name="portal" value="portal">

                    <!-- Role Selector (Hunter vs Recruiter) -->
                    <div>
                        <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5 font-semibold">Select Account Role</label>
                        <div class="grid grid-cols-2 gap-2.5">
                            <label class="p-2.5 rounded-xl border border-white/10 bg-white/5 cursor-pointer flex items-center gap-2 hover:border-amber-400/50 has-[:checked]:border-amber-400 has-[:checked]:bg-amber-500/10 transition">
                                <input type="radio" name="role" value="hunter" checked class="accent-amber-400">
                                <div>
                                    <div class="text-xs font-bold text-white">Hunter</div>
                                    <div class="text-[10px] text-slate-400">Solve tasks & earn</div>
                                </div>
                            </label>
                            <label class="p-2.5 rounded-xl border border-white/10 bg-white/5 cursor-pointer flex items-center gap-2 hover:border-emerald-400/50 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-500/10 transition">
                                <input type="radio" name="role" value="recruiter" class="accent-emerald-400">
                                <div>
                                    <div class="text-xs font-bold text-white">Recruiter</div>
                                    <div class="text-[10px] text-slate-400">Post jobs & hire</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase text-slate-300 mb-1 font-semibold">Full Display Name</label>
                        <input 
                            type="text" 
                            name="display_name" 
                            required 
                            placeholder="e.g. John Doe"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo transition">
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase text-slate-300 mb-1 font-semibold">Unique Handle</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-mono">@</span>
                            <input 
                                type="text" 
                                name="handle" 
                                required 
                                placeholder="johndoe"
                                class="w-full bg-white/5 border border-white/10 rounded-xl pl-8 pr-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo transition">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase text-slate-300 mb-1 font-semibold">Email Address</label>
                        <input 
                            type="email" 
                            name="email" 
                            required 
                            placeholder="john@example.com"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo transition">
                    </div>

                    <div>
                        <label class="block text-xs font-mono uppercase text-slate-300 mb-1 font-semibold">Password</label>
                        <input 
                            type="password" 
                            name="password" 
                            required 
                            placeholder="••••••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-3.5 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brandIndigo transition">
                    </div>

                    <button 
                        type="submit" 
                        class="btn-luxury-emerald w-full py-2.5 rounded-xl text-white font-bold text-sm shadow-glow-mint transition flex items-center justify-center gap-2 cursor-pointer mt-2 hover:scale-[1.01]">
                        <span>Create Account & Join</span>
                        <i class="fa-solid fa-sparkles text-xs"></i>
                    </button>
                </form>
            </div>

            <!-- Terminal Links -->
            <div class="mt-6 pt-4 border-t border-white/10 text-center">
                <a href="<?= empty(BASE_URL) ? '/' : BASE_URL . '/portal/index.php' ?>" class="text-xs text-slate-400 hover:text-white transition flex items-center justify-center gap-1">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>Continue to Lounge as Guest</span>
                </a>
            </div>

        </div>

        <!-- Footer Notice -->
        <div class="mt-6 text-center text-xs text-slate-500 font-mono">
            Hostinger PHP 8.2 · Supabase Auth & RLS · Atomic Escrow Vault
        </div>

    </div>

    <script>
        function switchTab(tab) {
            const loginPane = document.getElementById('pane-login');
            const signupPane = document.getElementById('pane-signup');
            const loginBtn = document.getElementById('tab-login-btn');
            const signupBtn = document.getElementById('tab-signup-btn');

            if (tab === 'signup') {
                loginPane.classList.add('hidden');
                signupPane.classList.remove('hidden');
                signupBtn.className = 'flex-1 py-2 rounded-lg transition-all text-center bg-brandIndigo text-white shadow-glow-indigo font-bold';
                loginBtn.className = 'flex-1 py-2 rounded-lg transition-all text-center text-slate-400 hover:text-white';
            } else {
                signupPane.classList.add('hidden');
                loginPane.classList.remove('hidden');
                loginBtn.className = 'flex-1 py-2 rounded-lg transition-all text-center bg-brandIndigo text-white shadow-glow-indigo font-bold';
                signupBtn.className = 'flex-1 py-2 rounded-lg transition-all text-center text-slate-400 hover:text-white';
            }
        }

        function quickFill(email) {
            document.getElementById('login-email').value = email;
        }
    </script>
    <script src="<?= BASE_URL ?>/js/ambient-visuals.js"></script>
</body>
</html>
