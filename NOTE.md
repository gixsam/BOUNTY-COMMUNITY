# 📋 BOUNTY COMMUNITY ENGINE — MASTER PROJECT NOTE & CHANGELOG (`NOTE.md`)

> **Project Name:** Bounty Community Engine  
> **Target Hosting:** Dedicated / Private Hostinger Web Plan (PHP 8.2+, Apache/Nginx, cURL enabled)  
> **Database & Backend:** Supabase PostgreSQL (Project ID: `ehswbdmizytpahosqkuj`, Region: `ap-southeast-2`)  
> **Frontend Stack:** HTML5, Tailwind CSS CDN (Stitch Design Tokens), Vanilla JS with `@supabase/supabase-js` v2 CDN, Lucide Icons, Web Audio API  
> **Local Project Root:** `D:\TECH\WEBSITE\BOUNTY COMMUNITY\`  
> **Google Drive Storage:** `G:\My Drive\ALL WEBSITE WORKPLACE\BOUNTY COMMUNITY WORKPLACE\`  
> **Last Synchronized:** 2026-09-11 09:44 Local Time  

---

## 📌 1. Master Rule & Maintenance Directive

This file (`NOTE.md`) is the **single source of truth** for all historical, present, and future updates to the Bounty Community platform.
Whenever any file, endpoint, database schema, or configuration is updated:
1. **Document the update** in this file under Section 3 (Completed Updates) or Section 4 (Upcoming Tasks).
2. **Synchronize this file to all designated locations**:
   - Local Project Folder: `D:\TECH\WEBSITE\BOUNTY COMMUNITY\NOTE.md`
   - Google Drive Workplace: `G:\My Drive\ALL WEBSITE WORKPLACE\BOUNTY COMMUNITY WORKPLACE\NOTE.md`

---

## 🏗️ 2. Architectural Blueprint & Technical Stack

### System Overview
Bounty Community is a zero-external-dependency, high-performance freelance bounty and talent matchmaking engine engineered to run on shared or private Hostinger PHP environments while leveraging Supabase PostgreSQL, Row Level Security (RLS), and Realtime websockets.

```text
bounty community/
├── .agent/
│   └── instructions.md              <-- Architect directives & synchronization rules
├── NOTE.md                          <-- Master project updates & roadmap log (This file)
├── README.md                        <-- Repository overview & documentation
├── index.php                        <-- Root router redirecting to /public/portal/index.php (Hostinger fix)
├── schema.sql                       <-- DDL: 8 Tables, 2 RPCs, RLS, Indexes, Seed Data
├── public/
│   ├── .env                         <-- Live environment configuration (Supabase URL, Anon Key, etc.)
│   ├── .env.example                 <-- Environment variable template
│   ├── .htaccess                    <-- Apache URL rewrite & HTTP security hardening
│   ├── index.php                    <-- Root router redirecting to /portal/index.php
│   ├── config.php                   <-- Security boot, Session hardening, User context, Master Header & Mobile Dock
│   ├── api/
│   │   ├── admin_sneak.php          <-- Admin persona teleportation & impersonation controller (JSON + Form)
│   │   ├── jobs_handler.php         <-- Job posting & escrow locking via post_job_with_escrow()
│   │   ├── payout_handler.php       <-- Escrow disbursement via hire_and_release_payout()
│   │   ├── applications_handler.php <-- Candidate screening actions (shortlist, reject, hire)
│   │   ├── telegram_dispatcher.php  <-- Automated Telegram notification bridge
│   │   └── chat_handler.php         <-- Lounge chat & broadcast messaging endpoint
│   ├── portal/
│   │   ├── index.php                <-- Live Lounge Feed, Status Box, Modal & Rich Job Alert Cards
│   │   ├── job_hub.php              <-- Job Listings & Recruiter ATS
│   │   └── candidate_review.php     <-- 100-to-2 Applicant Screening Accordion
│   ├── mod/
│   │   └── index.php                <-- Threat Patrol & Task Verification Queue
│   ├── support/
│   │   └── index.php                <-- Staff Support Desk & Escrow Dispute Split-Pane
│   ├── admin/
│   │   ├── index.php                <-- Founder Telemetry & Infiltration Center
│   │   └── branding.php             <-- White-Label Branding & Hostinger Diagnostics
│   ├── cron/
│   │   └── task_expirations.php     <-- Automated cron job for expiring stale open bounties
│   ├── css/
│   │   └── stitch-tokens.css        <-- Google Stitch cyberpunk design tokens, utilities & animations
│   └── js/
│       ├── app.js                   <-- Realtime event bus, community_messages subscription & AudioFX
│       └── sneak-bar.js             <-- Fixed top purple sneak-mode banner & persona switcher dock
```

### Design Tokens (Luminescent Jewel & Google Stitch Cyberpunk Palette)
- **Canvas / Background:** `#070A11` (Deep obsidian caviar)
- **Specular Glass Cards:** `rgba(15, 23, 42, 0.70)` with `backdrop-blur-xl`, `border-t-white/20`, and `box-shadow: inset 0 1px 0 0 rgba(255,255,255,0.10)`
- **Luminescent Solar Amber / Gold:** `#F59E0B` (Escrow Vaults, Leaderboard 1st, Hunter badges, Vault Coins)
- **Electric Cyan:** `#06B6D4` (Active Bounties, Vacancies, Support Desk, WebSockets)
- **Cyber Jade / Mint Emerald:** `#10B981` (Hostinger Engine, Escrow Ready balances, Recruiter badges, Payout releases)
- **Hyper Indigo & Royal Violet:** `#6366F1`, `#A855F7` (Brand Aura, Founder Telemetry, Primary luxury CTAs)
- **Rose Crimson:** `#F43F5E` / `#EF4444` (Threat Patrol, Sybil flags, dispute verification)
- **Admin Sneak Purple:** `#A855F7` (Fixed top impersonation warning banner & switcher dock)

