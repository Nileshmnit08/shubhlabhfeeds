-- =============================================================================
-- Migration 001 Rollback: Drop Initial Schema
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS navigation_items;
DROP TABLE IF EXISTS knowledge_articles;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS product_categories;
DROP TABLE IF EXISTS site_sections;
DROP TABLE IF EXISTS site_settings;
DROP TABLE IF EXISTS media_assets;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS migrations;

SET FOREIGN_KEY_CHECKS = 1;
