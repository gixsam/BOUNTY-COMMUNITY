/**
 * ==============================================================================
 * BOUNTY COMMUNITY ENGINE — Admin Sneak Mode & Top Persona Warning Bar
 * File: public/js/sneak-bar.js
 * ==============================================================================
 * DIRECTIVE:
 *   1. Fixed top strip that checks if admin impersonation is active.
 *   2. If active, inject the purple warning banner:
 *      "ADMIN SNEAK-MODE: ACTIVE | Acting As: [Username]" with a working "Exit Persona" button.
 *   3. Maintain persona quick-switch floating dock for admin testing.
 * ==============================================================================
 */

(function () {
    'use strict';

    const config = window.BOUNTY_CONFIG || {};
    const baseUrl = config.baseUrl || '';
    const csrfToken = config.csrfToken || '';
    const activeCtx = config.activeContext || {};

    const isImpersonating = !!activeCtx.is_impersonating;
    const currentUsername = activeCtx.display_name || activeCtx.handle || 'Anonymous';
    const currentHandle = activeCtx.handle || '';
    const currentRole = activeCtx.role || 'user';

    /**
     * 1. INJECT FIXED TOP PURPLE WARNING STRIP IF IMPERSONATION IS ACTIVE
     */
    function initSneakTopBanner() {
        if (!isImpersonating) return;

        // Prevent duplicate injection
        if (document.getElementById('admin-sneak-top-strip')) return;

        const banner = document.createElement('div');
        banner.id = 'admin-sneak-top-strip';
        banner.className = 'fixed top-0 left-0 right-0 z-[100] flex items-center justify-between px-4 sm:px-6 py-2 bg-gradient-to-r from-[#3B0764] via-[#581C87] to-[#3B0764] text-white border-b border-purple-500/40 shadow-[0_4px_25px_rgba(168,85,247,0.4)] backdrop-blur-xl transition-all duration-300';
        
        banner.innerHTML = `
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-purple-300"></span>
                </span>
                <span class="font-mono uppercase tracking-wider text-purple-200 text-xs font-bold flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-purple-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    ADMIN SNEAK-MODE: ACTIVE
                </span>
                <span class="text-purple-400/60 hidden sm:inline">|</span>
                <span class="text-xs text-purple-100 flex items-center gap-1.5">
                    Acting As: <strong class="text-white font-bold bg-purple-900/80 px-2 py-0.5 rounded border border-purple-400/30">${escapeHtml(currentUsername)}${currentHandle ? ` (@${escapeHtml(currentHandle)})` : ''}</strong>
                </span>
                <span class="hidden md:inline-block px-1.5 py-0.5 rounded text-[10px] uppercase font-mono font-bold bg-purple-500/20 text-purple-300 border border-purple-400/30">
                    ${escapeHtml(currentRole)}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <button 
                    id="btn-exit-sneak-persona" 
                    type="button"
                    class="flex items-center gap-1.5 px-3 py-1 rounded-lg bg-purple-500 hover:bg-purple-400 active:bg-purple-600 text-slate-950 font-bold text-xs shadow-glow-purple transition-all duration-150 transform hover:scale-[1.02] cursor-pointer">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Exit Persona</span>
                </button>
            </div>
        `;

        document.body.prepend(banner);

        // Adjust body and sticky headers to accommodate banner height
        const bannerHeight = banner.offsetHeight || 38;
        document.documentElement.style.setProperty('--admin-sneak-banner-height', `${bannerHeight}px`);
        document.body.style.paddingTop = `${bannerHeight}px`;

        // Bind Exit Persona handler
        const exitBtn = document.getElementById('btn-exit-sneak-persona');
        if (exitBtn) {
            exitBtn.addEventListener('click', handleExitPersona);
        }
    }

    /**
     * Dispatch Exit Persona request to API
     */
    async function handleExitPersona(e) {
        if (e) e.preventDefault();
        const exitBtn = document.getElementById('btn-exit-sneak-persona');
        if (exitBtn) {
            exitBtn.innerHTML = `<span class="animate-spin mr-1">⏳</span> Exiting...`;
            exitBtn.disabled = true;
        }

        try {
            const res = await fetch(`${baseUrl}/api/admin_sneak.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    action: 'exit_persona',
                    _csrf: csrfToken
                })
            });

            const data = await res.json().catch(() => null);
            if (data && data.success && data.redirect) {
                window.location.href = data.redirect;
            } else {
                // Fallback direct redirection
                window.location.href = `${baseUrl}/admin/index.php`;
            }
        } catch (err) {
            console.warn('[SneakBar] Fetch exit failed, fallback to programmatic form submit:', err);
            // Fallback native form POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `${baseUrl}/api/admin_sneak.php`;
            form.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'exit_persona';
            form.appendChild(actionInput);

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_csrf';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            document.body.appendChild(form);
            form.submit();
        }
    }

    /**
     * 2. OPTIONAL QUICK PERSONA SWITCHER DOCK (For Admin / Dev Staging)
     */
    function initPersonaSwitcherDock() {
        // Only mount switcher if user is admin, already impersonating, or mount element exists
        const mount = document.getElementById('sneak-bar-mount');
        const shouldMount = mount || isImpersonating || currentRole === 'admin' || (config.activePersona && config.activePersona.role === 'admin');

        if (!shouldMount) return;
        if (document.getElementById('sneak-bar-root')) return;

        const personas = [
            { role: 'admin', name: 'Elena Vance', label: 'Founder & Architect', icon: '👑', color: 'indigo' },
            { role: 'recruiter', name: 'Marcus Sterling', label: 'Lead Recruiter', icon: '💼', color: 'emerald' },
            { role: 'hunter', name: 'Alex Chen', label: 'Elite Bounty Hunter', icon: '🎯', color: 'amber' },
            { role: 'mod', name: 'Sarah Jenkins', label: 'Threat Patrol Mod', icon: '🛡️', color: 'rose' },
            { role: 'support', name: 'Devon Bailey', label: 'Support Desk Agent', icon: '🎧', color: 'cyan' }
        ];

        const dock = document.createElement('div');
        dock.id = 'sneak-bar-root';
        dock.className = 'hidden md:flex fixed bottom-4 left-1/2 -translate-x-1/2 z-40 items-center gap-2 p-1.5 rounded-2xl border border-white/10 bg-[#0B0F17]/90 backdrop-blur-2xl shadow-2xl transition-all duration-300';
        dock.style.boxShadow = '0 12px 40px -10px rgba(0, 0, 0, 0.8), 0 0 20px -5px rgba(99, 102, 241, 0.25)';

        dock.innerHTML = `
            <div class="flex items-center gap-2 pl-2 pr-1">
                <div class="flex items-center gap-1.5 text-xs font-mono font-bold text-slate-400 uppercase tracking-wider">
                    <span class="w-2 h-2 rounded-full ${isImpersonating ? 'bg-purple-400' : 'bg-emerald-400'} animate-pulse"></span>
                    <span class="hidden sm:inline">Persona:</span>
                </div>

                <div class="flex items-center gap-1.5 bg-white/5 border border-white/10 px-2 py-0.5 rounded-xl">
                    <span class="text-xs font-semibold text-white">${escapeHtml(currentUsername)}</span>
                    <span class="text-[10px] font-mono px-1.5 py-0.2 rounded bg-indigo-500/20 text-indigo-300 uppercase font-bold border border-indigo-500/30">
                        ${escapeHtml(currentRole)}
                    </span>
                </div>
            </div>

            <div class="h-5 w-px bg-white/10 mx-1"></div>

            <div class="flex items-center gap-1">
                ${personas.map(p => {
                    const isActive = p.role === currentRole;
                    return `
                        <button 
                            data-role="${p.role}" 
                            title="Switch to ${p.name} (${p.label})"
                            class="sneak-switch-btn px-2.5 py-1 text-xs font-medium rounded-xl flex items-center gap-1 transition-all duration-150 ${
                                isActive 
                                ? 'bg-indigo-600 text-white shadow-glow-indigo font-bold scale-105' 
                                : 'text-slate-400 hover:text-white hover:bg-white/10'
                            }">
                            <span>${p.icon}</span>
                            <span class="hidden lg:inline">${p.role.toUpperCase()}</span>
                        </button>
                    `;
                }).join('')}
            </div>

            ${isImpersonating ? `
                <div class="h-5 w-px bg-white/10 mx-1"></div>
                <button id="dock-exit-btn" title="Exit Persona Mode" class="px-2 py-1 text-xs rounded-xl bg-purple-500/20 hover:bg-purple-500/40 text-purple-300 border border-purple-500/30 font-bold transition">
                    Exit
                </button>
            ` : ''}
        `;

        document.body.appendChild(dock);

        // Bind role switch buttons
        dock.querySelectorAll('.sneak-switch-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const targetRole = btn.getAttribute('data-role');
                if (targetRole === currentRole) return;

                btn.disabled = true;
                btn.innerHTML = `<span class="animate-spin">⏳</span>`;

                try {
                    const res = await fetch(`${baseUrl}/api/admin_sneak.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            action: 'switch_mock_role',
                            role: targetRole,
                            _csrf: csrfToken
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
                    console.error('[SneakBar] Switch persona error:', err);
                    window.location.reload();
                }
            });
        });

        const dockExitBtn = document.getElementById('dock-exit-btn');
        if (dockExitBtn) {
            dockExitBtn.addEventListener('click', handleExitPersona);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initSneakTopBanner();
            initPersonaSwitcherDock();
        });
    } else {
        initSneakTopBanner();
        initPersonaSwitcherDock();
    }
})();