---

## ⚡ 3. Detailed Record of Completed Updates

### [Phase 1] Core DDL & Database Engine (`schema.sql`)
- Profiles, Jobs, Applications, Escrow Ledger, Chat Messages, Task Verifications, Support Tickets/Messages.
- Atomic stored procedures: `post_job_with_escrow(...)` and `hire_and_release_payout(...)`.
- Predefined personas seed data.

### [Phase 2] Security-Hardened Configuration & User Context (`public/config.php`)
- Strict session cookies, HTTP security headers, input sanitizers.
- Dynamic context resolver `get_active_user_context()`.

### [Phase 3] Administrative Teleportation & Sneak Mode (`public/api/admin_sneak.php`)
- CSRF defense, write-once admin locking, JSON and Form dual payload decoding.

### [Phase 4] Production Jobs & Escrow API (`public/api/jobs_handler.php`)
- Atomic RPC invocation of `public.post_job_with_escrow(...)` with validation and fee handling.

### [Phase 5] Payout Release & Escrow Disbursement API (`public/api/payout_handler.php`)
- Atomic RPC invocation of `public.hire_and_release_payout(...)` with double authorization and platform fee calculation.

### [Phase 6] Client-Side Realtime & UI Controls
- Realtime event dispatcher (`public/js/app.js`) and portal views.

### [Phase 7] Master Layout Shell, Stitch Design Tokens & Sneak Mode Enhancements
- Created `public/css/stitch-tokens.css` with Google Stitch tokens and cyberpunk utilities.
- Implemented fixed top purple admin sneak-mode strip in `public/js/sneak-bar.js`.
- Integrated master sticky navbar with level badge, live coin pill, and mobile bottom floating glass dock in `public/config.php`.

### [Phase 8] Live Community Lounge & Real-Time Event Feed
- **Responsive Layout (`public/portal/index.php`):**
  - Two-column responsive desktop layout (`lg:col-span-8` main feed and `lg:col-span-4` sidebar) that cleanly collapses to a single column on mobile viewports.
  - Status update input box with user profile context, live status indicator, and prominent `[Create Paid Job / Bounty]` modal trigger.
  - Interactive `[Create Paid Job / Bounty]` glass modal with dynamic escrow calculations, category filters, openings counter, and atomic escrow locking submit.
  - Message stream container (`#feed-stream`) pre-rendering both standard chat messages and rich Job Alert Cards (bounty title, coin reward pill, openings counter, and `[View Details & Apply]` action button).
- **Supabase Realtime Channel & AudioFX (`public/js/app.js`):**
  - Initialized `@supabase/supabase-js` v2 from CDN.
  - Subscribed to Supabase Realtime channel on `community_messages` table (and `chat_messages` / `jobs` tables) for `INSERT` events.
  - Implemented dynamic DOM prepending without page reloads.
  - Integrated Web Audio API synthesized celebratory coin sound effect (`playCelebratoryCoinSound()` via B5 987Hz -> E6 1318Hz dual-sine chime).
  - Implemented celebratory floating coin and sparkle micro-animation (`triggerCelebratoryAnimation()`) with glowing card border flash and toasts.
