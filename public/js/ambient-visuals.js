/**
 * ==============================================================================
 * BOUNTY COMMUNITY ENGINE — Ambient Visual FX Engine (Particle Grid & Video Handler)
 * File: public/js/ambient-visuals.js
 * High-performance, GPU-accelerated canvas particle grid & video fallback handler.
 * ==============================================================================
 */

(function () {
    'use strict';

    // 1. VIDEO AUTOPLAY SAFEGUARD
    const video = document.getElementById('ambient-cosmos-video');
    if (video) {
        video.muted = true;
        const playPromise = video.play();
        if (playPromise !== undefined) {
            playPromise.catch(() => {
                // Autoplay blocked by mobile battery/data saver; fallback to nebula + canvas
                video.style.opacity = '0';
            });
        }
    }

    // 2. CANVAS PARTICLE GRID INITIALIZATION
    const canvas = document.getElementById('bounty-particle-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d', { alpha: true });
    if (!ctx) return;

    // Check reduced motion preference
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) {
        canvas.style.display = 'none';
        return;
    }

    let width = 0;
    let height = 0;
    let dpr = Math.min(window.devicePixelRatio || 1, 2);
    let animationFrameId = null;
    let isDocumentVisible = true;

    // Mouse coordinates
    const mouse = {
        x: null,
        y: null,
        radius: 140,
        active: false,
        timer: null
    };

    function resize() {
        width = window.innerWidth;
        height = window.innerHeight;
        dpr = Math.min(window.devicePixelRatio || 1, 2);

        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.scale(dpr, dpr);
    }

    window.addEventListener('resize', () => {
        resize();
        initParticles();
    }, { passive: true });

    // Track mouse movement for subtle constellation reaction
    window.addEventListener('mousemove', (e) => {
        mouse.x = e.clientX;
        mouse.y = e.clientY;
        mouse.active = true;

        clearTimeout(mouse.timer);
        mouse.timer = setTimeout(() => {
            mouse.active = false;
        }, 3000);
    }, { passive: true });

    window.addEventListener('mouseleave', () => {
        mouse.active = false;
        mouse.x = null;
        mouse.y = null;
    }, { passive: true });

    // Interactive tactile ripple burst on click
    let ripples = [];
    window.addEventListener('click', (e) => {
        if (ripples.length > 5) ripples.shift();
        ripples.push({
            x: e.clientX,
            y: e.clientY,
            radius: 4,
            maxRadius: 75,
            alpha: 0.5,
            color: '6, 182, 212'
        });
    }, { passive: true });

    // Palette of subtle glowing cyber tokens
    const particleColors = [
        '99, 102, 241',  // Indigo
        '6, 182, 212',   // Cyan
        '16, 185, 129',  // Mint
        '168, 85, 247',  // Purple
        '245, 158, 11'   // Amber
    ];

    let particles = [];

    class Particle {
        constructor() {
            this.reset(true);
        }

        reset(initial = false) {
            this.x = initial ? Math.random() * width : (Math.random() > 0.5 ? 0 : width);
            this.y = initial ? Math.random() * height : Math.random() * height;
            // Subtle slow drift
            this.vx = (Math.random() - 0.5) * 0.45;
            this.vy = (Math.random() - 0.5) * 0.45;
            this.radius = Math.random() * 1.4 + 0.8;
            this.color = particleColors[Math.floor(Math.random() * particleColors.length)];
            this.baseAlpha = Math.random() * 0.4 + 0.25;
            this.alpha = this.baseAlpha;
            this.pulseSpeed = Math.random() * 0.02 + 0.008;
            this.pulseAngle = Math.random() * Math.PI * 2;
        }

        update() {
            this.x += this.vx;
            this.y += this.vy;

            // Bounce off edges gently
            if (this.x < 0 || this.x > width) this.vx *= -1;
            if (this.y < 0 || this.y > height) this.vy *= -1;

            // Subtle twinkle
            this.pulseAngle += this.pulseSpeed;
            this.alpha = this.baseAlpha + Math.sin(this.pulseAngle) * 0.15;

            // Mouse proximity reaction
            if (mouse.active && mouse.x !== null && mouse.y !== null) {
                const dx = mouse.x - this.x;
                const dy = mouse.y - this.y;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < mouse.radius) {
                    const force = (1 - dist / mouse.radius) * 0.02;
                    this.x += dx * force;
                    this.y += dy * force;
                }
            }
        }

        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(${this.color}, ${Math.max(0.1, this.alpha)})`;
            ctx.shadowColor = `rgba(${this.color}, 0.75)`;
            ctx.shadowBlur = 8;
            ctx.fill();
        }
    }

    function initParticles() {
        // Density based on screen area: ~25 on mobile, ~50 on desktop
        const count = Math.max(22, Math.min(52, Math.floor((width * height) / 28000)));
        particles = [];
        for (let i = 0; i < count; i++) {
            particles.push(new Particle());
        }
    }

    function drawConnections() {
        const maxDist = 110;
        const maxDistSq = maxDist * maxDist;

        for (let i = 0; i < particles.length; i++) {
            for (let j = i + 1; j < particles.length; j++) {
                const dx = particles[i].x - particles[j].x;
                const dy = particles[i].y - particles[j].y;
                const distSq = dx * dx + dy * dy;

                if (distSq < maxDistSq) {
                    const dist = Math.sqrt(distSq);
                    const opacity = (1 - dist / maxDist) * 0.22;

                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    ctx.strokeStyle = `rgba(99, 102, 241, ${opacity})`;
                    ctx.lineWidth = 0.75;
                    ctx.shadowBlur = 0;
                    ctx.stroke();
                }
            }

            // Connection to mouse cursor
            if (mouse.active && mouse.x !== null && mouse.y !== null) {
                const dx = particles[i].x - mouse.x;
                const dy = particles[i].y - mouse.y;
                const distSq = dx * dx + dy * dy;

                if (distSq < (mouse.radius * mouse.radius)) {
                    const dist = Math.sqrt(distSq);
                    const opacity = (1 - dist / mouse.radius) * 0.32;

                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(mouse.x, mouse.y);
                    ctx.strokeStyle = `rgba(6, 182, 212, ${opacity})`;
                    ctx.lineWidth = 1;
                    ctx.shadowBlur = 0;
                    ctx.stroke();
                }
            }
        }
    }

    function drawRipples() {
        for (let i = ripples.length - 1; i >= 0; i--) {
            const r = ripples[i];
            r.radius += 2.5;
            r.alpha *= 0.94;

            if (r.radius >= r.maxRadius || r.alpha <= 0.02) {
                ripples.splice(i, 1);
                continue;
            }

            ctx.beginPath();
            ctx.arc(r.x, r.y, r.radius, 0, Math.PI * 2);
            ctx.strokeStyle = `rgba(${r.color}, ${r.alpha})`;
            ctx.lineWidth = 1.2;
            ctx.shadowColor = `rgba(${r.color}, 0.6)`;
            ctx.shadowBlur = 6;
            ctx.stroke();
        }
    }

    function renderLoop() {
        if (!isDocumentVisible) return;

        ctx.clearRect(0, 0, width, height);

        for (let i = 0; i < particles.length; i++) {
            particles[i].update();
            particles[i].draw();
        }

        drawConnections();
        drawRipples();

        animationFrameId = requestAnimationFrame(renderLoop);
    }

    // Pause loop when user leaves tab to save CPU & battery
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            isDocumentVisible = false;
            if (animationFrameId) cancelAnimationFrame(animationFrameId);
        } else {
            isDocumentVisible = true;
            animationFrameId = requestAnimationFrame(renderLoop);
        }
    });

    // Start on DOM ready
    function start() {
        resize();
        initParticles();
        renderLoop();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
