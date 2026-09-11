-- ==============================================================================
-- BOUNTY COMMUNITY PLATFORM SCHEMA & STORED PROCEDURES
-- Target: Supabase PostgreSQL (with RLS & Realtime)
-- ==============================================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. PROFILES & PERSONAS TABLE
CREATE TABLE IF NOT EXISTS public.profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role TEXT NOT NULL CHECK (role IN ('admin', 'recruiter', 'hunter', 'mod', 'support')),
    display_name TEXT NOT NULL,
    handle TEXT NOT NULL UNIQUE,
    email TEXT UNIQUE,
    avatar_url TEXT,
    wallet_balance NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    rating NUMERIC(3, 2) DEFAULT 5.00,
    reputation_score INT DEFAULT 100,
    bio TEXT,
    skills TEXT[] DEFAULT ARRAY[]::TEXT[],
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. JOBS & BOUNTIES TABLE
CREATE TABLE IF NOT EXISTS public.jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    creator_id UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    description TEXT,
    category TEXT NOT NULL DEFAULT 'Development',
    bounty_amount NUMERIC(12, 2) NOT NULL CHECK (bounty_amount > 0),
    escrow_status TEXT NOT NULL CHECK (escrow_status IN ('locked', 'released', 'refunded', 'disputed')) DEFAULT 'locked',
    status TEXT NOT NULL CHECK (status IN ('open', 'in_review', 'awarded', 'closed')) DEFAULT 'open',
    skills_required TEXT[] DEFAULT ARRAY[]::TEXT[],
    deadline TIMESTAMPTZ,
    hired_candidate_id UUID REFERENCES public.profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 3. CANDIDATE APPLICATIONS / SUBMISSIONS
CREATE TABLE IF NOT EXISTS public.applications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    job_id UUID NOT NULL REFERENCES public.jobs(id) ON DELETE CASCADE,
    candidate_id UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    pitch TEXT,
    portfolio_url TEXT,
    github_url TEXT,
    status TEXT NOT NULL CHECK (status IN ('applied', 'screening', 'shortlisted', 'hired', 'rejected')) DEFAULT 'applied',
    match_score INT DEFAULT 88 CHECK (match_score BETWEEN 0 AND 100),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT unique_job_candidate UNIQUE (job_id, candidate_id)
);