- **Design Tokens Stylesheet Enhancements (`public/css/stitch-tokens.css`):**
  - Added `@keyframes feedItemEnter` and `@keyframes celebratoryGlow` with utility classes `.feed-item-new` and `.job-card-celebrate`.

### [Phase 9] Hostinger Deployment Root Router (`index.php`) & Git Remote Push
- **Hostinger Deployment Root Resolution:**
  - Hostinger auto-deploys repository root directly into `public_html`, causing a 403 Forbidden error because no entry `index.php` existed in the repository root directory.
  - Created root router `index.php` in the repository root (`index.php`) that cleanly issues an immediate HTTP 302 redirect to `/public/portal/index.php`:
    ```php
    <?php
    // Root router to public portal
    header("Location: /public/portal/index.php");
    exit;
    ```
  - Validated PHP syntax cleanly via CLI (`No syntax errors detected in index.php`).
- **File Inventory Update:** Registered `index.php` (Root Router) in master file inventory.
- **Git Tracking & Remote Push:**
  - Tracked and committed `index.php` to branch `main`.
  - Pushed commits directly to GitHub origin repository (`https://github.com/gixsam/BOUNTY-COMMUNITY.git`).
- **Google Drive Workplace Sync:**
  - Synchronized updated `NOTE.md` to `G:\My Drive\ALL WEBSITE WORKPLACE\BOUNTY COMMUNITY WORKPLACE\NOTE.md`.

### [Phase 10] Obsidian Midnight Dark Background & Dynamic Asset Path Resolution
- **Root Cause Analysis:**
  - When accessing `/public/portal/index.php` directly on Hostinger, the body lacked explicit background classes and defaulted to white canvas.
  - Furthermore, `BASE_URL` was resolving to empty string `""`, causing stylesheet link `<link rel="stylesheet" href="/css/stitch-tokens.css">` to 404 since the actual file is at `/public/css/stitch-tokens.css`.
  - With `stitch-tokens.css` failing to load, `.glass-card` styling was absent, rendering white text against a white background.
- **Architectural & Visual Fixes:**
  - **`public/config.php`:**
    - Implemented `resolve_public_base_url()` to dynamically detect `/public/` in `SCRIPT_NAME` or `REQUEST_URI` and set `BASE_URL` to `'/public'` (with fallback support in `render_header()` and `render_footer()`).
    - Added critical inline `<style>` to `<head>` (`html, body { background-color: #0B0F17 !important; color: #F1F5F9 !important; } .glass-card { background: rgba(18, 24, 38, 0.75) !important; }`) to completely eliminate white FOUC.
    - Updated `<body>` tag with explicit dark theme classes:
      `<body class="bg-[#0B0F17] text-slate-100 min-h-screen flex flex-col antialiased selection:bg-indigo-500 selection:text-white pb-28 md:pb-12 has-bottom-dock">`
  - **`public/portal/index.php`:**
    - Wrapped page layout in `<div class="portal-outer-wrapper w-full min-h-screen bg-[#0B0F17] text-slate-100">`.
    - Enforced `bg-[rgba(18,24,38,0.75)] backdrop-blur-md` across all cards, modals, and sidebar widgets.
  - **`public/js/app.js`:**
    - Enforced `bg-[rgba(18,24,38,0.75)] backdrop-blur-md` on dynamically injected Job Alert Cards and Chat Messages.
- **Verification & Git Push:**
  - Syntax validated with `php -l public/config.php` and `php -l public/portal/index.php`.
  - Verified dynamic prefixing (`/public/css/stitch-tokens.css`, `/public/js/app.js`, `/public/js/sneak-bar.js`).
  - Synchronized `NOTE.md` across local and Google Drive workplace directories.

### [Phase 11] Full Social Community Rollout, Stitch Design, BDT Localization, Role Logins & Clean URLs

- **1. Clean Root URLs & Apache URL Rewriting (`.htaccess`, `index.php`):**
  - Configured root `.htaccess` (`.htaccess`) for Hostinger Apache:
    - Blocked unauthorized browser access to sensitive files and directories: `schema.sql`, `NOTE.md`, `.env*`, `.agent/`, and `.git*` (HTTP 403 Forbidden).
    - Preserved direct static asset access without 404s: `/css/*` -> `public/css/*`, `/js/*` -> `public/js/*`, `/api/*` -> `public/api/*`.
    - Established canonical clean URL routes:
      * `/` -> `public/portal/index.php` (User Lounge & Bounties)
      * `/login` and `/signup` -> `public/portal/auth.php` (Tabbed Hunter/Recruiter Auth)
      * `/admin` -> `public/admin/index.php`
      * `/admin/login` -> `public/admin/login.php` (Founder & Admin Terminal)
      * `/support` -> `public/support/index.php`
      * `/support/login` -> `public/support/login.php` (Customer Support Desk)
      * `/staff` and `/stuff` -> `public/mod/index.php`
      * `/staff/login` and `/stuff/login` -> `public/mod/login.php` (Threat Patrol Staff Terminal)
  - Maintained root `index.php` as a syntax-clean immediate 302 redirect fallback to `/public/portal/index.php` resolving Hostinger document root edge cases.

