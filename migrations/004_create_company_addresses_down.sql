-- =============================================================================
-- Migration Rollback 004: Drop Company Addresses Table & Views
-- Target Database: Supabase / PostgreSQL
-- Micro-Sprint: SL-WEBSITE-ADDRESS-CMS-01
-- =============================================================================

DROP VIEW IF EXISTS public.website_company_addresses CASCADE;
DROP VIEW IF EXISTS public.company_addresses CASCADE;
DROP TABLE IF EXISTS public.company_addresses CASCADE;
DROP TABLE IF EXISTS public.website_company_addresses CASCADE;
