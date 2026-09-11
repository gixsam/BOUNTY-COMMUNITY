/**
 * ==============================================================================
 * BOUNTY COMMUNITY ENGINE — Realtime Event Feed, WebSockets & AudioFX
 * File: public/js/app.js
 * ==============================================================================
 * DIRECTIVE:
 *   1. Initialize @supabase/supabase-js v2 from CDN.
 *   2. Set up Supabase Realtime channel subscription to `community_messages` table:
 *      • Listen for INSERT events and prepend new chat messages or job cards
 *        dynamically into the DOM without full-page reloads.
 *      • Attach celebratory coin sound effect/micro-animation when new bounties go live.
 * ==============================================================================
 */

(function () {
    'use strict';

    const config = window.BOUNTY_CONFIG || {};
    const baseUrl = config.baseUrl || '';
    const csrfToken = config.csrfToken || '';
    const activeCtx = config.activeContext || {};

    let supabaseClient = null;

    /**
     * 1. CELEBRATORY COIN AUDIO SYNTHESIZER (Web Audio API)
     * Pure zero-external-dependency audio synthesis. Generates high-fidelity
     * retro-arcade coin chime (B5 987Hz -> E6 1318Hz dual-sine sparkle).
     */
    function playCelebratoryCoinSound() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            if (ctx.state === 'suspended') {
                ctx.resume();
            }

            const now = ctx.currentTime;

            // Tone 1: B5 (987.77 Hz)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(987.77, now);
            gain1.gain.setValueAtTime(0.18, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.14);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.14);

            // Tone 2: E6 (1318.51 Hz) - sparkling coin octave
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(1318.51, now + 0.07);
            gain2.gain.setValueAtTime(0.25, now + 0.07);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.07);
            osc2.stop(now + 0.45);
        } catch (err) {
            console.log('[AudioFX] WebAudio bypassed:', err);
        }
    }

    /**
     * 2. CELEBRATORY COIN MICRO-ANIMATION
     * Generates floating gold coin and sparkle particles exploding around
     * the new bounty card.
     */
    function triggerCelebratoryAnimation(anchorElem) {
        const container = document.createElement('div');
        container.className = 'fixed pointer-events-none z-[9999] inset-0 overflow-hidden';
        document.body.appendChild(container);

        let originX = window.innerWidth / 2;
        let originY = window.innerHeight / 3;

        if (anchorElem) {
            const rect = anchorElem.getBoundingClientRect();
            originX = rect.left + rect.width / 2;
            originY = Math.max(80, rect.top + 20);
        }

        const count = 18;
        for (let i = 0; i < count; i++) {
            const particle = document.createElement('div');
            particle.className = 'absolute text-lg sm:text-2xl font-bold select-none';
            particle.style.left = `${originX}px`;
            particle.style.top = `${originY}px`;
            particle.innerHTML = (i % 3 === 0) ? '✨' : (i % 2 === 0 ? '🪙' : '⚡');

            const angle = (Math.PI * 2 / count) * i + (Math.random() * 0.4 - 0.2);
            const velocity = 80 + Math.random() * 130;
            const destX = Math.cos(angle) * velocity;
            const destY = Math.sin(angle) * velocity - 70; // Float upward

            particle.animate([
                { transform: 'translate(-50%, -50%) scale(0.4) rotate(0deg)', opacity: 1 },
                { transform: `translate(calc(-50% + ${destX}px), calc(-50% + ${destY}px)) scale(1.3) rotate(${Math.random() * 360}deg)`, opacity: 0.95, offset: 0.6 },
                { transform: `translate(calc(-50% + ${destX * 1.25}px), calc(-50% + ${destY + 90}px)) scale(0.8) rotate(${Math.random() * 720}deg)`, opacity: 0 }
            ], {
                duration: 1200 + Math.random() * 400,
                easing: 'cubic-bezier(0.25, 1, 0.5, 1)'
            });

            container.appendChild(particle);
        }

        setTimeout(() => container.remove(), 1800);
    }

    /**
     * 3. TOAST NOTIFICATION PROVIDER
     */
    function showToast(message, type = 'info') {
        let toastContainer = document.getElementById('bounty-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'bounty-toast-container';
            toastContainer.className = 'fixed top-20 right-5 z-50 flex flex-col gap-2 pointer-events-none';
            document.body.appendChild(toastContainer);
        }

        const toast = document.createElement('div');
        const colorClasses = {
            success: 'border-emerald-500/30 bg-emerald-950/90 text-emerald-200 shadow-glow-mint',
            warning: 'border-amber-500/30 bg-amber-950/90 text-amber-200 shadow-glow-warning',
            error:   'border-rose-500/30 bg-rose-950/90 text-rose-200 shadow-glow-critical',
            info:    'border-indigo-500/30 bg-slate-900/95 text-indigo-200 shadow-glow-indigo'
        }[type] || 'border-white/10 bg-slate-900/90 text-slate-200';

        toast.className = `flex items-center gap-3 px-4 py-3 rounded-xl border backdrop-blur-xl shadow-xl text-sm font-medium transition-all duration-300 pointer-events-auto transform translate-y-2 opacity-0 ${colorClasses}`;
        toast.innerHTML = `
            <i class="fa-solid ${type === 'success' ? 'fa-circle-check text-brandMint' : (type === 'error' ? 'fa-triangle-exclamation text-brandRed' : 'fa-bell text-brandIndigo')}"></i>
            <span>${escapeHtml(message)}</span>
        `;

        toastContainer.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-2', 'opacity-0');
        });

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 300);
        }, 4500);
    }

    /**
     * 4. RENDERERS: CHAT MESSAGES & RICH JOB ALERT CARDS
     */
    function createJobAlertCard(data, isLive = false) {
        const card = document.createElement('div');
        const msgId = data.id || `job-${Date.now()}`;
        card.id = `feed-item-${msgId}`;
        card.setAttribute('data-message-id', msgId);
        card.className = `job-alert-card glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md rounded-2xl p-5 border border-white/10 border-l-4 border-l-[#10B981] relative overflow-hidden transition-all duration-300 hover:border-l-[#34D399] ${isLive ? 'job-card-celebrate' : ''}`;

        const meta = data.meta || data.meta_json || {};
        const title = meta.title || data.title || data.message || 'New Bounty Opportunity';
        const bountyVal = parseFloat(meta.bounty || meta.bounty_amount || data.bounty_amount || 0);
        const openings = parseInt(meta.openings || data.openings || 1, 10);
        const jobId = meta.job_id || data.job_id || data.id || '';
        const category = meta.category || data.category || 'Engineering';
        const senderHandle = data.sender_handle || (data.profiles ? data.profiles.handle : null) || 'recruiter';
        const senderAvatar = data.sender_avatar || (data.profiles ? data.profiles.avatar_url : null) || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150';
        const timeDisplay = data.created_at ? formatTime(data.created_at) : 'Just now';

        card.innerHTML = `
            <!-- Background Subtle Glow -->
            <div class="absolute -right-12 -bottom-12 w-44 h-44 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Top Header Row: Escrow Badge, Openings Counter, Coin Reward Pill -->
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
                        <span>${openings} ${openings === 1 ? 'Opening' : 'Openings'}</span>
                    </span>
                    <span class="text-[11px] font-mono text-slate-400 px-2 py-0.5 rounded bg-white/5">
                        ${escapeHtml(category)}
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
                    <span class="font-mono font-bold">$${bountyVal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    <span class="text-[10px] uppercase font-mono px-1 rounded bg-emerald-500/20 text-emerald-300">Coins</span>
                </div>
            </div>

            <!-- Bounty Title -->
            <h3 class="text-base sm:text-lg font-bold text-white mb-2 leading-snug tracking-tight hover:text-indigo-300 transition-colors">
                ${escapeHtml(title)}
            </h3>

            <!-- Excerpt / Message -->
            <p class="text-xs sm:text-sm text-slate-300 mb-4 line-clamp-2 leading-relaxed">
                ${escapeHtml(data.message || 'Task bounty with funds guaranteed in smart escrow. Verified candidates receive instant payout release upon milestone completion.')}
            </p>

            <!-- Action Bar: Poster Identity & Apply Button -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-white/5 relative z-10">
                <div class="flex items-center gap-2">
                    <img src="${escapeHtml(senderAvatar)}" alt="${escapeHtml(senderHandle)}" class="w-6 h-6 rounded-md object-cover border border-white/10">
                    <span class="text-xs text-slate-400">by <strong class="text-white">@${escapeHtml(senderHandle)}</strong></span>
                    <span class="text-[10px] font-mono text-slate-500">• ${escapeHtml(timeDisplay)}</span>
                </div>

                <div class="flex items-center gap-2">
                    <a href="${baseUrl}/portal/job_hub.php?job_id=${encodeURIComponent(jobId)}" class="px-3.5 py-1.5 rounded-xl bg-brandIndigo hover:bg-indigo-500 text-white font-bold text-xs shadow-glow-indigo transition flex items-center gap-1.5 group-hover:scale-[1.02]">
                        <span>View Details & Apply</span>
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>
            </div>
        `;
        return card;
    }

    function createChatMessageItem(data, isLive = false) {
        const item = document.createElement('div');
        const msgId = data.id || `chat-${Date.now()}`;
        item.id = `feed-item-${msgId}`;
        item.setAttribute('data-message-id', msgId);
        item.className = `chat-message-item glass-card bg-[rgba(18,24,38,0.75)] backdrop-blur-md rounded-2xl p-4 border border-white/10 transition-all duration-200 hover:border-white/20 ${isLive ? 'feed-item-new' : ''}`;

        const senderName = data.sender_name || (data.profiles ? data.profiles.display_name : null) || 'Community Member';
        const senderHandle = data.sender_handle || (data.profiles ? data.profiles.handle : null) || 'member';
        const senderRole = data.sender_role || (data.profiles ? data.profiles.role : null) || 'hunter';
        const senderAvatar = data.sender_avatar || (data.profiles ? data.profiles.avatar_url : null) || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150';
        const timeDisplay = data.created_at ? formatTime(data.created_at) : 'Just now';

        const roleBadgeClass = {
            admin: 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
            recruiter: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
            hunter: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
            mod: 'bg-rose-500/20 text-rose-300 border-rose-500/30',
            support: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30'
        }[senderRole] || 'bg-white/10 text-slate-300 border-white/10';

        item.innerHTML = `
            <div class="flex items-start gap-3">
                <img src="${escapeHtml(senderAvatar)}" alt="${escapeHtml(senderName)}" class="w-9 h-9 rounded-xl object-cover border border-white/10 mt-0.5">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="text-xs font-bold text-white">${escapeHtml(senderName)}</span>
                        <span class="text-[10px] font-mono text-slate-400">@${escapeHtml(senderHandle)}</span>
                        <span class="text-[9px] font-mono uppercase px-1.5 py-0.2 rounded border ${roleBadgeClass}">
                            ${escapeHtml(senderRole)}
                        </span>
                        <span class="text-[10px] font-mono text-slate-500 ml-auto">${escapeHtml(timeDisplay)}</span>
                    </div>
                    <div class="text-sm text-slate-200 leading-relaxed break-words">
                        ${escapeHtml(data.message || '')}
                    </div>
                </div>
            </div>
        `;
        return item;
    }

    /**
     * 5. PREPEND FEED ITEM DYNAMICALLY TO DOM
     */
    function prependFeedItem(data, isLive = false) {
        const streamBox = document.getElementById('feed-stream') || document.getElementById('chat-stream-box');
        if (!streamBox) return null;

        // Prevent duplicate rendering
        const msgId = data.id;
        if (msgId && document.getElementById(`feed-item-${msgId}`)) {
            return null;
        }

        const msgType = data.message_type || (data.bounty_amount ? 'job_broadcast' : 'chat');
        let elem = null;

        if (msgType === 'job_broadcast' || msgType === 'job_alert' || data.bounty_amount || (data.meta && data.meta.bounty)) {
            elem = createJobAlertCard(data, isLive);
            streamBox.prepend(elem);

            if (isLive) {
                // Play celebratory coin sound & particle burst
                playCelebratoryCoinSound();
                triggerCelebratoryAnimation(elem);
                const bountyTitle = (data.meta && data.meta.title) || data.title || data.message || 'New Bounty';
                showToast(`🚀 New Bounty Escrowed: ${bountyTitle}`, 'success');
            }
        } else {
            elem = createChatMessageItem(data, isLive);
            streamBox.prepend(elem);
        }

        // Re-initialize any dynamic Lucide icons
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }

        return elem;
    }

    /**
     * 6. SUPABASE REALTIME SUBSCRIPTION ENGINE
     * Subscribes to `community_messages` table per directive, as well as `chat_messages`
     * and `jobs` for comprehensive live updates.
     */
    function initRealtimeFeed() {
        if (!config.isSupabaseConfigured || !window.supabase) {
            console.log('[Realtime] Operating in local event simulation mode.');
            return;
        }

        try {
            supabaseClient = window.supabase.createClient(config.supabaseUrl, config.supabaseAnonKey);
            console.log('[Supabase Realtime] Connecting to live event channels...');

            // PRIMARY DIRECTIVE: Subscribe to community_messages table
            supabaseClient
                .channel('realtime:community_messages')
                .on(
                    'postgres_changes',
                    { event: 'INSERT', schema: 'public', table: 'community_messages' },
                    payload => {
                        console.log('[Realtime] New community_message received:', payload.new);
                        prependFeedItem(payload.new, true);
                    }
                )
                .subscribe((status) => {
                    console.log('[Supabase Realtime] community_messages subscription:', status);
                });

            // SECONDARY: Subscribe to chat_messages table (from schema.sql)
            supabaseClient
                .channel('realtime:chat_messages')
                .on(
                    'postgres_changes',
                    { event: 'INSERT', schema: 'public', table: 'chat_messages' },
                    payload => {
                        console.log('[Realtime] New chat_message received:', payload.new);
                        prependFeedItem(payload.new, true);
                    }
                )
                .subscribe();

            // TERTIARY: Subscribe to jobs table for live escrow alerts
            supabaseClient
                .channel('realtime:jobs')
                .on(
                    'postgres_changes',
                    { event: 'INSERT', schema: 'public', table: 'jobs' },
                    payload => {
                        console.log('[Realtime] New job posted:', payload.new);
                        const job = payload.new;
                        prependFeedItem({
                            id: `job-${job.id}`,
                            message: job.description || job.title,
                            message_type: 'job_broadcast',
                            meta: {
                                job_id: job.id,
                                title: job.title,
                                bounty: job.bounty_amount,
                                category: job.category,
                                openings: job.openings || 1
                            },
                            created_at: job.created_at
                        }, true);
                    }
                )
                .subscribe();

        } catch (err) {
            console.warn('[Supabase Realtime] Connection error:', err);
        }
    }

    /**
     * 7. STATUS INPUT & MODAL CONTROLLERS
     */
    function initPortalInteractions() {
        // Status Update Form
        const statusForm = document.getElementById('lounge-status-form');
        const statusInput = document.getElementById('lounge-status-input');

        if (statusForm && statusInput) {
            statusForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const text = statusInput.value.trim();
                if (!text) return;

                const submitBtn = statusForm.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;

                try {
                    statusInput.value = '';
                    const res = await window.BountyApp.sendLoungeMessage(text);
                    if (res && res.message) {
                        prependFeedItem(res.message, false);
                    }
                } catch (err) {
                    console.error('[Portal] Send status error:', err);
                    statusInput.value = text;
                } finally {
                    if (submitBtn) submitBtn.disabled = false;
                }
            });
        }

        // [Create Paid Job / Bounty] Modal Controls
        const modal = document.getElementById('create-bounty-modal');
        const openModalBtns = document.querySelectorAll('.btn-trigger-bounty-modal, #btn-open-create-bounty-modal');
        const closeModalBtns = document.querySelectorAll('.btn-close-bounty-modal');
        const bountyForm = document.getElementById('modal-create-bounty-form');

        if (modal) {
            openModalBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    const firstInput = modal.querySelector('input[name="title"]');
                    if (firstInput) firstInput.focus();
                });
            });

            closeModalBtns.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                });
            });

            // Close on backdrop click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            });

            // Handle Post Bounty Submit
            if (bountyForm) {
                bountyForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const formData = new FormData(bountyForm);
                    const bountyAmount = parseFloat(formData.get('bounty_amount') || 0);
                    const openings = parseInt(formData.get('openings') || 1, 10);
                    const title = (formData.get('title') || '').trim();
                    const category = formData.get('category') || 'Backend Architecture';
                    const skills = formData.get('skills') || '';
                    const description = (formData.get('description') || '').trim();

                    if (!title) {
                        alert('Please enter a bounty title.');
                        return;
                    }

                    if (bountyAmount <= 0) {
                        alert('Bounty reward must be greater than $0.');
                        return;
                    }

                    const submitBtn = bountyForm.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = `<span class="animate-spin mr-1.5">⏳</span> Locking Escrow...`;
                    }

                    try {
                        const payload = {
                            _csrf: csrfToken,
                            title,
                            description,
                            category,
                            bounty_amount: bountyAmount,
                            openings,
                            skills
                        };

                        const result = await window.BountyApp.postBounty(payload);
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                        bountyForm.reset();

                        // Prepend into feed stream with celebratory audio and micro-animation
                        const createdItem = prependFeedItem({
                            id: result.job_id || `job-${Date.now()}`,
                            message: description || title,
                            message_type: 'job_broadcast',
                            sender_name: activeCtx.display_name,
                            sender_handle: activeCtx.handle,
                            sender_role: activeCtx.role,
                            sender_avatar: activeCtx.avatar_url,
                            meta: {
                                job_id: result.job_id,
                                title,
                                bounty: bountyAmount,
                                openings,
                                category
                            },
                            created_at: new Date().toISOString()
                        }, true);

                    } catch (err) {
                        console.error('[PostBounty] Error:', err);
                    } finally {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = `<span>Lock Escrow & Broadcast Bounty</span> <i class="fa-solid fa-bolt text-xs ml-1"></i>`;
                        }
                    }
                });
            }
        }
    }

    function formatTime(isoStr) {
        try {
            const d = new Date(isoStr);
            if (isNaN(d.getTime())) return String(isoStr);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch {
            return 'Just now';
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

    // 8. GLOBAL API INTERFACE
    window.BountyApp = {
        showToast,
        playCelebratoryCoinSound,
        triggerCelebratoryAnimation,
        prependFeedItem,

        async postBounty(data) {
            try {
                const res = await fetch(`${baseUrl}/api/jobs_handler.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                if (!res.ok) {
                    throw new Error(result.error || 'Failed to post bounty');
                }
                showToast(`Bounty created! $${result.escrow_amount} locked in escrow.`, 'success');
                return result;
            } catch (err) {
                showToast(err.message, 'error');
                throw err;
            }
        },

        async sendLoungeMessage(message) {
            try {
                const res = await fetch(`${baseUrl}/api/chat_handler.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message })
                });
                const result = await res.json();
                if (!res.ok) {
                    throw new Error(result.error || 'Failed to send message');
                }
                return result;
            } catch (err) {
                showToast(err.message, 'error');
                throw err;
            }
        }
    };

    // Initialize on DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initRealtimeFeed();
            initPortalInteractions();
        });
    } else {
        initRealtimeFeed();
        initPortalInteractions();
    }
})();