- **2. Floating Glass Inset Header & Google Stitch Material Symbols (`public/config.php`, `public/css/stitch-tokens.css`):**
  - Loaded Google Stitch Material Symbols in HTML `<head>`:
    `<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />`
  - Redesigned master navigation bar with breathing room and Google Stitch glass tokens:
    - Inset container: `<div class="w-full pt-4 px-4 sm:px-6 max-w-7xl mx-auto sticky top-3 z-40">`
    - Elevated navbar: `bg-[#121826]/85 backdrop-blur-xl border border-slate-700/60 rounded-2xl shadow-2xl px-6 py-3.5 flex items-center justify-between`
  - Fixed awkward multi-line text wrapping on navigation items using `whitespace-nowrap text-xs font-semibold`.
  - Replaced all header and bottom dock icons with Google Stitch icon tokens:
    * Lounge Feed -> `<span class="material-symbols-outlined">forum</span>`
    * Job Hub -> `<span class="material-symbols-outlined">cases</span>`
    * Screening -> `<span class="material-symbols-outlined">how_to_reg</span>`
    * Threat Patrol -> `<span class="material-symbols-outlined">shield</span>`
    * Staff Desk -> `<span class="material-symbols-outlined">support_agent</span>`
    * Wallet Coin -> `<span class="material-symbols-outlined">monetization_on</span>`
  - Implemented role-aware navigation: standard members only see core items, while internal management tools are tucked into an elegant `Staff Suite ▾` dropdown.
  - Enforced single-dock guarantee: fixed bottom purple sneak bar (`#sneak-bar-root`) displayed exclusively on desktop (`hidden md:flex`), mobile dock exclusively on mobile (`md:hidden`), eliminating dual-dock collisions and card occlusions.

- **3. Universal Bangladeshi Taka (৳ BDT) Standardization (`public/config.php`):**
  - Standardized `format_bdt($amount)` global helper in `public/config.php`:
    ```php
    function format_bdt($amount) {
        return '৳' . number_format((float)$amount, 2);
    }
    ```
  - Replaced all USD (`$`) symbols across the entire platform:
    * Master Header wallet pill: `৳50,000.00 COINS`
    * Lounge Feed (`public/portal/index.php`): Vault stats (`৳74,270.00`), user identity card (`৳50,000.00 Escrow Ready`), open bounty cards (`৳2,500.00`, `৳1,800.00`, `৳3,200.00`), and modal reward inputs
    * Job Hub ATS (`public/portal/job_hub.php`): Bounty reward pills, escrow calculation previews, and balance notices
    * Admin Telemetry (`public/admin/index.php`): Custody metrics, platform revenue, liquidity pools, and user balance directory
    * Client-side DOM (`public/js/app.js`): Dynamic job cards (`৳${bountyVal...}`), input validation (`৳0`), and toast alerts (`৳${result.escrow_amount}`)
    * Backend Handlers & Dispatcher: `jobs_handler.php`, `payout_handler.php`, and `telegram_dispatcher.php`

- **4. Candidate Review Screen Fallback & Dark Theme Integration (`public/portal/candidate_review.php`):**
  - Removed raw `die('Invalid job identifier.')` crashes when no `job_id` query parameter is provided.
  - Added automatic fallback to query the first active bounty from Supabase or session mock database.
  - Rendered a centered Google Stitch obsidian glass empty state (`bg-[#121826]/75 border border-slate-700/60 rounded-2xl p-10 max-w-xl mx-auto mt-12 text-center shadow-2xl`) with glowing icon, helpful guidance, and `[Open Job Hub & ATS]` CTA.
  - Integrated full master layout shell (`render_header()` and `render_footer()`) and added `pb-24` bottom padding for obstacle-free candidate review.

