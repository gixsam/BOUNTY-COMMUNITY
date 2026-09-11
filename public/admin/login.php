<?php
// ==============================================================================
// BOUNTY COMMUNITY ENGINE — Founder & Architect Infiltration Terminal
// Path: public/admin/login.php
// Hostinger PHP 8.2 / Apache
// ==============================================================================

require_once __DIR__ . '/../config.php';

$csrfToken = $_SESSION['_csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['_csrf_token'] = $csrfToken;

$flash = consume_flash();
?>
<!DOCTYPE html>
<html lang="en" class="dark" style="background-color: #0B0F17; color-scheme: dark;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Founder & Admin Terminal | <?= htmlspecialchars(COMMUNITY_NAME) ?></title>
    <style>
        :root { color-scheme: dark; }
        html, body {
            background-color: #0B0F17 !important;
            color: #F1F5F9 !important;
        }
        .glass-card {
            background: rgba(18, 24, 38, 0.88) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
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
                        brandDark:   '#0B0F17',
                        brandPurple: '#A855F7',
                        brandIndigo: '#6366F1',
                        brandMint:   '#10B981',
                    },
                    boxShadow: {
                        'glow-purple': '0 0 30px -5px rgba(168,85,247,0.45)',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-[#0B0F17] text-slate-100 min-h-screen flex items-center justify-center p-4 selection:bg-purple-500 selection:text-white relative overflow-x-hidden">

    <!-- Ambient Purple Cyberpunk Glow -->
    <div class="fixed top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-purple-600/15 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10 my-8">

        <!-- Terminal Header -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-purple-500/15 border border-purple-500/30 text-purple-400 mb-3 text-2xl shadow-glow-purple">
                <i class="fa-solid fa-user-secret"></i>
            </div>
            
            <div class="inline-block mb-2">
                <span class="text-xs font-mono uppercase px-3 py-1 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 font-bold">
                    Executive Access Only
                </span>
            </div>

            <h1 class="text-xl font-bold text-white tracking-tight">
                Founder & Admin Terminal
            </h1>
            <p class="text-xs text-slate-400 mt-1 font-mono">
                System Telemetry, Vault Audits & Persona Teleportation
            </p>
        </div>

        <!-- Flash Notice -->
        <?php if (!empty($flash)): ?>
            <div class="mb-4 space-y-2">
                <?php foreach ($flash as $type => $msg): ?>
                    <div class="p-3 rounded-xl text-xs font-semibold flex items-center gap-2 <?= $type === 'success' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-300 border border-rose-500/30' ?>">
                        <i class="fa-solid <?= $type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                        <span><?= htmlspecialchars($msg) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Login Form Card -->
        <div class="glass-card bg-[#121826]/88 backdrop-blur-2xl border border-purple-500/30 rounded-3xl p-6 sm:p-8 shadow-glow-purple relative">

            <form action="<?= BASE_URL ?>/api/auth_handler.php" method="POST" class="space-y-4">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="portal" value="admin">

                <div>
                    <label class="block text-xs font-mono uppercase text-slate-300 mb-1.5 font-semibold">Founder Email / Identity</label>
                    <div class="relative">
                        <i class="fa-solid fa-key absolute left-3.5 top-1/2 -translate-y-1/2 text-purple-400 text-xs"></i>
                        <input 
                            type="text" 
                            name="email" 
                            id="admin-email"
                            required 
                            placeholder="elena@bounty.community"
                            class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition font-mono">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-mono uppercase text-slate-300 font-semibold">Security Token / Password</label>
                        <span class="text-[11px] text-slate-500 font-mono">Demo: Any string</span>
                    </div>
                    <div class="relative">
                        <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-purple-400 text-xs"></i>
                        <input 
                            type="password" 
                            name="password" 
                            value="founder123"
                            required 
                            placeholder="••••••••••••"
                            class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition">
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-sm shadow-glow-purple transition flex items-center justify-center gap-2 cursor-pointer mt-2">
                    <i class="fa-solid fa-shield-halved text-xs"></i>
                    <span>Authenticate & Infiltrate</span>
                </button>
            </form>

            <!-- 1-Click Quick Demo Login -->
            <div class="mt-6 pt-5 border-t border-white/10">
                <button 
                    type="button" 
                    onclick="document.getElementById('admin-email').value = 'elena@bounty.community'"
                    class="w-full p-2.5 rounded-xl bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/25 text-left transition cursor-pointer flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-purple-300 flex items-center gap-1.5">
                            <i class="fa-solid fa-crown text-[10px]"></i> Elena Vance
                        </div>
                        <div class="text-[10px] text-slate-400 font-mono">Founder & Architect · ৳50,000</div>
                    </div>
                    <span class="text-[10px] font-mono px-2 py-1 rounded bg-purple-500/20 text-purple-300 font-semibold">Quick Fill</span>
                </button>
            </div>

            <div class="mt-4 text-center">
                <a href="<?= empty(BASE_URL) ? '/' : BASE_URL . '/portal/index.php' ?>" class="text-xs text-slate-400 hover:text-white transition flex items-center justify-center gap-1">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i>
                    <span>Return to Public Portal</span>
                </a>
            </div>

        </div>

        <div class="mt-6 text-center text-xs text-slate-500 font-mono">
            Hostinger Native PHP 8.2 · PostgREST RPC Gate
        </div>

    </div>
</body>
</html>
