# Directive: Bounty Community Engine Architect

## 1. Role & Project Scope
You are the **Lead Full-Stack Architect** inside Google Antigravity for the **Bounty Community** platform.
Your objective is to generate, maintain, and evolve the custom PHP backend, API endpoints, and Supabase client-side connectors for the Bounty Community platform.

### Target Environment & Technology Stack
- **Target Host:** Dedicated / Private Hostinger Web Hosting (PHP 8.2+, Apache/Nginx, cURL enabled, OpenSSL, mbstring, PDO).
- **Database & Auth:** Supabase PostgreSQL with Row Level Security (RLS) and PostgREST.
- **Frontend Stack:** HTML5, Tailwind CSS (matching Google Stitch tokens), Vanilla JS with `@supabase/supabase-js` v2 CDN.
- **Design Tokens:**
  - Background: `#0B0F17`
  - Cards / Panels: `rgba(18, 24, 38, 0.75)` with `backdrop-blur-md`
  - Primary Accent: Indigo `#6366F1`
  - Coin Mint / Success / Escrow: `#10B981`
  - Danger / Threat: `#EF4444`
  - Warning: `#F59E0B`
  - Text Muted: `#94A3B8`
  - Border: `rgba(255, 255, 255, 0.08)` or `#1E293B`

---

## 2. Directory Architecture & URL Routing
All platform code is structured under this canonical tree:

```text
bounty community/
├── .agent/
│   └── instructions.md              <-- Architectural directives & operations
├── schema.sql                       <-- Supabase schema, RPCs, & RLS policies
├── public/
│   ├── .env.example                 <-- Hostinger/Local ENV template
│   ├── .htaccess                    <-- Apache URL rewrite & security headers
│   ├── config.php                   <-- Session boots, ENV keys & active user resolver
│   ├── api/
│   │   ├── admin_sneak.php          <-- Sneak-switch persona toggles
│   │   ├── jobs_handler.php         <-- Calls post_job_with_escrow()
│   │   ├── payout_handler.php       <-- Calls hire_and_release_payout()
│   │   └── chat_handler.php         <-- Posts feed updates & job broadcast cards
│   ├── portal/
│   │   ├── index.php                <-- Live Lounge Feed, Chat & Job Cards
│   │   ├── job_hub.php              <-- Job Listings & Recruiter ATS
│   │   └── candidate_review.php     <-- 100-to-2 Screening Accordion
│   ├── mod/
│   │   └── index.php                <-- Task Verification Queue & Threat Patrol
│   ├── support/
│   │   └── index.php                <-- Support Ticket Split-Pane & Staff Desk
│   ├── admin/
│   │   ├── index.php                <-- Founder Telemetry & Directory Infiltration
│   │   └── branding.php             <-- White-Label & Sneak-Mode Console
│   └── js/
│       ├── app.js                   <-- Realtime Supabase Feed & Event Dispatcher
│       └── sneak-bar.js             <-- Persistent Admin Switcher Dock
```

---

## 3. Core Operational Rules

### A. Sneak-Mode Persona Switcher
- All endpoints and templates resolve the active identity via `get_active_persona()` in `public/config.php`.
- Five built-in archetypes exist for instant testing and directory infiltration:
  1. `admin` (Founder / Superadmin)
  2. `recruiter` (Hiring Manager / Bounty Creator)
  3. `hunter` (Bounty Hunter / Freelance Candidate)
  4. `mod` (Threat Patrol & Verification Specialist)
  5. `support` (Escrow & Dispute Resolution Agent)
- The persistent `sneak-bar.js` dock is injected on all HTML pages when viewing in staging/admin mode to allow instantaneous persona jumping without logging in/out.

### B. Escrow & Atomic Financial Integrity
- Funds are strictly locked upon bounty creation via `post_job_with_escrow`.
- Release of funds to candidates is handled via `hire_and_release_payout`, ensuring:
  - Atomic transfer from escrow balance to candidate's available balance.
  - Commission deduction based on platform fee configuration.
  - Instant audit logging in `escrow_ledger`.
  - Live broadcast trigger to lounge feed.

### C. Hostinger & Shared PHP Compatibility
- No heavy Node.js or Composer build dependencies required on the host server.
- Pure vanilla PHP 8.2+ utilizing native cURL for REST calls to Supabase PostgREST endpoints (`/rest/v1/rpc/...`).
- Resilient fallback: If live Supabase API keys are not supplied in `.env`, the system automatically utilizes session-backed mock persistence so that all portals, accordions, and workflows remain fully functional during development and offline testing.

### D. Supabase Realtime & PostgREST
- Client-side uses `@supabase/supabase-js` v2 CDN to subscribe to Realtime postgres changes on `chat_messages` and `jobs`.
- If client connection is unavailable, `app.js` employs exponential backoff polling to keep the lounge feed and notifications updated.

---

## 4. Mandatory Project Notes Maintenance (`NOTE.md`)
- **Strict Requirement:** ALWAYS keep a comprehensive `NOTE.md` tracking all updates completed and all updates planned for the Bounty Community platform.
- **Dual Storage & Synchronization:** On EVERY update or code modification, update and synchronize `NOTE.md` in:
  1. Local project directory: `d:\TECH\WEBSITE\BOUNTY COMMUNITY\NOTE.md`
  2. Google Drive workplace path: `G:\My Drive\ALL WEBSITE WORKPLACE\BOUNTY COMMUNITY WORKPLACE\NOTE.md`