- **5. Role-Specific Login Portals & Server-Side Auth Gatekeeper (`public/portal/auth.php`, `public/admin/login.php`, `public/support/login.php`, `public/mod/login.php`, `public/api/auth_handler.php`):**
  - **User Portal Auth (`public/portal/auth.php`):**
    - Clean tab switcher for Login and Sign Up.
    - Role selector for registration offering `Hunter` ("Solve tasks & earn") and `Recruiter` ("Post jobs & hire").
    - Obsidian card styling: `bg-[#121826]/85 backdrop-blur-xl border border-white/10 rounded-2xl max-w-md mx-auto p-8 shadow-2xl relative` with top spacing `mt-16`.
    - Clean fallback return link routing to `/`.
  - **Dedicated Staff Login Terminals (Login-Only):**
    - Admin Login (`public/admin/login.php`): Title & `<h1>` "Founder & Admin Terminal", purple accent, quick-fill for Elena Vance (`elena@bounty.community`).
    - Support Login (`public/support/login.php`): Title & `<h1>` "Customer Support Desk", teal accent, quick-fill for Devon Bailey (`support@bounty.community`).
    - Staff/Mod Login (`public/mod/login.php`): Title & `<h1>` "Threat Patrol Staff Terminal", red accent, quick-fill for Sarah Jenkins (`mod@bounty.community`).
  - **Authentication Controller (`public/api/auth_handler.php`):**
    - Multi-tier identity lookup: predefined personas, mock registered accounts, and Supabase `public.profiles` by email or handle.
    - Server-side boundary enforcement: Hunters and Recruiters attempting access to `/admin`, `/support`, or `/staff` terminals are rejected with HTTP 403 / redirect to terminal logins.
    - Session cookie issuance with strict role state tracking and clean destination routing on successful authentication.
  - **Header Gatekeeping on Protected Pages:**
    - `public/admin/index.php` & `public/admin/branding.php`: Redirect unauthenticated visitors to `/admin/login`.
    - `public/support/index.php`: Redirect unauthenticated visitors to `/support/login`.
    - `public/mod/index.php`: Redirect unauthenticated visitors to `/staff/login`.

- **6. Social Community Lounge Reactions, In-Stream Q&A Threads & Leaderboard (`public/portal/index.php`, `public/js/app.js`, `public/api/reaction_handler.php`, `public/api/comment_handler.php`):**
  - **One-Click Emoji Reactions Engine:**
    - Added interactive reaction buttons (👍 Like, 🚀 Launch, 🪙 Bounty, 🔥 Fire) with live reaction counters under all Lounge chat messages and escrowed job alert cards.
    - Built optimistic UI feedback with micro-bounce animations (`scale-110`), immediate count adjustments, and active indigo styling.
    - Created `public/api/reaction_handler.php` supporting GET (reaction totals + active user state) and POST (toggle/increment/decrement) with session state persistence and real-time Supabase broadcasting via `chat_messages` (`meta_json.type = 'reaction_event'`).
  - **In-Stream Bounty Q&A & Quick Reply Threads:**
    - Built quick question/inquiry thread under each job alert card directly on the social wall, allowing hunters to ask questions before applying.
    - Created `public/api/comment_handler.php` supporting GET (job comments) and POST (post inquiry/reply) with sender context preservation and Supabase broadcasting (`meta_json.type = 'job_reply'`).
    - Integrated real-time Supabase websocket listener in `public/js/app.js` to dynamically append incoming comments to the relevant bounty card's discussion thread without page refresh.
  - **Sidebar Community Leaderboard & Live Online Stats:**
    - Added Live Community Activity widget to the Lounge sidebar showing live counts of active members (142 online, 89 hunters, 24 recruiters, 29 tasks).
    - Built "Top Earners this Week" Leaderboard widget showcasing top 4 performers with ranking medals (🥇, 🥈, 🥉, ⭐), persona badges, bounties won, and total earnings rendered in universal Bangladeshi Taka (`৳ BDT`).
  - **Global JavaScript Interface:**
    - Extended `window.BountyApp` with `toggleReaction()`, `toggleCommentBox()`, and `appendJobCommentToDOM()`.
    - Added delegated event listeners for all dynamic and static `.btn-reaction` buttons and `.job-reply-form` submissions.

### [Phase 12] Tri-Layer Ambient Visual FX Engine (Deep-Space Video, CSS Nebula & Interactive Particle Grid)
- **Layer 0: Muted Deep-Space Video Background (Option C):**
  - Integrated a hardware-accelerated looping cosmic video background (`<video class="ambient-video-layer" autoplay loop muted playsinline preload="metadata">`) using WebM media with low opacity (`0.22`) and high-contrast saturation.
  - Multi-source fallback with mobile battery/data-saver detection; automatically gracefully falls back to CSS nebula and particle grid if video autoplay is restricted.
  - Overlayed with a deep obsidian scrim & radial vignette (`.ambient-scrim`), preserving 100% text contrast and obsidian card glassmorphism.