-- 4. ESCROW TRANSACTION LEDGER
CREATE TABLE IF NOT EXISTS public.escrow_ledger (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    job_id UUID REFERENCES public.jobs(id) ON DELETE SET NULL,
    from_user_id UUID REFERENCES public.profiles(id) ON DELETE SET NULL,
    to_user_id UUID REFERENCES public.profiles(id) ON DELETE SET NULL,
    amount NUMERIC(12, 2) NOT NULL,
    fee_amount NUMERIC(12, 2) NOT NULL DEFAULT 0.00,
    action TEXT NOT NULL CHECK (action IN ('lock', 'release', 'refund', 'dispute_hold')),
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 5. LIVE CHAT & BROADCAST FEED
CREATE TABLE IF NOT EXISTS public.chat_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    sender_id UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    channel TEXT NOT NULL DEFAULT 'lounge',
    message_type TEXT NOT NULL CHECK (message_type IN ('chat', 'job_broadcast', 'system_alert')) DEFAULT 'chat',
    meta_json JSONB DEFAULT '{}'::JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 6. TASK VERIFICATION & THREAT PATROL
CREATE TABLE IF NOT EXISTS public.task_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    job_id UUID NOT NULL REFERENCES public.jobs(id) ON DELETE CASCADE,
    candidate_id UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    proof_url TEXT NOT NULL,
    proof_notes TEXT,
    status TEXT NOT NULL CHECK (status IN ('pending', 'approved', 'flagged', 'rejected')) DEFAULT 'pending',
    mod_notes TEXT,
    reviewed_by UUID REFERENCES public.profiles(id) ON DELETE SET NULL,
    threat_score INT DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 7. SUPPORT TICKETS & DESK
CREATE TABLE IF NOT EXISTS public.support_tickets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    subject TEXT NOT NULL,
    category TEXT NOT NULL CHECK (category IN ('escrow_dispute', 'verification', 'payout', 'general')),
    priority TEXT NOT NULL CHECK (priority IN ('low', 'medium', 'high', 'critical')) DEFAULT 'medium',
    status TEXT NOT NULL CHECK (status IN ('open', 'in_progress', 'resolved', 'closed')) DEFAULT 'open',
    assigned_to UUID REFERENCES public.profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS public.support_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id UUID NOT NULL REFERENCES public.support_tickets(id) ON DELETE CASCADE,
    sender_id UUID NOT NULL REFERENCES public.profiles(id) ON DELETE CASCADE,
    message TEXT NOT NULL,
    is_staff BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- ==============================================================================
-- STORED PROCEDURES / RPC FUNCTIONS
-- ==============================================================================

-- RPC 1: post_job_with_escrow
CREATE OR REPLACE FUNCTION public.post_job_with_escrow(
    p_creator_id UUID,
    p_title TEXT,
    p_description TEXT,
    p_category TEXT,
    p_bounty_amount NUMERIC,
    p_skills_required TEXT[],
    p_deadline TIMESTAMPTZ
)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
    v_creator_balance NUMERIC;
    v_new_job_id UUID;
    v_creator_handle TEXT;
BEGIN
    -- 1. Check creator balance
    SELECT wallet_balance, handle INTO v_creator_balance, v_creator_handle
    FROM public.profiles
    WHERE id = p_creator_id
    FOR UPDATE;

    IF v_creator_balance IS NULL THEN
        RAISE EXCEPTION 'Creator profile not found.';
    END IF;

    IF v_creator_balance < p_bounty_amount THEN
        RAISE EXCEPTION 'Insufficient balance to lock escrow. Balance: %, Required: %', v_creator_balance, p_bounty_amount;
    END IF;

    -- 2. Deduct from creator wallet
    UPDATE public.profiles
    SET wallet_balance = wallet_balance - p_bounty_amount
    WHERE id = p_creator_id;

    -- 3. Insert Job with status locked
    INSERT INTO public.jobs (
        creator_id, title, description, category, bounty_amount, escrow_status, status, skills_required, deadline
    ) VALUES (
        p_creator_id, p_title, p_description, COALESCE(p_category, 'Engineering'), p_bounty_amount, 'locked', 'open', p_skills_required, p_deadline
    ) RETURNING id INTO v_new_job_id;

    -- 4. Record Escrow Ledger entry
    INSERT INTO public.escrow_ledger (
        job_id, from_user_id, to_user_id, amount, fee_amount, action, notes
    ) VALUES (
        v_new_job_id, p_creator_id, NULL, p_bounty_amount, 0.00, 'lock', 'Escrow locked upon bounty creation'
    );

    -- 5. Broadcast to lounge chat feed
    INSERT INTO public.chat_messages (
        sender_id, message, channel, message_type, meta_json
    ) VALUES (
        p_creator_id,
        '🚀 New Bounty Broadcast: ' || p_title || ' ($' || p_bounty_amount || ') locked in Escrow!',
        'lounge',
        'job_broadcast',
        jsonb_build_object(
            'job_id', v_new_job_id,
            'title', p_title,
            'bounty', p_bounty_amount,
            'creator', v_creator_handle
        )
    );

    RETURN jsonb_build_object(
        'success', true,
        'job_id', v_new_job_id,
        'escrow_amount', p_bounty_amount,
        'remaining_balance', (v_creator_balance - p_bounty_amount)
    );
END;
$$;

-- RPC 2: hire_and_release_payout
CREATE OR REPLACE FUNCTION public.hire_and_release_payout(
    p_job_id UUID,
    p_candidate_id UUID,
    p_actor_id UUID,
    p_platform_fee_percent NUMERIC DEFAULT 5.0
)
RETURNS JSONB
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
    v_job RECORD;
    v_fee NUMERIC;
    v_net_payout NUMERIC;
    v_actor_role TEXT;
    v_candidate_handle TEXT;
BEGIN
    -- Check actor authorization
    SELECT role INTO v_actor_role FROM public.profiles WHERE id = p_actor_id;
    
    -- Lock job row
    SELECT * INTO v_job FROM public.jobs WHERE id = p_job_id FOR UPDATE;
    IF v_job IS NULL THEN
        RAISE EXCEPTION 'Job % not found.', p_job_id;
    END IF;

    IF v_job.creator_id != p_actor_id AND v_actor_role NOT IN ('admin', 'mod') THEN
        RAISE EXCEPTION 'Unauthorized: Only the creator or administrator can release payout.';
    END IF;

    IF v_job.escrow_status != 'locked' THEN
        RAISE EXCEPTION 'Escrow cannot be released. Current status: %', v_job.escrow_status;
    END IF;

    -- Calculate Fee and Net Payout
    v_fee := ROUND((v_job.bounty_amount * (p_platform_fee_percent / 100.0)), 2);
    v_net_payout := v_job.bounty_amount - v_fee;

    -- Update Candidate Wallet
    UPDATE public.profiles
    SET wallet_balance = wallet_balance + v_net_payout,
        reputation_score = reputation_score + 25
    WHERE id = p_candidate_id
    RETURNING handle INTO v_candidate_handle;

    -- Update Job Record
    UPDATE public.jobs
    SET escrow_status = 'released',
        status = 'awarded',
        hired_candidate_id = p_candidate_id
    WHERE id = p_job_id;

    -- Update Candidate Application
    UPDATE public.applications
    SET status = 'hired'
    WHERE job_id = p_job_id AND candidate_id = p_candidate_id;

    -- Other applicants become rejected
    UPDATE public.applications
    SET status = 'rejected'
    WHERE job_id = p_job_id AND candidate_id != p_candidate_id AND status != 'hired';

    -- Record in Escrow Ledger
    INSERT INTO public.escrow_ledger (
        job_id, from_user_id, to_user_id, amount, fee_amount, action, notes
    ) VALUES (
        p_job_id, v_job.creator_id, p_candidate_id, v_net_payout, v_fee, 'release',
        'Bounty awarded. Platform fee ' || p_platform_fee_percent || '%'
    );

    -- Broadcast to Lounge
    INSERT INTO public.chat_messages (
        sender_id, message, channel, message_type, meta_json
    ) VALUES (
        p_actor_id,
        '🎉 Bounty Awarded! $' || v_job.bounty_amount || ' escrow released to @' || COALESCE(v_candidate_handle, 'hunter') || ' for "' || v_job.title || '"',
        'lounge',
        'system_alert',
        jsonb_build_object(
            'job_id', p_job_id,
            'winner', v_candidate_handle,
            'net_payout', v_net_payout
        )
    );

    RETURN jsonb_build_object(
        'success', true,
        'job_id', p_job_id,
        'candidate_id', p_candidate_id,
        'bounty_released', v_job.bounty_amount,
        'platform_fee', v_fee,
        'net_payout', v_net_payout
    );
END;
$$;

-- Enable Realtime
ALTER PUBLICATION supabase_realtime ADD TABLE public.chat_messages;
ALTER PUBLICATION supabase_realtime ADD TABLE public.jobs;
ALTER PUBLICATION supabase_realtime ADD TABLE public.support_tickets;

-- Seed Data for Default Personas
INSERT INTO public.profiles (id, role, display_name, handle, email, avatar_url, wallet_balance, rating, reputation_score, bio, skills)
VALUES 
    ('11111111-1111-1111-1111-111111111111', 'admin', 'Elena Vance', 'founder_elena', 'elena@bounty.community', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80', 50000.00, 5.00, 1000, 'Founder & Principal Architect at Bounty Community.', ARRAY['Architecture', 'Supabase', 'Security', 'Escrow Operations']),
    ('22222222-2222-2222-2222-222222222222', 'recruiter', 'Marcus Sterling', 'marcus_hire', 'marcus@hypergrowth.vc', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80', 12450.00, 4.92, 450, 'Talent Lead funding bounties across Web3, AI, and Full-Stack.', ARRAY['Talent Sourcing', 'ATS', 'Screening', 'Engineering Management']),
    ('33333333-3333-3333-3333-333333333333', 'hunter', 'Alex Chen', 'alex_code', 'alex@buildspace.dev', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80', 3820.00, 4.98, 890, 'Elite Bounty Hunter specialized in PHP, TypeScript, and Postgres RPCs.', ARRAY['PHP 8.2', 'Supabase', 'Tailwind CSS', 'PostgreSQL', 'API Security']),
    ('44444444-4444-4444-4444-444444444444', 'mod', 'Sarah Jenkins', 'patrol_sarah', 'mod@bounty.community', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80', 1500.00, 4.95, 620, 'Threat patrol, anti-spam guardian, and task verification lead.', ARRAY['Compliance', 'Proof Verification', 'Dispute Resolution', 'Threat Analysis']),
    ('55555555-5555-5555-5555-555555555555', 'support', 'Devon Bailey', 'support_desk', 'support@bounty.community', 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=150&auto=format&fit=crop&q=80', 800.00, 4.88, 380, 'Senior Escrow Dispute Specialist & User Success Advocate.', ARRAY['Customer Success', 'Escrow Arbitrations', 'Technical Troubleshooting'])
ON CONFLICT (id) DO NOTHING;