- **Layer 1: Ambient Floating Cyberpunk Nebula Orbs (Option A):**
  - Designed 3 dynamic aurora orbs (`.ambient-nebula-orb`, `.nebula-1`, `.nebula-2`, `.nebula-3`) with 90px blurs in indigo, purple, and mint.
  - Animated with smooth asynchronous CSS transforms (`@keyframes floatNebula1`, `floatNebula2`, `floatNebula3`) creating a living, breathing background without GPU strain.
  - Terminal-specific accent support (`accent-purple-glow`, `accent-teal-glow`, `accent-rose-glow`) for specialized roles.
- **Layer 2: Interactive Cyberpunk Particle Grid Canvas (Option B):**
  - Created `public/js/ambient-visuals.js` managing an interactive HTML5 canvas (`#bounty-particle-canvas`).
  - Dynamic responsive node scaling (22–52 nodes based on screen resolution) with Retina `devicePixelRatio` handling.
  - Renders inter-node distance connections with glowing line opacity when distance < 105px.
  - Constellation cursor interaction: nearby particles subtly connect to mouse movements with interactive trail lines and gentle magnetic drift.
  - Battery/CPU optimization: automatically pauses the `requestAnimationFrame` render loop when tab visibility changes (`document.hidden`).
- **Universal Engine Integration:**
  - Global helper `render_ambient_background(?string $accent = null)` in `public/config.php` automatically called in `render_header()`.
  - Also embedded across all standalone login gates: User Auth (`public/portal/auth.php`), Founder Terminal (`public/admin/login.php`), Support Desk (`public/support/login.php`), and Threat Patrol (`public/mod/login.php`).
  - Full WCAG accessibility compliance with `@media (prefers-reduced-motion: reduce)`.

### [Phase 13] Luxury Premium UI Overhaul & Luminescent Jewel Color Palette
- **1. Luminescent Jewel Color Palette & Obsidian Caviar Canvas (`stitch-tokens.css`, `config.php`):**
  - Upgraded platform canvas from `#0B0F17` to deep obsidian caviar `#070A11` (`bg-[#070A11]`) across all layouts, portals, and login gates.
  - Formulated a 5-tier luminescent jewel color system with tailored neon drop shadows and specular glassmorphism:
    * **Solar Amber & Gold (`#F59E0B`):** Applied to Escrow Vaults, Leaderboard #1 rank (Alex Chen), Hunter persona badges, and coins.
    * **Electric Cyan (`#06B6D4`):** Applied to Active Bounties, Vacancies, Support Desk, and live WebSocket heartbeat indicators.
    * **Cyber Jade & Mint Emerald (`#10B981`):** Applied to Hostinger PHP 8.2 Engine, Escrow Ready balances, and Recruiter badges.
    * **Hyper Indigo & Royal Violet (`#6366F1`, `#A855F7`):** Applied to master brand aura, Lounge activity, Founder telemetry, and primary CTA gradients.
    * **Rose Crimson (`#F43F5E`, `#EF4444`):** Applied to Threat Patrol verification gates, active disputes, and Sybil warnings.
- **2. Specular Glassmorphism & Jewel Design Tokens (`public/css/stitch-tokens.css`):**
  - Formulated `.glass-card`, `.bg-stitch-card`, and `.premium-card` with specular top highlights (`border-t-white/20`), inner refraction rims (`box-shadow: inset 0 1px 0 0 rgba(255,255,255,0.10)`), and luminous hover states.
  - Added radiant text glow utilities: `.text-glow-gold`, `.text-glow-cyan`, `.text-glow-mint`, `.text-glow-purple`, `.text-glow-title`.
  - Built hardware-vault coin pill token `.coin-pill-vault` with metallic rim sheen, dual glowing pulse beacon, and BDT formatting.
  - Created luxury CTA buttons `.btn-luxury-primary` (indigo-purple) and `.btn-luxury-emerald` (cyber jade) with hover micro-scaling (`scale-[1.02]`), specular edges, and neon glow.
  - Formulated universal jewel badge classes: `.badge-gold`, `.badge-emerald`, `.badge-cyan`, `.badge-purple`, `.badge-rose`.
- **3. Master Floating Header & Mobile Dock Modernization (`public/config.php`):**
  - Master Header: Elevated with specular top shine, glowing gradient brand bolt (`shadow-[0_0_25px_rgba(99,102,241,0.55)]`), active nav gradient pill highlight, `.coin-pill-vault` for BDT wallet custody, and glowing avatar ring on the identity chip.
  - Layout Canvas: Added `relative z-10` to `<main>` container, eliminating visual occlusion and providing high-contrast depth separation over the ambient background.
  - Mobile Floating Glass Dock: Modernized with `.glass-dock`, specular top border (`border-t-white/20`), backdrop blur 24px, and glowing jewel icons (forum, cases, add task, screening, support).
- **4. Live Lounge Feed & Sidebar Overhaul (`public/portal/index.php`):**
  - Solid background occlusion resolved: set `.portal-outer-wrapper` to `bg-transparent relative z-10`, allowing the deep-space video, nebulae, and interactive particle grid to shine through frosted glass.
  - Top 4 Platform Stats Banner: Redesigned with jewel borders and radiant glowing numbers (Gold Vault, Cyan Bounties, Purple Hunters, Emerald Engine).
  - Status Update Box: Upgraded with glowing avatar ring, frosted textarea, and luxury CTA buttons (`[Create Paid Job / Bounty]` in cyber jade and `[Post Update]` in hyper indigo).
  - Job Alert Cards: Enhanced with left cyber jade glowing bar (`border-l-[#10B981] shadow-[-6px_0_20px_-3px_rgba(16,185,129,0.35)]`), pulsing escrow beacon, category jewel pills, and luxury apply CTA.
  - Emoji Reactions & Q&A: Frosted jewel pill buttons with active neon glow states and sleek terminal styling on inquiry threads.
  - Sidebar Widgets: Upgraded Community Activity radar beacon with jewel counts, and Top Earners Leaderboard with Gold (🥇), Cyan (🥈), Emerald (🥉), and Purple (🎖️) glowing earnings.
  - [Create Paid Job / Bounty] Modal: Transformed into a frosted obsidian glass panel with specular borders and luxury escrow lock button.
- **5. Interactive Tactile Ripple Feedback (`public/js/ambient-visuals.js`):**
  - Integrated click ripple burst engine (`drawRipples()`): clicks anywhere in the portal emit expanding neon cyan rings with gentle decay.
  - Boosted particle halo luminescence (`ctx.shadowBlur = 8`) and dynamic two-tone connection lines between nodes and cursor.
- **6. Authentication & Role Terminal Unification (`auth.php`, `admin/login.php`, `support/login.php`, `mod/login.php`):**
  - Synchronized canvas background to deep obsidian `#070A11` and added specular top borders (`border-t-white/20`, `border-t-purple-400/40`, `border-t-teal-400/40`, `border-t-rose-400/40`) to all auth cards.

---

## 🚀 4. Upcoming Tasks & Future Roadmap ("Will Be Done")

### Priority 1: Supabase Database Migration
- [ ] **Apply `schema.sql` to Live Supabase Project:**
  - Project ID: `ehswbdmizytpahosqkuj`
  - Execute DDL to instantiate the 8 tables and 2 RPC functions in the live PostgreSQL instance.
- [ ] **Configure Service-Role Key in `.env`:**
  - Add `SUPABASE_SERVICE_ROLE_KEY` to `public/.env` for secure server-side RPC execution.

### Priority 2: Supabase Auth & JWT Session Bridging
- [ ] **Bridge Supabase Auth to PHP Session:**
  - Enable native user login/signup via `@supabase/supabase-js` on the frontend.
  - Transmit Supabase Auth JWT access token to PHP via secure HTTP-only cookie.
  - Implement server-side JWT verification in `public/config.php` to map authenticated Supabase Auth users to `public.profiles`.

### Priority 3: Candidate Application & Submission Flow
- [ ] **Create Application Submission API (`public/api/applications_handler.php`):**
  - Allow bounty hunters to submit proposals, GitHub repositories, and preview links.
  - Enforce one submission per candidate per bounty.
- [ ] **Automated Proof Verification / Threat Scanner:**
  - Implement heuristic URL checking for GitHub repos and file upload links.
  - Automatically populate `public.task_verifications` with threat scores.

### Priority 4: Production Deployment to Hostinger
- [ ] **Hostinger Git Deployment / FTP Sync:**
  - Set up automated Git sync or SFTP deployment pipeline to Hostinger public root.
  - Configure Hostinger `.htaccess` for production SSL redirection and custom error pages.
- [ ] **Hostinger PHP.ini Fine-Tuning:**
  - Ensure `cURL`, `OpenSSL`, and CA certificate bundles are up to date on the remote host.

---

## 📂 5. File Inventory & Verification Summary

| File | Status | Last Check | Purpose |
|---|---|---|---|
| `.htaccess` | ✅ Verified (Syntax Clean) | 2026-09-11 | Root Apache rewrite, clean URL router & sensitive file shield |
| `index.php` | ✅ Verified (Syntax Clean) | 2026-09-11 | Root router redirecting to `/public/portal/index.php` (Hostinger 403 fix) |
| `public/portal/index.php` | ✅ Verified (Phase 13) | 2026-09-11 | Live Community Lounge with jewel stats, specular cards, reactions, Q&A & leaderboard |
| `public/portal/auth.php` | ✅ Verified (Phase 13) | 2026-09-11 | User portal login & signup with Hunter/Recruiter role tabs, obsidian caviar & luxury buttons |
| `public/admin/login.php` | ✅ Verified (Phase 13) | 2026-09-11 | Founder & Admin Terminal login with purple accent, specular card & Elena Vance quick-fill |
| `public/support/login.php` | ✅ Verified (Phase 13) | 2026-09-11 | Customer Support Desk login with teal accent, specular card & Devon Bailey quick-fill |
| `public/mod/login.php` | ✅ Verified (Phase 13) | 2026-09-11 | Threat Patrol Staff Terminal login with red accent, specular card & Sarah Jenkins quick-fill |
| `public/api/auth_handler.php` | ✅ Verified (Phase 11) | 2026-09-11 | Authentication controller, boundary enforcement & cross-portal gate |
| `public/api/reaction_handler.php` | ✅ Verified (Phase 11) | 2026-09-11 | Social emoji reaction controller (toggle, counters & Supabase broadcast) |
| `public/api/comment_handler.php` | ✅ Verified (Phase 11) | 2026-09-11 | Job inquiry & Q&A thread controller with Supabase real-time broadcast |
| `public/js/app.js` | ✅ Verified (Phase 11) | 2026-09-11 | Realtime reactions & Q&A event listeners, delegation, Web AudioFX & BDT cards |
| `public/js/ambient-visuals.js` | ✅ Verified (Phase 13) | 2026-09-11 | Interactive cyberpunk canvas particle grid, click ripple burst & video safety |
| `public/css/stitch-tokens.css` | ✅ Updated (Phase 13) | 2026-09-11 | Luminescent jewel palette, specular cards, text glow, coin vault pill & luxury buttons |
| `public/js/sneak-bar.js` | ✅ Verified (Active) | 2026-09-11 | Fixed top purple sneak banner & persona switcher dock |
| `public/config.php` | ✅ Verified (Phase 13) | 2026-09-11 | Floating header with specular shine, coin vault pill, jewel badges & relative z-10 |
| `public/api/admin_sneak.php` | ✅ Verified (Syntax Clean) | 2026-09-11 | Persona teleportation controller (JSON + Form support) |
| `public/portal/candidate_review.php`| ✅ Verified (Syntax Clean) | 2026-09-11 | 100-to-2 Applicant Screening Accordion with Stitch empty state |
| `public/portal/job_hub.php` | ✅ Verified (Syntax Clean) | 2026-09-11 | Job Listings & Recruiter ATS with BDT currency localization |
| `public/admin/index.php` | ✅ Verified (Phase 11) | 2026-09-11 | Founder Telemetry & Infiltration Center with `/admin/login` redirect gate |
| `public/admin/branding.php` | ✅ Verified (Phase 11) | 2026-09-11 | White-Label & Sneak Console with `/admin/login` redirect gate |
| `public/support/index.php` | ✅ Verified (Phase 11) | 2026-09-11 | Staff Support Desk with dispute queue & `/support/login` redirect gate |
| `public/mod/index.php` | ✅ Verified (Phase 11) | 2026-09-11 | Threat Patrol Verification Queue with `/staff/login` redirect gate |
| `public/api/jobs_handler.php` | ✅ Verified (Syntax Clean) | 2026-09-11 | Escrow job creation via `post_job_with_escrow()` |
| `public/api/payout_handler.php` | ✅ Verified (Syntax Clean) | 2026-09-11 | Escrow payout release via `hire_and_release_payout()` |
| `public/api/telegram_dispatcher.php` | ✅ Verified (Syntax Clean) | 2026-09-11 | Automated Telegram notification bridge with BDT localization |
| `schema.sql` | ✅ Ready to Apply | 2026-09-11 | DDL for PostgreSQL tables, triggers & RPCs |
| `public/.env` | ✅ Active | 2026-09-11 | Local & Supabase credentials |
| `.agent/instructions.md` | ✅ Updated | 2026-09-11 | Architect rules & auto-documentation directives |
| `NOTE.md` | ✅ Synchronized | 2026-09-11 | Master project log (Local + Google Drive) |

---
*Note: This document is maintained continuously by Antigravity and synchronized to local and Google Drive repositories upon every update.*


